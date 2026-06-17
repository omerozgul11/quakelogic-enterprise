<?php

namespace App\Services\Proposals;

use App\Models\Agency;
use App\Models\AiAnalysis;
use App\Models\Company;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\ProposalFile;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Jobs\EnrichProposalOrgsJob;
use App\Services\Ai\AiProviderInterface;
use App\Services\Documents\DocumentTextExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an uploaded proposal document into structured data. Extraction is
 * staged (not auto-applied): the user reviews each proposed field and record on
 * the review screen, then apply() commits only the accepted choices.
 */
class ProposalIntakeService
{
    /** Max file size to send to a vision model (request limit ~20 MB). */
    private const MAX_VISION_BYTES = 20000000;

    /** extracted-key => [label, proposal column, type] */
    private const FIELDS = [
        'project_name'        => ['Project name', 'project_name', 'text'],
        'solicitation_number' => ['Solicitation #', 'solicitation_number', 'text'],
        'value'               => ['Proposal value', 'proposal_value', 'money'],
        'due_date'            => ['Due date', 'due_date', 'date'],
        'scope'               => ['Scope summary', 'scope_summary', 'text'],
    ];

    public function __construct(
        private readonly AiProviderInterface $ai,
        private readonly DocumentTextExtractionService $textExtractor,
        private readonly ProposalNumberService $numberService,
    ) {}

    /**
     * Create an empty draft proposal to attach a dropped document to.
     */
    public function createDraft(UploadedFile $file, User $user): ProposalSubmission
    {
        return DB::transaction(function () use ($user, $file) {
            $proposal = ProposalSubmission::create([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'owner_id' => $user->id,
                'proposal_number' => $this->numberService->generate($user->organization_id),
                'project_name' => $this->defaultName($file),
                'status' => 'in_progress',
            ]);

            $proposal->statusHistory()->create([
                'changed_by' => $user->id,
                'from_status' => null,
                'to_status' => 'in_progress',
                'changed_at' => now(),
            ]);

            return $proposal;
        });
    }

    /**
     * Store the file, extract its text, run AI extraction and record the result
     * as an AiAnalysis. Nothing is applied to the proposal here.
     *
     * @return AiAnalysis|null  the analysis when readable text was found, else null
     */
    public function extract(ProposalSubmission $proposal, UploadedFile $file, User $user): ?AiAnalysis
    {
        $proposalFile = $this->storeFile($proposal, $file, $user);
        $mime = (string) $proposalFile->mime_type;

        $extracted = null;
        $region = null;

        // Prefer native vision: send the ORIGINAL document so the model reads the
        // whole thing — cover/title page, SF-1449 or cover-form fields, signature
        // block — which captures the client/company name and details that
        // pdftotext + front-matter focusing miss. Works on scanned/image PDFs too.
        if ($this->ai->supportsVision() && $this->isVisionMime($mime) && (int) ($proposalFile->size ?? 0) <= self::MAX_VISION_BYTES) {
            try {
                $bytes = Storage::disk($proposalFile->disk ?: 'local')->get($proposalFile->path);
                if ($bytes !== null && $bytes !== '') {
                    $vision = $this->ai->extractDocumentVision(base64_encode($bytes), $mime);
                    if ($this->hasUsefulFields($vision)) {
                        $extracted = $vision;
                        $region = 'vision';
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Proposal intake vision extraction failed', ['file' => $proposalFile->id, 'error' => $e->getMessage()]);
            }
        }

        // Fall back to text extraction (front-matter focused) when vision is
        // unavailable or returned nothing useful.
        if ($extracted === null) {
            try {
                $text = $this->textExtractor->extract($proposalFile->path, $mime);
            } catch (\Throwable $e) {
                Log::warning('Proposal intake text extraction failed', ['file' => $proposalFile->id, 'error' => $e->getMessage()]);
                $text = '';
            }

            if (trim($text) === '') {
                return null;
            }

            // Drop References / Bibliography so citation authors aren't extracted as contacts.
            $text = $this->textExtractor->stripReferenceSections($text);
            $focus = $this->textExtractor->frontMatter($text);
            $region = $focus !== $text ? 'front_matter' : 'full_document';

            try {
                $extracted = $this->ai->extractDocumentData($focus, array_keys(self::FIELDS));
            } catch (\Throwable $e) {
                Log::error('Proposal intake AI extraction failed', ['file' => $proposalFile->id, 'error' => $e->getMessage()]);
                return null;
            }
        }

        $proposalFile->update(['status' => 'parsed']);

        return AiAnalysis::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'subject_type' => ProposalSubmission::class,
            'subject_id' => $proposal->id,
            'analysis_type' => 'document_extraction',
            'ai_provider' => $extracted['_provider'] ?? $this->ai->getName(),
            'status' => 'needs_review',
            'context_data' => [
                'file' => $proposalFile->display_name,
                'characters' => $extracted['_chars'] ?? null,
                'source_region' => $region,
            ],
            'output' => $extracted,
        ]);
    }

    /** Mime types we can hand to a vision model directly. */
    private function isVisionMime(string $mime): bool
    {
        return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
    }

    /** True when an extraction result actually found something worth using. */
    private function hasUsefulFields(array $data): bool
    {
        foreach (['project_name', 'company', 'agency', 'solicitation_number', 'scope'] as $k) {
            if (! empty($data[$k])) {
                return true;
            }
        }

        return ! empty($data['contacts']);
    }

    /**
     * Extract from several uploaded files at once and return the analysis from
     * whichever document yielded the richest data. Every file is still stored
     * and analysed (so all are attached to the proposal); only the winner is
     * used to fill the proposal fields.
     *
     * @param  array<int,UploadedFile>  $files
     */
    public function extractBest(ProposalSubmission $proposal, array $files, User $user): ?AiAnalysis
    {
        $best = null;
        $bestScore = -1;

        foreach ($files as $file) {
            $analysis = $this->extract($proposal, $file, $user);
            if (!$analysis) {
                continue;
            }
            $score = $this->scoreExtraction($analysis->output ?? []);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $analysis;
            }
        }

        return $best;
    }

    /**
     * Rough "how complete is this extraction" score, used to auto-pick the
     * document that best describes the proposal when several are uploaded.
     *
     * @param  array<string,mixed>  $output
     */
    private function scoreExtraction(array $output): int
    {
        $score = 0;
        foreach (['project_name', 'solicitation_number', 'agency', 'company', 'due_date', 'value', 'scope', 'naics', 'set_aside'] as $field) {
            if (!empty($output[$field])) {
                $score += 2;
            }
        }
        $score += min(5, count($output['contacts'] ?? []));
        $score += (int) round(((float) ($output['_extraction_confidence'] ?? 0)) * 10);

        return $score;
    }

    /**
     * Build the review structure (extracted value vs current value) for the UI.
     */
    public function proposedChanges(ProposalSubmission $proposal, array $extracted): array
    {
        $fields = [];
        foreach (self::FIELDS as $key => [$label, $column, $type]) {
            if (!isset($extracted[$key]) || $extracted[$key] === '' || $extracted[$key] === null) {
                continue;
            }
            $current = $proposal->{$column};
            $fields[] = [
                'key' => $key,
                'label' => $label,
                'current' => $this->display($current, $type),
                'extracted' => $this->display($extracted[$key], $type),
                'changed' => $this->display($current, $type) !== $this->display($extracted[$key], $type),
            ];
        }

        $records = [];
        if (!empty($extracted['agency'])) {
            $records[] = $this->recordRow('agency', 'Agency', $extracted['agency'], Agency::class, $proposal->organization_id);
        }
        if (!empty($extracted['company'])) {
            $records[] = $this->recordRow('company', 'Company', $extracted['company'], Company::class, $proposal->organization_id);
        }
        if (!empty($extracted['contact_person']) || !empty($extracted['email'])) {
            $name = trim(($extracted['contact_person'] ?? '') . (isset($extracted['email']) ? ' · ' . $extracted['email'] : ''));
            $exists = !empty($extracted['email']) && Contact::where('organization_id', $proposal->organization_id)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($extracted['email'])])->exists();
            $records[] = ['key' => 'contact', 'label' => 'Contact', 'value' => $name, 'action' => $exists ? 'link' : 'create'];
        }
        $records[] = ['key' => 'follow_up', 'label' => 'Follow-up reminder', 'value' => 'Review extracted proposal (due tomorrow)', 'action' => 'create'];

        return ['fields' => $fields, 'records' => $records];
    }

    /**
     * Commit the accepted fields and records. Values are read from the stored
     * analysis (never trusted from the client).
     *
     * @param  array{fields?: array<int,string>, agency?: bool, company?: bool, contact?: bool, follow_up?: bool}  $accept
     */
    public function apply(ProposalSubmission $proposal, AiAnalysis $analysis, array $accept, User $user): array
    {
        $extracted = $analysis->output ?? [];
        $acceptFields = $accept['fields'] ?? [];
        $summary = ['fields' => [], 'created' => [], 'linked' => []];

        DB::transaction(function () use ($proposal, $analysis, $extracted, $accept, $acceptFields, $user, &$summary) {
            $updates = [];
            foreach (self::FIELDS as $key => [$label, $column, $type]) {
                if (!in_array($key, $acceptFields, true) || !isset($extracted[$key]) || $extracted[$key] === '') {
                    continue;
                }
                $updates[$column] = $this->coerce($extracted[$key], $type, $column);
                $summary['fields'][] = $label;
            }
            if (array_key_exists('scope_summary', $updates) && !$proposal->description) {
                $updates['description'] = $updates['scope_summary'];
            }

            // Companies only: the buyer/client organization (whether the document
            // calls it an agency or a company) is recorded as a Company.
            $company = !empty($accept['company']) ? $this->resolveCompany($extracted, $user, $summary) : null;
            if ($company) {
                $updates['company_id'] = $company->id;
            }
            if ($updates) {
                $proposal->update($updates);
            }

            // Create a contact for every person QuakeAI found, linked to the company.
            $contact = !empty($accept['contact'])
                ? $this->applyContacts($extracted, $user, $company ?? $proposal->company, $summary)
                : null;

            if (!empty($accept['follow_up'])) {
                $this->createFollowUp($proposal, $contact, $user, $summary);
            }

            $analysis->update(['status' => 'completed', 'human_decision' => 'accepted', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        });

        return $summary;
    }

    /**
     * Apply everything QuakeAI extracted automatically: fill the proposal fields,
     * create/link the agency, company and contact, set the proposal manager to the
     * uploader, and generate notes (key dates, requests, specs).
     *
     * @param  bool  $fillBlanksOnly  when true, never overwrite values already set
     */
    public function autoApply(ProposalSubmission $proposal, AiAnalysis $analysis, User $user, bool $fillBlanksOnly = false): array
    {
        $extracted = $analysis->output ?? [];

        $fields = [];
        foreach (self::FIELDS as $key => [$label, $column, $type]) {
            if (!isset($extracted[$key]) || $extracted[$key] === '') {
                continue;
            }
            if ($fillBlanksOnly && !blank($proposal->{$column})) {
                continue;
            }
            $fields[] = $key;
        }

        $hasOrg = !empty($extracted['company']) || !empty($extracted['agency']);
        $accept = [
            'fields' => $fields,
            'company' => $hasOrg && (!$fillBlanksOnly || blank($proposal->company_id)),
            'contact' => !empty($extracted['contacts']) || !empty($extracted['contact_person']) || !empty($extracted['email']),
            'follow_up' => true,
        ];

        $summary = $this->apply($proposal, $analysis, $accept, $user);

        $notes = $this->generateNotes($extracted);
        $proposal->forceFill([
            'proposal_manager_id' => $proposal->proposal_manager_id ?: $user->id,
            'notes' => ($fillBlanksOnly && !blank($proposal->notes)) ? $proposal->notes : $notes,
        ])->save();

        $summary['manager_set'] = true;
        $summary['notes_generated'] = $notes !== '';

        // On a fresh intake, research the client company + agency on the web in
        // the background and save a factual background to their records (which
        // then feeds the writer + RAG). Best-effort; no-ops without web research.
        if (! $fillBlanksOnly && ($proposal->company_id || $proposal->agency_id)) {
            EnrichProposalOrgsJob::dispatch($proposal->id);
        }

        return $summary;
    }

    /**
     * Compose human-readable notes from the extracted facts: summary, key dates,
     * and key requirements/specs.
     */
    private function generateNotes(array $e): string
    {
        $lines = ['Auto-generated by QuakeAI from the uploaded document.'];

        $facts = [];
        if (!empty($e['solicitation_number'])) $facts[] = 'Solicitation #: ' . $e['solicitation_number'];
        if (!empty($e['agency'])) $facts[] = 'Agency: ' . $e['agency'];
        if (!empty($e['naics'])) $facts[] = 'NAICS: ' . $e['naics'];
        if (!empty($e['set_aside'])) $facts[] = 'Set-aside: ' . $e['set_aside'];
        if (isset($e['value']) && is_numeric($e['value'])) $facts[] = 'Estimated value: $' . number_format((float) $e['value']);
        if ($facts) {
            $lines[] = '';
            $lines[] = 'Summary:';
            foreach ($facts as $f) $lines[] = ' • ' . $f;
        }

        $dates = [];
        if (!empty($e['due_date'])) $dates[] = 'Proposal due: ' . $e['due_date'];
        foreach (($e['key_dates'] ?? []) as $label => $d) {
            if ($d === ($e['due_date'] ?? null)) continue; // already covered by "Proposal due"
            $dates[] = $label . ': ' . $d;
        }
        if ($dates) {
            $lines[] = '';
            $lines[] = 'Key dates:';
            foreach ($dates as $d) $lines[] = ' • ' . $d;
        }

        if (!empty($e['requirements'])) {
            $lines[] = '';
            $lines[] = 'Key requirements & specs:';
            foreach ($e['requirements'] as $r) $lines[] = ' • ' . $r;
        }

        return trim(implode("\n", $lines));
    }

    private function recordRow(string $key, string $label, string $value, string $model, int $orgId): array
    {
        $exists = $model::where('organization_id', $orgId)->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->exists();
        return ['key' => $key, 'label' => $label, 'value' => $value, 'action' => $exists ? 'link' : 'create'];
    }

    private function display(mixed $value, string $type): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return match ($type) {
            'money' => '$' . number_format((float) (is_string($value) ? str_replace([',', '$'], '', $value) : $value)),
            'date' => $this->toDate((string) $value) ?? (string) $value,
            default => Str::limit(trim((string) $value), 300),
        };
    }

    private function coerce(mixed $value, string $type, string $column): mixed
    {
        return match ($type) {
            'money' => (float) (is_string($value) ? str_replace([',', '$'], '', $value) : $value),
            'date' => $this->toDate((string) $value),
            default => $column === 'project_name'
                ? Str::limit(trim((string) $value), 490, '')
                : ($column === 'solicitation_number' ? Str::limit(trim((string) $value), 95, '') : trim((string) $value)),
        };
    }

    private function toDate(string $raw): ?string
    {
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function defaultName(UploadedFile $file): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = trim(str_replace(['_', '-'], ' ', $base));
        return Str::limit($base !== '' ? Str::title($base) : 'Untitled Proposal', 250, '');
    }

    private function storeFile(ProposalSubmission $proposal, UploadedFile $file, User $user): ProposalFile
    {
        $storedName = Str::ulid() . '.' . ($file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs("proposals/{$proposal->id}", $storedName, 'local');

        return ProposalFile::create([
            'ulid' => (string) Str::ulid(),
            'proposal_submission_id' => $proposal->id,
            'uploaded_by' => $user->id,
            'display_name' => $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'document_type' => 'proposal',
            'status' => 'uploaded',
            'version' => 1,
            'is_current_version' => true,
        ]);
    }

    private function resolveAgency(array $extracted, User $user, array &$summary): ?Agency
    {
        $name = trim((string) ($extracted['agency'] ?? ''));
        if ($name === '') {
            return null;
        }

        $existing = Agency::where('organization_id', $user->organization_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            $summary['linked'][] = "Agency: {$existing->name}";
            return $existing;
        }

        $agency = Agency::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'name' => Str::limit($name, 250, ''),
            'email' => $this->isGovEmail($extracted['email'] ?? null) ? $extracted['email'] : null,
            'phone' => $extracted['phone'] ?? null,
        ]);
        $summary['created'][] = "Agency: {$agency->name}";

        return $agency;
    }

    private function resolveCompany(array $extracted, User $user, array &$summary): ?Company
    {
        // The organization we're doing business with — whether the document labels
        // it a company or a government agency, it lives under Companies.
        $name = trim((string) ($extracted['company'] ?? $extracted['agency'] ?? ''));
        if ($name === '') {
            return null;
        }

        $existing = Company::where('organization_id', $user->organization_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            // Backfill phone/email if we now have them and the record lacked them.
            $existing->fill(array_filter([
                'phone' => $existing->phone ?: ($extracted['phone'] ?? null),
                'email' => $existing->email ?: ($extracted['email'] ?? null),
            ]))->save();
            $summary['linked'][] = "Company: {$existing->name}";
            return $existing;
        }

        $company = Company::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'name' => Str::limit($name, 250, ''),
            'phone' => $extracted['phone'] ?? null,
            'email' => $extracted['email'] ?? null,
        ]);
        $summary['created'][] = "Company: {$company->name}";

        return $company;
    }

    /**
     * Create a Contact record for every person QuakeAI extracted (each with its
     * own phone/email/title), linked to the company. Returns the first one.
     */
    private function applyContacts(array $extracted, User $user, ?Company $company, array &$summary): ?Contact
    {
        $list = $extracted['contacts'] ?? [];

        // Back-compat: fall back to the single primary-contact fields.
        if (empty($list) && (!empty($extracted['contact_person']) || !empty($extracted['email']))) {
            $list = [[
                'name' => $extracted['contact_person'] ?? null,
                'email' => $extracted['email'] ?? null,
                'phone' => $extracted['phone'] ?? null,
                'title' => $extracted['contact_title'] ?? null,
            ]];
        }

        $first = null;
        foreach ($list as $c) {
            $contact = $this->createContactRecord(
                $c['name'] ?? null, $c['email'] ?? null, $c['phone'] ?? null, $c['title'] ?? null,
                $company, $user, $summary
            );
            if ($contact && !$first) {
                $first = $contact;
            }
        }

        return $first;
    }

    private function createContactRecord(?string $name, ?string $email, ?string $phone, ?string $title, ?Company $company, User $user, array &$summary): ?Contact
    {
        $email = trim((string) $email);
        $name = trim((string) $name);
        $phone = trim((string) $phone);
        // A contact must be reachable. Without an email or phone it is almost
        // always noise extracted from headings/specs, not a real person — skip it.
        if ($email === '' && $phone === '') {
            return null;
        }

        $existing = $email !== ''
            ? Contact::where('organization_id', $user->organization_id)->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first()
            : Contact::where('organization_id', $user->organization_id)
                ->whereRaw("LOWER(CONCAT(first_name,' ',last_name)) = ?", [mb_strtolower($name)])->first();

        if ($existing) {
            $existing->fill(array_filter([
                'company_id' => $existing->company_id ?: $company?->id,
                'phone' => $existing->phone ?: ($phone ?: null),
                'email' => $existing->email ?: ($email !== '' ? $email : null),
                'title' => $existing->title ?: ($title ?: null),
            ]))->save();
            $summary['linked'][] = "Contact: {$existing->first_name} {$existing->last_name}";
            return $existing;
        }

        [$first, $last] = $this->splitName($name, $email);

        $contact = Contact::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'company_id' => $company?->id,
            'first_name' => $first,
            'last_name' => $last,
            'title' => $title ?: null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone ?: null,
            'is_key_contact' => true,
        ]);
        $summary['created'][] = "Contact: {$contact->first_name} {$contact->last_name}";

        return $contact;
    }

    private function createFollowUp(ProposalSubmission $proposal, ?Contact $contact, User $user, array &$summary): void
    {
        // One review follow-up per proposal — not one per contact. Re-extracting
        // or re-uploading a document must not pile on duplicate reminders.
        $exists = FollowUp::where('proposal_submission_id', $proposal->id)
            ->where('type', 'review')
            ->where('is_automated', true)
            ->exists();
        if ($exists) {
            return;
        }

        FollowUp::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'proposal_submission_id' => $proposal->id,
            'contact_id' => $contact?->id,
            'type' => 'review',
            'status' => 'scheduled',
            'subject' => 'Review extracted proposal: ' . Str::limit($proposal->project_name, 120),
            'message' => 'A proposal document was uploaded and parsed. Confirm the extracted details and linked records are accurate.',
            'scheduled_date' => now()->addDay(),
            'is_automated' => true,
        ]);
        $summary['created'][] = 'Follow-up reminder';
    }

    private function splitName(string $person, string $email): array
    {
        $person = trim(preg_replace('/^(Mr\.?|Ms\.?|Mrs\.?|Dr\.?|Mx\.?)\s+/i', '', trim($person)) ?? '');
        if ($person !== '') {
            $parts = preg_split('/\s+/', $person) ?: [];
            $first = array_shift($parts) ?: $person;
            $last = $parts ? implode(' ', $parts) : $first;
            return [Str::limit($first, 95, ''), Str::limit($last, 95, '')];
        }

        if ($email !== '' && preg_match('/^([^@]+)@/', $email, $m)) {
            $local = str_replace(['.', '_', '-'], ' ', $m[1]);
            $parts = preg_split('/\s+/', trim($local)) ?: [];
            $first = ucfirst($parts[0] ?? 'Unknown');
            $last = isset($parts[1]) ? ucfirst(implode(' ', array_slice($parts, 1))) : 'Contact';
            return [$first, $last];
        }

        return ['Unknown', 'Contact'];
    }

    private function isGovEmail(?string $email): bool
    {
        return $email !== null && (bool) preg_match('/\.(gov|mil)$/i', $email);
    }
}
