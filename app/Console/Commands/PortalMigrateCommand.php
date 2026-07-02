<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\Activity;
use App\Models\Crm\Invoice;
use App\Models\Crm\InvoiceItem;
use App\Models\Crm\Lead;
use App\Models\Crm\Payment;
use App\Models\Crm\Project;
use App\Models\User;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use App\Modules\ExpenseTracker\Services\ExpenseNumberService;
use App\Modules\ServiceDesk\Enums\TicketPriority;
use App\Modules\ServiceDesk\Enums\TicketStatus;
use App\Modules\ServiceDesk\Enums\TicketType;
use App\Modules\ServiceDesk\Models\Ticket;
use App\Modules\ServiceDesk\Services\TicketNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Migrates CRM data from the legacy RISE CRM (portal.quakelogic.net) into this
 * platform. The RISE dump is loaded into an isolated `portal_import` database
 * first; this command reads from there and writes into our tables.
 *
 * SAFETY: never deletes or truncates anything. Existing records are matched
 * (by email / name) and skipped, so it is idempotent and safe to re-run. A
 * `migration_map` table in portal_import records source-id -> target-id for
 * every entity, so foreign keys (contact->company, lead->contact, …) remap
 * reliably across phases and re-runs. Use --dry-run to preview counts.
 *
 * Out of scope by request: purchasing (rise_pur_*), inventory/warehouse, the
 * product catalog, proposals, and the mailbox/session tables. Those belong to
 * other work streams and are never touched.
 */
class PortalMigrateCommand extends Command
{
    protected $signature = 'portal:migrate
        {--only= : Comma list of entities (users,companies,contacts,leads,projects,invoices,estimates,tickets,expenses,notes). Default: all in order}
        {--org=1 : Target organization id}
        {--owner= : Fallback owner/creator user id (defaults to the lowest-id user in the org)}
        {--source=portal_import : Source database name (already loaded from the RISE dump)}
        {--dry-run : Report what would happen; write nothing}';

    protected $description = 'Migrate CRM data (companies, contacts, leads, staff) from the legacy RISE portal into this platform';

    private bool $dry = false;
    private int $org = 1;
    private int $fallbackOwnerId;

    /** In-memory source-id -> target-id cache, seeded from the persisted migration_map. */
    private array $mem = [];
    private int $dryCounter = 0;

    /**
     * Insert a record with its ULID set explicitly and model events suppressed.
     * Suppressing events keeps the bulk import from flooding the RAG queue with
     * hundreds of ReindexEmbeddingJob dispatches (Company/Contact are observed);
     * re-index afterward in a paced way with `kb:embed`.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     * @param  class-string<T>  $class
     * @return T
     */
    private function makeRecord(string $class, array $attrs)
    {
        $attrs['ulid'] = (string) Str::ulid();

        return $class::withoutEvents(fn () => $class::create($attrs));
    }

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $this->org = (int) $this->option('org');

        // A dedicated read connection onto the isolated import database.
        config(['database.connections.portal' => array_merge(
            config('database.connections.mysql'),
            ['database' => $this->option('source')],
        )]);
        DB::purge('portal');

        try {
            DB::connection('portal')->getPdo();
        } catch (\Throwable $e) {
            $this->error("Cannot reach source database '{$this->option('source')}': {$e->getMessage()}");

            return self::FAILURE;
        }

        $owner = $this->option('owner')
            ? (int) $this->option('owner')
            : (int) User::where('organization_id', $this->org)->orderBy('id')->value('id');
        if (! $owner) {
            $this->error("No fallback owner: organization {$this->org} has no users. Pass --owner=<id>.");

            return self::FAILURE;
        }
        $this->fallbackOwnerId = $owner;

        $this->ensureMapTable();

        $this->line($this->dry ? '<comment>DRY RUN — no data will be written.</comment>' : '<info>LIVE RUN — writing data.</info>');
        $this->line("Target org: {$this->org} · fallback owner user: {$this->fallbackOwnerId}");
        $this->newLine();

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $run = fn (string $e) => empty($only) || in_array($e, $only, true);

        // Dependency order: users -> companies -> contacts -> leads.
        if ($run('users')) {
            $this->migrateUsers();
        }
        if ($run('companies')) {
            $this->migrateCompanies();
        }
        if ($run('contacts')) {
            $this->migrateContacts();
        }
        if ($run('leads')) {
            $this->migrateLeads();
        }
        if ($run('projects')) {
            $this->migrateProjects();
        }
        if ($run('invoices')) {
            $this->migrateInvoices();
        }
        if ($run('estimates')) {
            $this->migrateEstimates();
        }
        if ($run('tickets')) {
            $this->migrateTickets();
        }
        if ($run('expenses')) {
            $this->migrateExpenses();
        }
        if ($run('notes')) {
            $this->migrateNotes();
        }

        $this->newLine();
        $this->info($this->dry ? 'Dry run complete.' : 'Migration complete.');

        return self::SUCCESS;
    }

    private function migrateUsers(): void
    {
        $rows = DB::connection('portal')->table('rise_users')
            ->where('user_type', 'staff')->where('deleted', 0)->get();

        [$created, $matched, $skipped] = [0, 0, 0];
        foreach ($rows as $u) {
            $email = trim((string) $u->email);
            if ($email === '') {
                $skipped++;
                continue;
            }
            $name = trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $email;

            $existing = User::where('email', $email)->first();
            if ($existing) {
                $this->putMap('user', (int) $u->id, (int) $existing->id, 'matched');
                $matched++;
                continue;
            }

            if ($this->dry) {
                $this->putMap('user', (int) $u->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $user = $this->makeRecord(User::class, [
                'organization_id' => $this->org,
                'name' => $name,
                'email' => $email,
                'title' => $u->job_title ?: null,
                'phone' => $u->phone ?: null,
                'password' => Hash::make(Str::random(32)),
                'is_active' => ((string) $u->status === 'active') && ! $u->disable_login,
            ]);
            $this->putMap('user', (int) $u->id, (int) $user->id, 'create');
            $created++;
        }

        $this->reportEntity('Staff users', $rows->count(), $created, $matched, $skipped);
    }

    private function migrateCompanies(): void
    {
        $rows = DB::connection('portal')->table('rise_clients')
            ->where('is_lead', 0)->where('deleted', 0)->get();

        [$created, $matched, $skipped] = [0, 0, 0];
        foreach ($rows as $c) {
            $name = trim((string) $c->company_name);
            if ($name === '') {
                $skipped++;
                continue;
            }

            $existing = Company::where('organization_id', $this->org)
                ->where(function ($q) use ($c, $name) {
                    $q->where('notes', 'like', '%[[portal_client:'.$c->id.']]%')
                        ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
                })->first();
            if ($existing) {
                $this->putMap('company', (int) $c->id, (int) $existing->id, 'matched');
                $matched++;
                continue;
            }

            if ($this->dry) {
                $this->putMap('company', (int) $c->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $company = $this->makeRecord(Company::class, [
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($c->created_by),
                'owner_id' => $this->ownerFor($c->owner_id),
                'name' => $name,
                'company_type' => 'client',
                'website' => $this->clean($c->website, 255),
                'phone' => $this->clean($c->phone, 255),
                'email' => $this->clean($this->primaryContactEmail((int) $c->id), 255),
                'address_line1' => $this->clean($c->address, 255),
                'city' => $this->clean($c->city, 255),
                'state' => $this->clean($c->state, 10, true),
                'zip' => $this->clean($c->zip, 20, true),
                // country is NOT NULL(10) — keep short values, default the rest to US.
                'country' => $this->clean($c->country, 10, true) ?? 'US',
                'notes' => $this->composeNotes([
                    'VAT' => $c->vat_number ?? null,
                    'GST' => $c->gst_number ?? null,
                ], 'portal_client:'.$c->id),
            ]);
            $this->putMap('company', (int) $c->id, (int) $company->id, 'create');
            $created++;
        }

        $this->reportEntity('Companies', $rows->count(), $created, $matched, $skipped);
    }

    private function migrateContacts(): void
    {
        $rows = DB::connection('portal')->table('rise_users')
            ->where('user_type', 'client')->where('deleted', 0)->get();

        [$created, $matched, $skipped] = [0, 0, 0];
        foreach ($rows as $u) {
            $first = $this->clean($u->first_name, 255) ?? '';
            $last = $this->clean($u->last_name, 255) ?? '';
            $email = $this->clean($u->email, 255);
            if ($first === '' && $last === '' && ! $email) {
                $skipped++;
                continue;
            }

            $companyId = $this->getMap('company', (int) $u->client_id);

            $existing = Contact::where('organization_id', $this->org)
                ->where(function ($q) use ($email, $first, $last, $companyId) {
                    if ($email) {
                        $q->where('email', $email);
                    } else {
                        $q->where('first_name', $first)->where('last_name', $last)
                            ->where('company_id', $companyId);
                    }
                })->first();
            if ($existing) {
                $this->putMap('contact', (int) $u->id, (int) $existing->id, 'matched');
                $matched++;
                continue;
            }

            if ($this->dry) {
                $this->putMap('contact', (int) $u->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $contact = $this->makeRecord(Contact::class, [
                'organization_id' => $this->org,
                'created_by' => $this->fallbackOwnerId,
                'owner_id' => $this->fallbackOwnerId,
                'company_id' => $companyId,
                'first_name' => $first ?: ($email ?: 'Contact'),
                'last_name' => $last ?: '',
                'title' => $this->clean($u->job_title, 255),
                'email' => $email,
                'phone' => $this->clean($u->phone, 255),
                'mobile' => $this->clean($u->alternative_phone, 255),
                'is_key_contact' => (bool) $u->is_primary_contact,
                'notes' => $this->composeNotes([], 'portal_user:'.$u->id, $u->note ?? null),
            ]);
            $this->putMap('contact', (int) $u->id, (int) $contact->id, 'create');
            $created++;
        }

        $this->reportEntity('Contacts', $rows->count(), $created, $matched, $skipped);
    }

    private function migrateLeads(): void
    {
        $rows = DB::connection('portal')->table('rise_clients as c')
            ->leftJoin('rise_lead_source as s', 'c.lead_source_id', '=', 's.id')
            ->leftJoin('rise_lead_status as st', 'c.lead_status_id', '=', 'st.id')
            ->where('c.is_lead', 1)->where('c.deleted', 0)
            ->get(['c.*', 's.title as source_title', 'st.title as status_title']);

        [$created, $matched, $skipped] = [0, 0, 0];
        foreach ($rows as $l) {
            $name = trim((string) $l->company_name);
            if ($name === '') {
                $skipped++;
                continue;
            }
            $name = mb_substr($name, 0, 255);
            $statusTitle = $l->status_title ?: (string) $l->last_lead_status;

            $existing = Lead::where('organization_id', $this->org)
                ->where(function ($q) use ($l, $name) {
                    $q->where('notes', 'like', '%[[portal_lead:'.$l->id.']]%')
                        ->orWhereRaw('LOWER(company_name) = ?', [mb_strtolower($name)]);
                })->first();
            if ($existing) {
                $this->putMap('lead', (int) $l->id, (int) $existing->id, 'matched');
                $matched++;
                continue;
            }

            if ($this->dry) {
                $this->putMap('lead', (int) $l->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $lead = $this->makeRecord(Lead::class, [
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($l->created_by),
                'owner_id' => $this->ownerFor($l->owner_id),
                'company_name' => $name,
                'title' => $name,
                'email' => $this->clean($this->primaryContactEmail((int) $l->id), 255),
                'phone' => $this->clean($l->phone, 255),
                'source' => $this->clean($l->source_title, 255),
                'status' => $this->mapLeadStatus($statusTitle),
                'notes' => $this->composeNotes(
                    ['Portal status' => $statusTitle ?: null],
                    'portal_lead:'.$l->id,
                ),
            ]);
            $this->putMap('lead', (int) $l->id, (int) $lead->id, 'create');
            $created++;
        }

        $this->reportEntity('Leads', $rows->count(), $created, $matched, $skipped);
    }

    private function migrateProjects(): void
    {
        $rows = DB::connection('portal')->table('rise_projects as p')
            ->leftJoin('rise_project_status as ps', 'p.status_id', '=', 'ps.id')
            ->where('p.deleted', 0)->get(['p.*', 'ps.title as status_title']);

        [$created, $matched, $skipped] = [0, 0, 0];
        foreach ($rows as $p) {
            $name = $this->clean($p->title, 255) ?: ('Project '.$p->id);
            $companyId = $this->getMap('company', (int) $p->client_id);

            $existing = Project::where('organization_id', $this->org)
                ->where(function ($q) use ($p, $name, $companyId) {
                    $q->where('notes', 'like', '%[[portal_project:'.$p->id.']]%')
                        ->orWhere(fn ($w) => $w->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                            ->where('company_id', $companyId));
                })->first();
            if ($existing) {
                $this->putMap('project', (int) $p->id, (int) $existing->id, 'matched');
                $matched++;
                continue;
            }

            if ($this->dry) {
                $this->putMap('project', (int) $p->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $project = $this->makeRecord(Project::class, [
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($p->created_by),
                'owner_id' => $this->ownerFor($p->created_by),
                'company_id' => $companyId,
                'name' => $name,
                'status' => $this->mapProjectStatus($p->status_title ?: (string) $p->status),
                'description' => $this->clean($p->description, 60000),
                'start_date' => $this->date($p->start_date),
                'due_date' => $this->date($p->deadline),
                'budget' => $this->money($p->price),
                'created_via' => 'portal_import',
                'notes' => $this->composeNotes([], 'portal_project:'.$p->id),
            ]);
            $this->putMap('project', (int) $p->id, (int) $project->id, 'create');
            $created++;
        }

        $this->reportEntity('Projects', $rows->count(), $created, $matched, $skipped);
    }

    private function migrateInvoices(): void
    {
        $rows = DB::connection('portal')->table('rise_invoices')->where('deleted', 0)->get();

        [$created, $matched, $skipped, $items, $pays] = [0, 0, 0, 0, 0];
        foreach ($rows as $inv) {
            $existing = Invoice::where('organization_id', $this->org)
                ->where('notes', 'like', '%[[portal_invoice:'.$inv->id.']]%')->first();
            if ($existing) {
                $this->putMap('invoice', (int) $inv->id, (int) $existing->id, 'matched');
                $matched++;
                // Self-heal a partially-imported invoice (created, but items/payments
                // never landed because an earlier run errored). No deletes.
                if (! $this->dry && InvoiceItem::where('crm_invoice_id', $existing->id)->doesntExist()) {
                    $items += $this->copyItems('rise_invoice_items', 'invoice_id', (int) $inv->id, (int) $existing->id);
                }
                if (! $this->dry && Payment::where('crm_invoice_id', $existing->id)->doesntExist()) {
                    $pays += $this->copyPayments((int) $inv->id, (int) $existing->id);
                }
                continue;
            }

            $paid = (float) DB::connection('portal')->table('rise_invoice_payments')
                ->where('invoice_id', $inv->id)->where('deleted', 0)->sum('amount');
            $total = $this->money($inv->invoice_total);

            if ($this->dry) {
                $this->putMap('invoice', (int) $inv->id, $this->dryId(), 'create');
                $created++;
                $items += DB::connection('portal')->table('rise_invoice_items')->where('invoice_id', $inv->id)->where('deleted', 0)->count();
                $pays += DB::connection('portal')->table('rise_invoice_payments')->where('invoice_id', $inv->id)->where('deleted', 0)->count();
                continue;
            }

            $base = trim((string) $inv->display_id) ?: (($inv->number_year && $inv->number_sequence)
                ? $inv->number_year.'-'.str_pad((string) $inv->number_sequence, 4, '0', STR_PAD_LEFT)
                : 'INV-'.$inv->id);

            $invoice = $this->makeRecord(Invoice::class, [
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($inv->created_by),
                'owner_id' => $this->ownerFor($inv->created_by),
                'company_id' => $this->getMap('company', (int) $inv->client_id),
                'crm_project_id' => $this->getMap('project', (int) $inv->project_id),
                'number' => $this->uniqueInvoiceNumber($base),
                'kind' => 'invoice',
                'status' => $this->mapInvoiceStatus((string) $inv->status, $paid, $total),
                'issue_date' => $this->date($inv->bill_date),
                'due_date' => $this->date($inv->due_date),
                'subtotal' => $this->money($inv->invoice_subtotal),
                'tax_rate' => 0,
                'tax_amount' => $this->money($inv->tax) + $this->money($inv->tax2) + $this->money($inv->tax3),
                'discount_amount' => $this->money($inv->discount_total),
                'total' => $total,
                'amount_paid' => round($paid, 2),
                'currency' => $this->clientCurrency((int) $inv->client_id),
                'notes' => $this->composeNotes([], 'portal_invoice:'.$inv->id, $inv->note ?? null),
            ]);
            $this->putMap('invoice', (int) $inv->id, (int) $invoice->id, 'create');
            $created++;
            $items += $this->copyItems('rise_invoice_items', 'invoice_id', (int) $inv->id, (int) $invoice->id);
            $pays += $this->copyPayments((int) $inv->id, (int) $invoice->id);
        }

        $this->reportEntity('Invoices', $rows->count(), $created, $matched, $skipped);
        $this->line("                  + {$items} line items, {$pays} payments");
    }

    private function migrateEstimates(): void
    {
        $rows = DB::connection('portal')->table('rise_estimates')->where('deleted', 0)->get();

        [$created, $matched, $skipped, $items] = [0, 0, 0, 0];
        foreach ($rows as $est) {
            $existing = Invoice::where('organization_id', $this->org)
                ->where('notes', 'like', '%[[portal_estimate:'.$est->id.']]%')->first();
            if ($existing) {
                $this->putMap('estimate', (int) $est->id, (int) $existing->id, 'matched');
                $matched++;
                if (! $this->dry && InvoiceItem::where('crm_invoice_id', $existing->id)->doesntExist()) {
                    $items += $this->copyItems('rise_estimate_items', 'estimate_id', (int) $est->id, (int) $existing->id);
                }
                continue;
            }

            $subtotal = (float) DB::connection('portal')->table('rise_estimate_items')
                ->where('estimate_id', $est->id)->where('deleted', 0)->sum('total');
            $discount = ((string) $est->discount_amount_type === 'percentage')
                ? round($subtotal * $this->money($est->discount_amount) / 100, 2)
                : $this->money($est->discount_amount);
            $total = max(0, round($subtotal - $discount, 2));

            if ($this->dry) {
                $this->putMap('estimate', (int) $est->id, $this->dryId(), 'create');
                $created++;
                $items += DB::connection('portal')->table('rise_estimate_items')->where('estimate_id', $est->id)->where('deleted', 0)->count();
                continue;
            }

            $invoice = $this->makeRecord(Invoice::class, [
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($est->created_by),
                'owner_id' => $this->ownerFor($est->created_by),
                'company_id' => $this->getMap('company', (int) $est->client_id),
                'crm_project_id' => $this->getMap('project', (int) $est->project_id),
                'number' => $this->uniqueInvoiceNumber('EST-'.$est->id),
                'kind' => 'estimate',
                'status' => $this->mapEstimateStatus((string) $est->status),
                'issue_date' => $this->date($est->estimate_date),
                'due_date' => $this->date($est->valid_until),
                'subtotal' => round($subtotal, 2),
                'tax_rate' => 0,
                'tax_amount' => 0,
                'discount_amount' => $discount,
                'total' => $total,
                'amount_paid' => 0,
                'currency' => $this->clientCurrency((int) $est->client_id),
                'notes' => $this->composeNotes([], 'portal_estimate:'.$est->id, $est->note ?? null),
            ]);
            $this->putMap('estimate', (int) $est->id, (int) $invoice->id, 'create');
            $created++;
            $items += $this->copyItems('rise_estimate_items', 'estimate_id', (int) $est->id, (int) $invoice->id);
        }

        $this->reportEntity('Estimates', $rows->count(), $created, $matched, $skipped);
        $this->line("                  + {$items} line items");
    }

    private function migrateTickets(): void
    {
        $rows = DB::connection('portal')->table('rise_tickets')->where('deleted', 0)->get();
        $numbers = app(TicketNumberService::class);

        [$created, $matched, $skipped] = [0, 0, 0];
        foreach ($rows as $t) {
            $subject = $this->clean($t->title, 255) ?: ('Ticket '.$t->id);

            $existing = Ticket::where('organization_id', $this->org)
                ->where('description', 'like', '%[[portal_ticket:'.$t->id.']]%')->first();
            if ($existing) {
                $this->putMap('ticket', (int) $t->id, (int) $existing->id, 'matched');
                $matched++;
                continue;
            }
            if ($this->dry) {
                $this->putMap('ticket', (int) $t->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $status = $this->mapTicketStatus((string) $t->status);
            $ticket = $this->makeRecord(Ticket::class, [
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($t->created_by),
                'assigned_to' => $this->getMap('user', (int) $t->assigned_to),
                'company_id' => $this->getMap('company', (int) $t->client_id),
                'contact_id' => $this->getMap('contact', (int) $t->requested_by),
                'number' => $numbers->generate($this->org),
                'subject' => $subject,
                'description' => $this->composeNotes([], 'portal_ticket:'.$t->id),
                'type' => TicketType::Support,
                'status' => $status,
                'priority' => TicketPriority::Normal,
                'opened_at' => $this->date($t->created_at),
                'closed_at' => $status === TicketStatus::Closed ? $this->date($t->closed_at) : null,
            ]);
            $this->putMap('ticket', (int) $t->id, (int) $ticket->id, 'create');
            $created++;
        }

        $this->reportEntity('Tickets', $rows->count(), $created, $matched, $skipped);
    }

    private function migrateExpenses(): void
    {
        $rows = DB::connection('portal')->table('rise_expenses')->where('deleted', 0)->get();
        $numbers = app(ExpenseNumberService::class);

        [$created, $matched, $skipped] = [0, 0, 0];
        foreach ($rows as $e) {
            $existing = Expense::where('organization_id', $this->org)
                ->where('notes', 'like', '%[[portal_expense:'.$e->id.']]%')->first();
            if ($existing) {
                $this->putMap('expense', (int) $e->id, (int) $existing->id, 'matched');
                $matched++;
                continue;
            }
            if ($this->dry) {
                $this->putMap('expense', (int) $e->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $expense = $this->makeRecord(Expense::class, [
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($e->created_by),
                'owner_id' => $this->ownerFor($e->user_id ?: $e->created_by),
                'expense_category_id' => $this->expenseCategoryId($e->category_id ? (int) $e->category_id : null),
                'company_id' => $this->getMap('company', (int) $e->client_id),
                'crm_project_id' => $this->getMap('project', (int) $e->project_id),
                'number' => $numbers->generate($this->org),
                'description' => $this->clean($e->description ?: $e->title, 255) ?: 'Expense',
                'amount' => $this->money($e->amount),
                'currency' => 'USD',
                'status' => ExpenseStatus::Approved,
                'expense_date' => $this->date($e->expense_date) ?? now()->toDateString(),
                'notes' => $this->composeNotes([], 'portal_expense:'.$e->id, $e->title ?? null),
            ]);
            $this->putMap('expense', (int) $e->id, (int) $expense->id, 'create');
            $created++;
        }

        $this->reportEntity('Expenses', $rows->count(), $created, $matched, $skipped);
    }

    private function migrateNotes(): void
    {
        $rows = DB::connection('portal')->table('rise_notes')->where('deleted', 0)->get();

        [$created, $matched, $skipped] = [0, 0, 0];
        foreach ($rows as $n) {
            // Attach to the project if there is one, else the client company.
            [$subjectType, $subjectId] = match (true) {
                (bool) $this->getMap('project', (int) $n->project_id) => [Project::class, $this->getMap('project', (int) $n->project_id)],
                (bool) $this->getMap('company', (int) $n->client_id) => [Company::class, $this->getMap('company', (int) $n->client_id)],
                default => [null, null],
            };
            if (! $subjectType) {
                $skipped++;
                continue;
            }

            $existing = Activity::where('organization_id', $this->org)
                ->where('subject_type', $subjectType)->where('subject_id', $subjectId)
                ->where('body', 'like', '%[[portal_note:'.$n->id.']]%')->first();
            if ($existing) {
                $matched++;
                continue;
            }
            if ($this->dry) {
                $created++;
                continue;
            }

            $text = trim(strip_tags((string) ($n->title ?? '')."\n".(string) ($n->description ?? '')));
            $this->makeRecord(Activity::class, [
                'organization_id' => $this->org,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'user_id' => $this->ownerFor($n->created_by),
                'type' => 'note',
                'body' => mb_substr($text, 0, 60000)."\n[[portal_note:{$n->id}]]",
                'happened_at' => $this->date($n->created_at),
            ]);
            $created++;
        }

        $this->reportEntity('Notes', $rows->count(), $created, $matched, $skipped);
    }

    /** Find or create our expense category matching a RISE category, by name. */
    private function expenseCategoryId(?int $riseCatId): ?int
    {
        if (! $riseCatId) {
            return null;
        }
        $name = DB::connection('portal')->table('rise_expense_categories')->where('id', $riseCatId)->value('title');
        if (! $name) {
            return null;
        }
        if ($this->dry) {
            return $this->dryId();
        }

        $cat = ExpenseCategory::firstOrCreate(
            ['organization_id' => $this->org, 'name' => mb_substr((string) $name, 0, 255)],
            ['created_by' => $this->fallbackOwnerId, 'currency' => 'USD', 'is_active' => true],
        );

        return (int) $cat->id;
    }

    /** Copy line items from a RISE items table onto our invoice. Returns count. */
    private function copyItems(string $table, string $fk, int $sourceId, int $invoiceId): int
    {
        $lines = DB::connection('portal')->table($table)
            ->where($fk, $sourceId)->where('deleted', 0)->orderBy('sort')->get();
        $n = 0;
        foreach ($lines as $li) {
            $title = trim(strip_tags((string) ($li->title ?? '')));
            $body = trim(strip_tags((string) ($li->description ?? '')));
            $desc = trim($title.($body !== '' ? ' — '.$body : ''), " —\t\n");
            InvoiceItem::withoutEvents(fn () => InvoiceItem::create([
                'crm_invoice_id' => $invoiceId,
                'description' => mb_substr($desc !== '' ? $desc : 'Item', 0, 255),
                'quantity' => $this->money($li->quantity),
                'unit_price' => $this->money($li->rate),
                'amount' => $this->money($li->total),
                'position' => (int) $li->sort,
            ]));
            $n++;
        }

        return $n;
    }

    private function copyPayments(int $sourceInvoiceId, int $invoiceId): int
    {
        $pays = DB::connection('portal')->table('rise_invoice_payments')
            ->where('invoice_id', $sourceInvoiceId)->where('deleted', 0)->get();
        $n = 0;
        foreach ($pays as $pay) {
            $this->makeRecord(Payment::class, [
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($pay->created_by),
                'crm_invoice_id' => $invoiceId,
                'amount' => $this->money($pay->amount),
                'paid_at' => $this->date($pay->payment_date) ?? now()->toDateString(),
                'method' => $this->paymentMethod((int) $pay->payment_method_id),
                'reference' => $this->clean($pay->transaction_id, 255),
                'status' => 'completed',
                'notes' => $this->clean($pay->note, 60000),
            ]);
            $n++;
        }

        return $n;
    }

    // ---- helpers -----------------------------------------------------------

    /** Map a RISE lead-status title onto our LeadStatus enum by keyword. */
    private function mapLeadStatus(?string $title): LeadStatus
    {
        $t = mb_strtolower((string) $title);

        return match (true) {
            $t === '' => LeadStatus::New,
            str_contains($t, 'won') || str_contains($t, 'paid') || str_contains($t, 'payment') => LeadStatus::Won,
            str_contains($t, 'lost') || str_contains($t, 'dead') || str_contains($t, 'zombie') => LeadStatus::Lost,
            str_contains($t, 'quote') || str_contains($t, 'bid sent') || str_contains($t, 'proposal') => LeadStatus::Proposal,
            str_contains($t, 'progress') || str_contains($t, 'seeking') || str_contains($t, 'potential') || str_contains($t, 'quali') => LeadStatus::Qualified,
            str_contains($t, 'follow') || str_contains($t, 'remind') => LeadStatus::Contacted,
            str_contains($t, 'new') => LeadStatus::New,
            default => LeadStatus::New,
        };
    }

    private function mapProjectStatus(?string $title): ProjectStatus
    {
        $t = mb_strtolower((string) $title);

        return match (true) {
            str_contains($t, 'complete') => ProjectStatus::Completed,
            str_contains($t, 'cancel') => ProjectStatus::Cancelled,
            str_contains($t, 'hold') => ProjectStatus::OnHold,
            str_contains($t, 'open') => ProjectStatus::InProgress,
            default => ProjectStatus::New,
        };
    }

    /** Map RISE invoice status onto ours, reconciled with actual payments. */
    private function mapInvoiceStatus(string $raw, float $paid, float $total): InvoiceStatus
    {
        if ($total > 0 && $paid >= $total - 0.01) {
            return InvoiceStatus::Paid;
        }
        if ($paid > 0) {
            return InvoiceStatus::PartiallyPaid;
        }

        return match (mb_strtolower($raw)) {
            'draft' => InvoiceStatus::Draft,
            'cancelled', 'canceled', 'credited' => InvoiceStatus::Void,
            default => InvoiceStatus::Sent,
        };
    }

    private function mapTicketStatus(string $raw): TicketStatus
    {
        return match (mb_strtolower($raw)) {
            'new' => TicketStatus::New,
            'closed' => TicketStatus::Closed,
            'client_replied', 'open' => TicketStatus::Open,
            default => TicketStatus::Open,
        };
    }

    private function mapEstimateStatus(string $raw): InvoiceStatus
    {
        return match (mb_strtolower($raw)) {
            'sent' => InvoiceStatus::Sent,
            'accepted' => InvoiceStatus::Accepted,
            'declined' => InvoiceStatus::Declined,
            default => InvoiceStatus::Draft,
        };
    }

    /** A per-org unique invoice number: the base, or base-2, base-3, … on collision. */
    private function uniqueInvoiceNumber(string $base): string
    {
        $base = mb_substr(trim($base) ?: 'INV', 0, 40);
        $number = $base;
        $i = 1;
        while (Invoice::withTrashed()->where('organization_id', $this->org)->where('number', $number)->exists()) {
            $number = mb_substr($base, 0, 36).'-'.(++$i);
        }

        return $number;
    }

    /** Normalize a RISE date; RISE uses '0000-00-00' / '' for "none". */
    private function date($value): ?string
    {
        $s = trim((string) ($value ?? ''));
        if ($s === '' || str_starts_with($s, '0000') || str_starts_with($s, '1970-01-01')) {
            return null;
        }

        return $s;
    }

    private function money($value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    private array $paymentMethodCache = [];

    private function paymentMethod(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }
        if (! array_key_exists($id, $this->paymentMethodCache)) {
            $this->paymentMethodCache[$id] = DB::connection('portal')->table('rise_payment_methods')
                ->where('id', $id)->value('title');
        }

        return $this->paymentMethodCache[$id] ? mb_substr((string) $this->paymentMethodCache[$id], 0, 255) : null;
    }

    private array $currencyCache = [];

    private function clientCurrency(int $clientId): string
    {
        if (! array_key_exists($clientId, $this->currencyCache)) {
            $cur = DB::connection('portal')->table('rise_clients')->where('id', $clientId)->value('currency');
            $this->currencyCache[$clientId] = ($cur && strlen(trim((string) $cur)) === 3) ? strtoupper(trim((string) $cur)) : 'USD';
        }

        return $this->currencyCache[$clientId];
    }

    /** Resolve a RISE staff id to our user id, falling back to the default owner. */
    private function ownerFor($portalUserId): int
    {
        return $this->getMap('user', (int) $portalUserId) ?? $this->fallbackOwnerId;
    }

    private function primaryContactEmail(int $portalClientId): ?string
    {
        $email = DB::connection('portal')->table('rise_users')
            ->where('client_id', $portalClientId)->where('deleted', 0)
            ->orderByDesc('is_primary_contact')->orderBy('id')
            ->value('email');

        return $email ? trim((string) $email) : null;
    }

    /** @param array<string,?string> $labels */
    private function composeNotes(array $labels, string $marker, ?string $prepend = null): string
    {
        $parts = [];
        if ($prepend && trim($prepend) !== '') {
            $parts[] = trim($prepend);
        }
        foreach ($labels as $k => $v) {
            if ($v !== null && trim((string) $v) !== '') {
                $parts[] = "{$k}: ".trim((string) $v);
            }
        }
        $parts[] = "[[{$marker}]]";

        return implode("\n", $parts);
    }

    /**
     * Trim a value to fit a target column. With $nullIfOver, a value that would
     * overflow is treated as misplaced source data and dropped (returns null)
     * rather than truncated to garbage — used for tight fields like state/zip.
     */
    private function clean($value, int $max, bool $nullIfOver = false): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return null;
        }
        if (mb_strlen($v) > $max) {
            return $nullIfOver ? null : mb_substr($v, 0, $max);
        }

        return $v;
    }

    private function ensureMapTable(): void
    {
        DB::connection('portal')->statement(
            'CREATE TABLE IF NOT EXISTS migration_map ('
            .'entity VARCHAR(40) NOT NULL, source_id INT NOT NULL, target_id BIGINT UNSIGNED NULL, '
            .'action VARCHAR(20) NULL, created_at DATETIME NULL, PRIMARY KEY(entity, source_id))'
        );
    }

    private function getMap(string $entity, ?int $srcId): ?int
    {
        if (! $srcId) {
            return null;
        }
        if (isset($this->mem[$entity][$srcId])) {
            return $this->mem[$entity][$srcId];
        }
        $v = DB::connection('portal')->table('migration_map')
            ->where('entity', $entity)->where('source_id', $srcId)->value('target_id');
        if ($v !== null) {
            return $this->mem[$entity][$srcId] = (int) $v;
        }

        return null;
    }

    private function putMap(string $entity, int $srcId, int $targetId, string $action): void
    {
        $this->mem[$entity][$srcId] = $targetId;
        if (! $this->dry) {
            DB::connection('portal')->table('migration_map')->updateOrInsert(
                ['entity' => $entity, 'source_id' => $srcId],
                ['target_id' => $targetId, 'action' => $action, 'created_at' => now()],
            );
        }
    }

    /** A placeholder target id for dry runs, so dependent FK lookups resolve non-null. */
    private function dryId(): int
    {
        return --$this->dryCounter;
    }

    private function reportEntity(string $label, int $total, int $created, int $matched, int $skipped): void
    {
        $verb = $this->dry ? 'would create' : 'created';
        $this->line(sprintf(
            '  %-14s source=%-5d %s=%-5d matched(skip)=%-5d empty(skip)=%-5d',
            $label, $total, $verb, $created, $matched, $skipped,
        ));
    }
}
