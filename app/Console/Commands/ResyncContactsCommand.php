<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\ProposalFile;
use App\Models\ProposalSubmission;
use App\Services\Ai\AiProviderInterface;
use App\Services\Documents\DocumentTextExtractionService;
use Illuminate\Console\Command;

/**
 * Re-reads every proposal's stored document (with References/Bibliography
 * sections removed) and (a) corrects existing contacts whose names were
 * mis-extracted, (b) creates any real contacts that were missed, and
 * (c) soft-deletes obvious junk contacts harvested from spec text or citations.
 */
class ResyncContactsCommand extends Command
{
    protected $signature = 'contacts:resync {--dry-run : Show changes without saving} {--org= : Limit to one organization id} {--no-clean : Skip junk-contact removal}';

    protected $description = 'Re-extract proposal documents and correct/clean contact records.';

    /** Spec / citation vocabulary that should never be a person name. */
    private const JUNK_WORDS = [
        'seismic', 'monitoring', 'instrumentation', 'instrument', 'sensor', 'sensors', 'signal', 'input',
        'output', 'outputs', 'relay', 'relays', 'trip', 'fault', 'binary', 'digital', 'analog', 'interface',
        'enclosure', 'equipment', 'module', 'modules', 'channel', 'channels', 'voltage', 'current', 'power',
        'supply', 'range', 'frequency', 'accuracy', 'resolution', 'threshold', 'calibration', 'specification',
        'specifications', 'requirements', 'requirement', 'configuration', 'network', 'protocol', 'data',
        'communication', 'communications', 'controller', 'processor', 'display', 'panel', 'cable', 'wiring',
        'mounting', 'installation', 'operating', 'temperature', 'housing', 'terminal', 'terminals', 'port',
        'ports', 'connection', 'connections', 'measurement', 'measurements', 'testing', 'test', 'trigger',
        'alarm', 'alarms', 'acceleration', 'velocity', 'recording', 'recorder', 'earthquake', 'ground',
        'motion', 'vibration', 'table', 'figure', 'appendix', 'chapter', 'overview', 'introduction',
        'description', 'summary', 'pricing', 'price', 'total', 'quantity', 'item', 'items', 'part', 'model',
        'number', 'page', 'terms', 'conditions', 'warranty', 'delivery', 'shipping', 'payment', 'department',
        'agency', 'administration', 'bureau', 'section', 'volume', 'notice', 'solicitation', 'proposal',
        'reference', 'references', 'exception', 'exceptions', 'approved', 'commercial', 'proponent', 'fax',
        'rehabilitation', 'amendments', 'education', 'et', 'al',
        // Placeholder / non-person fragments that slipped in as names.
        'contact', 'unknown', 'poc', 'tbd', 'none', 'other', 'act', 'the', 'rfp', 'quote', 'quotes',
        'mailbox', 'sales', 'info', 'support', 'admin', 'office',
    ];

    public function handle(DocumentTextExtractionService $text, AiProviderInterface $ai): int
    {
        $dry = (bool) $this->option('dry-run');
        $org = $this->option('org');
        $docs = 0;
        $corrected = 0;
        $created = 0;
        $cleaned = 0;

        $this->info(($dry ? '[dry-run] ' : '') . 'Re-extracting proposal documents (references excluded)…');

        $proposals = ProposalSubmission::query()
            ->when($org, fn ($q) => $q->where('organization_id', $org))
            ->with('company:id,name')
            ->cursor();

        foreach ($proposals as $proposal) {
            $file = ProposalFile::where('proposal_submission_id', $proposal->id)
                ->where('is_current_version', true)
                ->latest('id')->first();
            if (!$file) {
                continue;
            }

            try {
                $raw = $text->extract($file->path, $file->mime_type);
            } catch (\Throwable) {
                continue;
            }
            if (trim($raw) === '') {
                continue;
            }

            $raw = $text->stripReferenceSections($raw);
            try {
                $extracted = $ai->extractDocumentData($raw, ['contacts']);
            } catch (\Throwable) {
                continue;
            }
            $docs++;

            foreach ($extracted['contacts'] ?? [] as $c) {
                $email = mb_strtolower(trim((string) ($c['email'] ?? '')));
                [$first, $last] = $this->splitName(trim((string) ($c['name'] ?? '')), $email);
                if ($email === '' || !$this->validName($first, $last)) {
                    continue;
                }

                $existing = Contact::where('organization_id', $proposal->organization_id)
                    ->whereRaw('LOWER(email) = ?', [$email])->first();

                if ($existing) {
                    $current = trim($existing->first_name . ' ' . $existing->last_name);
                    $proposed = "$first $last";
                    // Only correct a name that is currently junk — never overwrite an
                    // already-valid (possibly human-curated) name with a different one.
                    $needsFix = !$this->validName($existing->first_name, $existing->last_name)
                        && strcasecmp($current, $proposed) !== 0;
                    if ($needsFix) {
                        $this->line("  fix  <{$email}>  “{$current}” → “{$proposed}”");
                        if (!$dry) {
                            $existing->update([
                                'first_name' => $first,
                                'last_name' => $last,
                                'title' => $existing->title ?: ($c['title'] ?? null),
                            ]);
                        }
                        $corrected++;
                    }
                } else {
                    $this->line("  new  {$first} {$last} <{$email}>");
                    if (!$dry) {
                        Contact::create([
                            'organization_id' => $proposal->organization_id,
                            'created_by' => $proposal->created_by,
                            'owner_id' => $proposal->owner_id ?? $proposal->created_by,
                            'company_id' => $proposal->company_id,
                            'first_name' => $first,
                            'last_name' => $last,
                            'title' => $c['title'] ?? null,
                            'email' => $email,
                            'phone' => $c['phone'] ?? null,
                            'is_key_contact' => true,
                        ]);
                    }
                    $created++;
                }
            }
        }

        // Clean obvious junk contacts (spec terms / citation fragments).
        if (!$this->option('no-clean')) {
            $contacts = Contact::query()
                ->when($org, fn ($q) => $q->where('organization_id', $org))
                ->cursor();
            foreach ($contacts as $contact) {
                if ($this->isJunk($contact->first_name, $contact->last_name)) {
                    $label = trim($contact->first_name . ' ' . $contact->last_name);
                    $this->warn("  drop junk #{$contact->id} “{$label}”" . ($contact->email ? " <{$contact->email}>" : ''));
                    if (!$dry) {
                        $contact->delete(); // soft delete — reversible
                    }
                    $cleaned++;
                }
            }
        }

        $this->newLine();
        $this->info(($dry ? '[dry-run] ' : '') . "Done. Documents re-read: {$docs} · corrected: {$corrected} · created: {$created} · junk removed: {$cleaned}");

        return self::SUCCESS;
    }

    /** @return array{0:string,1:string} */
    private function splitName(string $name, string $email): array
    {
        // Strip honorifics and military ranks (e.g. "Sgt Shawn Suber" → "Shawn Suber").
        $name = trim(preg_replace('/^(Mr|Ms|Mrs|Dr|Mx|Sgt|SSgt|MSgt|TSgt|SrA|Capt|Lt|LtCol|Col|Maj|Gen|Cmdr|CDR|Cpl|Pvt|Adm|CWO|MSG|SFC)\.?\s+/i', '', $name) ?? $name);
        if ($name !== '') {
            // Reorder "Last, First" → "First Last".
            if (preg_match('/^([A-Z][\w\'\-]+),\s+(.+)$/', $name, $m)) {
                $name = trim($m[2] . ' ' . $m[1]);
            }
            $parts = preg_split('/\s+/', $name) ?: [];
            $first = array_shift($parts) ?: $name;
            $lastName = $parts ? implode(' ', $parts) : '';
            return [$first, $lastName];
        }
        return ['', ''];
    }

    private function validName(?string $first, ?string $last): bool
    {
        $first = trim((string) $first);
        $last = trim((string) $last);
        if ($first === '' || $last === '') {
            return false;
        }
        foreach ([$first, $last] as $token) {
            // Each token: starts with a capital, letters/apostrophe/hyphen, length ≥ 2,
            // no digits. (A bare initial like "A" or a spec word fails.)
            $head = preg_split('/\s+/', $token)[0] ?? $token;
            if (!preg_match("/^[A-Z][A-Za-z'\\-]{1,}$/", $head)) {
                return false;
            }
        }
        return !$this->isJunk($first, $last);
    }

    private function isJunk(?string $first, ?string $last): bool
    {
        $first = trim((string) $first);
        $last = trim((string) $last);
        if ($first === '' && $last === '') {
            return true;
        }
        $full = mb_strtolower($first . ' ' . $last);
        if (preg_match('/\d/', $full)) {
            return true;
        }
        foreach (preg_split('/\s+/', trim($full)) as $word) {
            $word = trim($word, ".,'-");
            if ($word === '') {
                continue;
            }
            if (mb_strlen($word) === 1) {
                return true; // a bare single-letter token is not a real name
            }
            if (in_array($word, self::JUNK_WORDS, true)) {
                return true;
            }
        }
        return false;
    }
}
