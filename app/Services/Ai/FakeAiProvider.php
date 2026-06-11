<?php

namespace App\Services\Ai;

use Carbon\Carbon;
use Illuminate\Support\Str;

class FakeAiProvider implements AiProviderInterface
{
    public function getName(): string
    {
        return 'QuakeAI';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Extract structured fields from real document text using deterministic
     * heuristics (regex + keyword anchoring). When no usable text is supplied,
     * fall back to representative demo data so the UI always has something.
     *
     * Swap AI_PROVIDER to openai/anthropic to replace this with a real LLM —
     * the return contract is identical.
     */
    public function extractDocumentData(string $documentText, array $schema): array
    {
        $text = trim($documentText);

        if (mb_strlen($text) < 40) {
            return $this->demoExtraction();
        }

        $email = $this->matchEmail($text);
        $keyDates = $this->matchKeyDates($text);
        // The proposal's own due date should prefer submission-specific labels over
        // ancillary ones like "Questions Due".
        $due = $keyDates['Proposal Due'] ?? $keyDates['Offers Due'] ?? $keyDates['Response Deadline']
            ?? $keyDates['Closing Date'] ?? $this->matchDueDate($text);
        $data = array_filter([
            'project_name'        => $this->matchProjectName($text),
            'agency'              => $this->matchAgency($text),
            'company'             => $this->matchCompany($text),
            'contact_person'      => $this->matchContactPerson($text, $email),
            'contact_title'       => $this->matchContactTitle($text),
            'email'               => $email,
            'phone'               => $this->matchPhone($text),
            'solicitation_number' => $this->matchSolicitation($text),
            'naics'               => $this->matchNaics($text),
            'set_aside'           => $this->matchSetAside($text),
            'due_date'            => $due,
            'key_dates'           => $keyDates,
            'value'               => $this->matchMoney($text),
            'scope'               => $this->matchScope($text),
            'requirements'        => $this->matchRequirements($text),
            'contacts'            => $this->matchContacts($text),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        // Confidence scales with how many of the high-value fields we located.
        $key = ['project_name', 'agency', 'email', 'solicitation_number', 'due_date', 'value'];
        $found = count(array_intersect_key($data, array_flip($key)));
        $data['_extraction_confidence'] = round(min(0.95, 0.4 + ($found * 0.09)), 2);
        $data['_provider'] = 'fake';
        $data['_chars'] = mb_strlen($text);

        return $data;
    }

    private function matchEmail(string $t): ?string
    {
        if (!preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $t, $m)) {
            return null;
        }
        $emails = array_values(array_unique(array_map('strtolower', $m[0])));
        // Prefer government / military, then any external address over our own domain.
        foreach ($emails as $e) {
            if (preg_match('/\.(gov|mil)$/i', $e)) {
                return $e;
            }
        }
        foreach ($emails as $e) {
            if (!str_contains($e, 'quakelogic')) {
                return $e;
            }
        }
        return $emails[0];
    }

    private function matchPhone(string $t): ?string
    {
        // Accept any phone-like token (US, international, dotted, spaced, bare 10-digit)
        // by collecting candidates and keeping the first with 9–15 digits.
        if (preg_match_all('/(?<![\d.])([(+]?\d[\d\s().\-]{7,16}\d)(?![\d.])/', $t, $m)) {
            foreach ($m[1] as $cand) {
                $digits = preg_replace('/\D/', '', $cand) ?? '';
                $len = strlen($digits);
                if ($len >= 9 && $len <= 15) {
                    return trim($cand);
                }
            }
        }
        return null;
    }

    /**
     * Extract every person we can find with their associated phone/email/title.
     *
     * @return array<int,array{name:?string,email:?string,phone:?string,title:?string}>
     */
    private function matchContacts(string $t): array
    {
        $lines = preg_split('/\n/', $t) ?: [];
        $found = []; // key => [name,email,phone,title]

        $merge = function (?string $name, ?string $email, ?string $phone, ?string $title) use (&$found): void {
            $key = $email ?: ($name ? $this->normName($name) : null);
            if (!$key) {
                return;
            }
            $found[$key] ??= ['name' => null, 'email' => null, 'phone' => null, 'title' => null];
            foreach (['name' => $name, 'email' => $email, 'phone' => $phone, 'title' => $title] as $k => $v) {
                if ($v && empty($found[$key][$k])) {
                    $found[$key][$k] = $v;
                }
            }
        };

        foreach ($lines as $i => $line) {
            // Never harvest a "contact" from a bibliographic citation line.
            if ($this->isCitationLine($line)) {
                continue;
            }

            $prev = $lines[$i - 1] ?? '';
            $next = $lines[$i + 1] ?? '';

            $email = $this->emailInLine($line);
            $phone = $this->phoneInLine($line);
            // Only associate contact info on the SAME line or an immediately
            // adjacent one — never two-plus lines away, which in spec/technical
            // documents pulls in unrelated part/model numbers.
            $emailNear = $email ?: ($this->emailInLine($next) ?: $this->emailInLine($prev));
            $phoneNear = $phone ?: ($this->phoneInLine($next) ?: $this->phoneInLine($prev));

            $name = $this->isOrgLine($line) ? null : $this->extractNameFromLine($line);

            // A person is only recorded when we can actually reach them: a name
            // must be anchored to a real email or phone (same/adjacent line). A
            // bare capitalized phrase with no contact channel is NOT a contact —
            // this is what was turning spec headings into junk "names".
            $title = $this->matchContactTitle($line) ?? $this->matchContactTitle($prev) ?? $this->matchContactTitle($next);
            if ($name && ($emailNear || $phoneNear)) {
                $merge($name, $emailNear, $phoneNear, $title);
            } elseif ($email) {
                $merge($this->deriveNameFromEmail($email), $email, $phoneNear, $title);
            }
        }

        // Consolidate name-only entries into email entries for the same person.
        $byName = [];
        foreach ($found as $k => $v) {
            if ($v['email'] && $v['name']) {
                $byName[$this->normName($v['name'])] = $k;
            }
        }
        foreach ($found as $k => $v) {
            if (!$v['email'] && $v['name'] && isset($byName[$this->normName($v['name'])])) {
                $tgt = $byName[$this->normName($v['name'])];
                foreach (['phone', 'title'] as $f) {
                    if (empty($found[$tgt][$f]) && !empty($v[$f])) {
                        $found[$tgt][$f] = $v[$f];
                    }
                }
                unset($found[$k]);
            }
        }

        // Final guard: a contact must be reachable. Drop anything that ended up
        // with neither an email nor a phone (it is not a usable contact).
        $found = array_filter($found, fn ($v) => !empty($v['email']) || !empty($v['phone']));

        return array_slice(array_values($found), 0, 12);
    }

    private function normName(string $s): string
    {
        $s = preg_replace('/^(Mr\.?|Ms\.?|Mrs\.?|Dr\.?|Mx\.?)\s+/i', '', trim($s)) ?? $s;
        return mb_strtolower(trim($s));
    }

    /**
     * Heuristic: does this line look like a bibliographic citation (author list,
     * year, journal/DOI) rather than a real point of contact?
     */
    private function isCitationLine(string $s): bool
    {
        if (preg_match('/\bet al\.?|\bdoi:|\bpp\.\s*\d|\bvol\.?\s*\d|\bno\.\s*\d|\bissn\b|\bisbn\b|\bproceedings\b|\bjournal\b/i', $s)) {
            return true;
        }
        // "Lastname, A. B." author style accompanied by a four-digit year.
        if (preg_match('/\b(19|20)\d{2}\b/', $s) && preg_match('/[A-Z][a-z]+,\s*[A-Z]\.(?:\s*[A-Z]\.)*/', $s)) {
            return true;
        }
        // Several "Initial. Surname" tokens on one line (author list).
        if (preg_match_all('/\b[A-Z]\.\s*[A-Z][a-z]+/', $s) >= 2) {
            return true;
        }
        return false;
    }

    private function isOrgLine(string $s): bool
    {
        return (bool) preg_match('/\b(Inc\.?|LLC|L\.L\.C\.|Corp\.?|Corporation|Company|Co\.|Ltd\.?|LLP|Technologies|Systems|Solutions|Group|Industries|Associates|Partners|Enterprises|Department|Agency|Administration|Bureau)\b/i', $s)
            || str_contains($s, '&');
    }

    private function emailInLine(string $s): ?string
    {
        return preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $s, $m) ? strtolower($m[0]) : null;
    }

    private function phoneInLine(string $s): ?string
    {
        if (preg_match('/(?<![\d.])([(+]?\d[\d\s().\-]{7,16}\d)(?![\d.])/', $s, $m)) {
            $d = preg_replace('/\D/', '', $m[1]) ?? '';
            if (strlen($d) >= 9 && strlen($d) <= 15) {
                return trim($m[1]);
            }
        }
        return null;
    }

    private function extractNameFromLine(string $line): ?string
    {
        $l = trim($line);
        if ($l === '') {
            return null;
        }
        // Remove emails, phones and common labels so only the name remains.
        $l = preg_replace('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', ' ', $l) ?? $l;
        $l = preg_replace('/(?<![\d.])[(+]?\d[\d\s().\-]{7,16}\d(?![\d.])/', ' ', $l) ?? $l;
        $l = preg_replace('/\b(Point of Contact|Primary Contact|Contact Person|Contact|POC|Attn|Attention|Contracting Officer|Project Lead|Project Manager|Program Manager|Prepared by|Submitted by|Authorized by|Dear|Sincerely|Regards|Respectfully|Best regards|Best|Thank you|Phone|Tel|Mobile|Cell|Email|E-mail)\b[:,\s]*/i', ' ', $l) ?? $l;
        $l = trim($l, " \t-–—:•|");

        // Reorder "Last, First [M.]" into "First Last" before matching.
        if (preg_match('/^([A-Z][a-z\'.\-]+),\s+([A-Z][a-z\'.\-]+(?:\s+[A-Z]\.?)?)$/', $l, $lm)) {
            $l = trim($lm[2] . ' ' . $lm[1]);
        }

        if (preg_match('/((?:Mr\.|Ms\.|Mrs\.|Dr\.)?\s*[A-Z][a-z\'.]+(?:\s+[A-Z]\.?)?(?:\s+[A-Z][a-z\'.]+){1,2})/', $l, $m)) {
            $cand = trim($m[1]);
            if ($this->looksLikePersonName($cand)) {
                // Drop the honorific for a clean first/last split downstream.
                return trim(preg_replace('/^(Mr\.?|Ms\.?|Mrs\.?|Dr\.?|Mx\.?)\s+/i', '', $cand) ?? $cand);
            }
        }
        return null;
    }

    private function deriveNameFromEmail(string $email): ?string
    {
        if (preg_match('/^([a-z]+)[._]([a-z]+)@/i', $email, $m)) {
            return ucfirst($m[1]) . ' ' . ucfirst($m[2]);
        }
        return null;
    }

    private function looksLikePersonName(string $s): bool
    {
        $s = trim($s);
        if (!preg_match('/^(?:Mr\.|Ms\.|Mrs\.|Dr\.)?\s*[A-Z][a-z\'.]+(?:\s+[A-Z]\.?)?(?:\s+[A-Z][a-z\'.]+){1,2}$/', $s)) {
            return false;
        }
        $block = ['Department', 'United', 'States', 'Air', 'Force', 'Army', 'Navy', 'Marine', 'Coast', 'Guard',
            'Space', 'Agency', 'Office', 'Administration', 'Corporation', 'Company', 'Services', 'Solutions',
            'Systems', 'Technologies', 'Group', 'Industries', 'Federal', 'Bureau', 'General', 'National',
            'Request', 'Proposal', 'Statement', 'Scope', 'Work', 'Section', 'Volume', 'Notice', 'Solicitation',
            // Technical / spec vocabulary — common in equipment datasheets and the
            // source of bogus "names" like "Seismic Monitoring Instrumentation".
            'Seismic', 'Monitoring', 'Instrumentation', 'Instrument', 'Sensor', 'Sensors', 'Signal', 'Input',
            'Output', 'Outputs', 'Relay', 'Relays', 'Trip', 'Fault', 'Binary', 'Digital', 'Analog', 'Interface',
            'Enclosure', 'Equipment', 'Module', 'Modules', 'Channel', 'Channels', 'Voltage', 'Current', 'Power',
            'Supply', 'Range', 'Frequency', 'Accuracy', 'Resolution', 'Threshold', 'Calibration', 'Specification',
            'Specifications', 'Requirements', 'Requirement', 'Configuration', 'Network', 'Protocol', 'Data',
            'Communication', 'Communications', 'Controller', 'Processor', 'Display', 'Panel', 'Cable', 'Wiring',
            'Mounting', 'Installation', 'Operating', 'Temperature', 'Housing', 'Terminal', 'Terminals', 'Port',
            'Ports', 'Connection', 'Connections', 'Measurement', 'Measurements', 'Testing', 'Test', 'Trigger',
            'Threshold', 'Alarm', 'Alarms', 'Acceleration', 'Velocity', 'Recording', 'Recorder', 'Earthquake',
            'Ground', 'Motion', 'Vibration', 'Table', 'Figure', 'Appendix', 'Chapter', 'Overview', 'Introduction',
            'Description', 'Summary', 'Pricing', 'Price', 'Total', 'Quantity', 'Item', 'Items', 'Part', 'Model',
            'Number', 'Date', 'Page', 'Terms', 'Conditions', 'Warranty', 'Delivery', 'Shipping', 'Payment'];
        foreach (explode(' ', $s) as $w) {
            if (in_array(trim($w, '.'), $block, true)) {
                return false;
            }
        }
        return true;
    }

    private function matchProjectName(string $t): ?string
    {
        foreach (['Project Title', 'Project Name', 'Project', 'Title', 'Subject', 'RE', 'Re'] as $label) {
            if (preg_match('/^\s*' . preg_quote($label, '/') . '\s*[:\-]\s*(.+)$/mi', $t, $m)) {
                $val = trim($m[1]);
                if (mb_strlen($val) >= 4) {
                    return Str::limit($val, 250, '');
                }
            }
        }
        // Otherwise take the first substantial line that looks like a heading.
        foreach (preg_split('/\n/', $t) as $line) {
            $line = trim($line);
            if (mb_strlen($line) >= 12 && mb_strlen($line) <= 140 && !str_contains($line, '@') && !preg_match('/^\d/', $line)) {
                return $line;
            }
        }
        return null;
    }

    private function matchAgency(string $t): ?string
    {
        foreach (['Agency', 'Issued By', 'Issuing Office', 'Department', 'Contracting Office'] as $label) {
            if (preg_match('/^\s*' . preg_quote($label, '/') . '\s*[:\-]\s*(.+)$/mi', $t, $m)) {
                $val = trim($m[1]);
                if (mb_strlen($val) >= 3) {
                    return Str::limit($val, 200, '');
                }
            }
        }
        // Common federal phrasing, e.g. "Department of Veterans Affairs", "U.S. Army".
        if (preg_match('/\b((?:U\.?S\.?\s+)?Department of [A-Z][A-Za-z]+(?:\s+(?:of|and|the)\s+[A-Z][A-Za-z]+)*)/', $t, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b(U\.?S\.?\s+(?:Army|Navy|Air Force|Marine Corps|Coast Guard|Space Force))\b/i', $t, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function matchCompany(string $t): ?string
    {
        // 1) Labeled buyer/client/recipient (the other company, not ours).
        foreach (['Prepared for', 'Submitted to', 'Client', 'Buyer', 'Customer', 'Sold to', 'Bill to', 'Issued to', 'Offeror', 'Vendor', 'Contractor'] as $label) {
            if (preg_match('/' . preg_quote($label, '/') . '[ \t]*[:\-][ \t]*([^\n]{2,80})/i', $t, $m)) {
                $val = trim($m[1], " \t.,;");
                if ($val !== '' && stripos($val, 'quakelogic') === false) {
                    return Str::limit($val, 120, '');
                }
            }
        }
        // 2) Any organization name with a corporate suffix (excluding our own).
        //    Suffixes like Technologies/Systems/Solutions also appear inside spec
        //    phrases ("Data Acquisition Systems"), so prefer a candidate sitting
        //    right after a vendor/buyer label and skip any embedded in a scope
        //    sentence; only fall back to a bare match when nothing better exists.
        if (preg_match_all('/\b([A-Z][A-Za-z0-9&.,\'\- ]{2,60}?(?:Inc\.?|LLC|L\.L\.C\.|Corp\.?|Corporation|Company|Co\.|Ltd\.?|LLP|Technologies|Systems|Solutions|Group|Industries|Associates|Partners|Enterprises))\b/', $t, $m, PREG_OFFSET_CAPTURE)) {
            $firstClean = null;
            foreach ($m[1] as [$cand, $offset]) {
                $val = trim(preg_replace('/\s+/', ' ', $cand) ?? '');
                if ($val === '' || mb_strlen($val) < 4 || stripos($val, 'quakelogic') !== false) {
                    continue;
                }
                $before = mb_strtolower(mb_substr($t, max(0, $offset - 60), min(60, $offset)));
                // Strong signal: a label naming the other party right before it.
                if (preg_match('/\b(vendor|offeror|contractor|bidder|supplier|firm|company|awarded to|submitted by|prepared by)\b[ \t:\-]*$/', $before)) {
                    return Str::limit($val, 120, '');
                }
                // Skip candidates living inside a scope/requirement sentence.
                $inScope = (bool) preg_match('/\b(shall|furnish|provide|including|include|supply|install|deliver|support|compatible|consist|comprise|equipped|using|via|such as)\b/', $before);
                if (!$inScope && $firstClean === null) {
                    $firstClean = $val;
                }
            }
            if ($firstClean !== null) {
                return Str::limit($firstClean, 120, '');
            }
        }
        return null;
    }

    private function matchContactPerson(string $t, ?string $email): ?string
    {
        // 1) Explicit labels (same line).
        foreach (['Point of Contact', 'Primary Contact', 'Contact Person', 'Contact', 'POC', 'Attn', 'Attention', 'Contracting Officer', 'Prepared by', 'Authorized by', 'Submitted by'] as $label) {
            if (preg_match('/' . preg_quote($label, '/') . '[ \t]*[:\-][ \t]*((?:Mr\.|Ms\.|Mrs\.|Dr\.)?[ \t]*[A-Z][a-zA-Z.\']+(?:[ \t]+[A-Z][a-zA-Z.\']+){1,2})/', $t, $m)) {
                $name = trim($m[1]);
                if ($this->looksLikePersonName($name)) {
                    return $name;
                }
            }
        }
        // 2) Salutation: "Dear Jane Doe,"
        if (preg_match('/\bDear\s+((?:Mr\.|Ms\.|Mrs\.|Dr\.)?\s*[A-Z][a-z.\']+(?:\s+[A-Z][a-z.\']+){0,2})\s*[,:]/', $t, $m)) {
            $name = trim($m[1]);
            if ($this->looksLikePersonName($name)) {
                return $name;
            }
        }
        // 3) Signature block after a sign-off.
        if (preg_match('/(?:Sincerely|Regards|Respectfully|Best regards|Best|Thank you)[,]?\s*\n+\s*([A-Z][a-z.\']+(?:\s+[A-Z][a-z.\']+){1,2})/', $t, $m)) {
            $name = trim($m[1]);
            if ($this->looksLikePersonName($name)) {
                return $name;
            }
        }
        // 4) A name on the line directly above/below the contact email.
        if ($email) {
            $lines = preg_split('/\n/', $t) ?: [];
            foreach ($lines as $idx => $line) {
                if (stripos($line, $email) !== false) {
                    foreach ([$idx - 1, $idx + 1, $idx] as $n) {
                        $cand = trim($lines[$n] ?? '');
                        if ($this->looksLikePersonName($cand)) {
                            return $cand;
                        }
                    }
                }
            }
        }
        // 5) Derive a name from the email local-part as a last resort (jane.doe -> Jane Doe).
        if ($email && preg_match('/^([a-z]+)[._]([a-z]+)@/i', $email, $m)) {
            return ucfirst($m[1]) . ' ' . ucfirst($m[2]);
        }
        return null;
    }

    /**
     * Job titles we recognize, ordered most-specific first so a multi-word title
     * ("Contract Specialist") is matched before a generic one ("Specialist").
     */
    private const CONTACT_TITLES = [
        "Contracting Officer's Representative", 'Contracting Officer Representative',
        'Contracting Officer', 'Contracting Specialist', 'Contract Specialist',
        'Contract Administrator', 'Contract Manager', 'Contract Officer',
        'Procurement Officer', 'Procurement Specialist', 'Procurement Manager',
        'Procurement Analyst', 'Procurement Agent', 'Purchasing Manager',
        'Purchasing Agent', 'Purchasing Officer', 'Acquisition Specialist',
        'Acquisition Manager', 'Program Manager', 'Project Manager', 'Project Lead',
        'Project Coordinator', 'Grants Officer', 'Grants Specialist',
        'Sourcing Manager', 'Sourcing Specialist', 'Category Manager',
        'Buyer', 'Senior Buyer', 'Bid Coordinator', 'Solicitation Manager',
        'Procurement Director', 'Director of Procurement', 'Director of Purchasing',
    ];

    private function matchContactTitle(string $t): ?string
    {
        foreach (self::CONTACT_TITLES as $title) {
            if (preg_match('/\b' . preg_quote($title, '/') . '\b/i', $t)) {
                return $title;
            }
        }
        return null;
    }

    private function matchSolicitation(string $t): ?string
    {
        foreach (['Solicitation Number', 'Solicitation No', 'Solicitation', 'Notice ID', 'RFP No', 'RFP Number', 'RFQ No', 'RFQ Number', 'Reference Number', 'Reference No', 'Bid Number', 'Bid No'] as $label) {
            if (preg_match('/' . preg_quote($label, '/') . '\.?\s*[:#\-]?\s*([A-Z0-9][A-Z0-9\-\/]{3,30})/mi', $t, $m)) {
                return trim($m[1]);
            }
        }
        // Standalone token like "W912DY-26-R-0007" or "FA8773-26-Q-1234".
        if (preg_match('/\b([A-Z0-9]{4,}-\d{2}-[A-Z]-\d{3,5})\b/', $t, $m)) {
            return $m[1];
        }
        return null;
    }

    private function matchNaics(string $t): ?string
    {
        if (preg_match('/NAICS\s*(?:Code)?\.?\s*[:#\-]?\s*(\d{6})/i', $t, $m)) {
            return $m[1];
        }
        return null;
    }

    private function datePattern(): string
    {
        return '(?:(?:January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\.?\s+\d{1,2},?\s+\d{4}|\d{1,2}\/\d{1,2}\/\d{2,4}|\d{4}-\d{2}-\d{2})';
    }

    private function matchDueDate(string $t): ?string
    {
        // Look for a date near a deadline keyword first.
        $anchors = 'due|deadline|closing|close|response|offers? due|submission|submit(?:ted)? by|must be received';
        $datePat = $this->datePattern();

        if (preg_match('/(?:' . $anchors . ')[^\n]{0,40}?(' . $datePat . ')/i', $t, $m)) {
            $d = $this->toDate($m[1]);
            if ($d) {
                return $d;
            }
        }
        // Otherwise the first date that appears anywhere.
        if (preg_match('/(' . $datePat . ')/i', $t, $m)) {
            return $this->toDate($m[1]);
        }
        return null;
    }

    /** @return array<string,string> label => Y-m-d */
    private function matchKeyDates(string $t): array
    {
        $labels = ['Proposal Due', 'Offers Due', 'Questions Due', 'Question Due', 'Inquiries Due',
            'Site Visit', 'Pre-Proposal Conference', 'Pre-bid Conference', 'Response Deadline',
            'Closing Date', 'Award Date', 'Anticipated Award'];
        $datePat = $this->datePattern();
        $out = [];
        foreach ($labels as $lab) {
            if (preg_match('/' . preg_quote($lab, '/') . '[^\n]{0,40}?(' . $datePat . ')/i', $t, $m)) {
                $d = $this->toDate($m[1]);
                if ($d) {
                    $out[$lab] = $d;
                }
            }
        }
        return $out;
    }

    private function matchSetAside(string $t): ?string
    {
        foreach (['Total Small Business Set-Aside', 'Small Business Set-Aside', '8(a)', 'HUBZone',
            'Service-Disabled Veteran-Owned', 'SDVOSB', 'Woman-Owned Small Business', 'WOSB', 'Full and Open'] as $k) {
            if (stripos($t, $k) !== false) {
                return $k;
            }
        }
        if (preg_match('/Set[- ]?Aside\s*[:\-]\s*([^\n]{3,60})/i', $t, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** @return array<int,string> key requirement/spec sentences */
    private function matchRequirements(string $t): array
    {
        $out = [];
        foreach (preg_split('/(?<=[.\n])/', $t) as $sentence) {
            $s = trim(preg_replace('/\s+/', ' ', (string) $sentence) ?? '');
            if (mb_strlen($s) < 20 || mb_strlen($s) > 240) {
                continue;
            }
            if (preg_match('/\b(shall|must|is required to|are required to|required to|minimum of|no later than|page limit|shall not exceed|must not exceed|deliverables?)\b/i', $s)) {
                $out[] = $s;
                if (count($out) >= 6) {
                    break;
                }
            }
        }
        return $out;
    }

    private function toDate(string $raw): ?string
    {
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function matchMoney(string $t): ?float
    {
        // 1) An amount sitting right after a strong "total/price" keyword wins.
        $strong = 'grand total|total price|total cost|total amount|total contract value|contract value|total value|total proposed|total sell(?:ing)? price|sell price|our price|price to (?:the )?(?:client|customer|buyer)|total to (?:the )?(?:client|customer)|total investment|not to exceed|ceiling amount|estimated value|award amount|total bid|bid amount|quoted price|total';
        $num = '(?:\$|USD\s*)?\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)\s*(million|billion|m|b|k|thousand|USD)?';
        // The grand total is the LARGEST amount near a strong keyword (not the first
        // line item that happens to match).
        $strongAmounts = [];
        if (preg_match_all('/(?:' . $strong . ')[^\n]{0,40}?' . $num . '/i', $t, $sm, PREG_SET_ORDER)) {
            foreach ($sm as $m) {
                $v = $this->normalizeMoney($m[1], $m[2] ?? '');
                if ($v >= 100) {
                    $strongAmounts[] = $v;
                }
            }
        }
        if ($strongAmounts) {
            return max($strongAmounts);
        }

        // 2) Otherwise take the largest $/USD-denominated amount in the document
        //    (in a quote the total is almost always the biggest figure).
        $amounts = [];
        if (preg_match_all('/(?:\$\s*|USD\s*|US\$\s*)([0-9][0-9,]*(?:\.[0-9]{1,2})?)\s*(million|billion|m|b|k|thousand)?/i', $t, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $amounts[] = $this->normalizeMoney($m[1], $m[2] ?? '');
            }
        }
        $amounts = array_filter($amounts, fn ($a) => $a >= 100);
        return $amounts ? max($amounts) : null;
    }

    private function normalizeMoney(string $number, string $unit): float
    {
        $value = (float) str_replace(',', '', $number);
        $unit = strtolower(trim($unit));
        return match ($unit) {
            'k', 'thousand' => $value * 1_000,
            'm', 'million' => $value * 1_000_000,
            'b', 'billion' => $value * 1_000_000_000,
            default => $value,
        };
    }

    private function matchScope(string $t): ?string
    {
        foreach (['Scope of Work', 'Statement of Work', 'Scope', 'Description', 'Summary', 'Overview', 'Background', 'Objective'] as $label) {
            if (preg_match('/' . preg_quote($label, '/') . '\s*[:\-]?\s*\n?(.+?)(?:\n\n|$)/si', $t, $m)) {
                $val = trim(preg_replace('/\s+/', ' ', $m[1]) ?? '');
                if (mb_strlen($val) >= 30) {
                    return Str::limit($val, 600);
                }
            }
        }
        return null;
    }

    private function demoExtraction(): array
    {
        return [
            'project_name'        => 'Sample Extracted Project',
            'agency'              => 'Department of Defense',
            'contact_person'      => 'John Smith',
            'contact_title'       => 'Contracting Officer',
            'email'               => 'john.smith@example.gov',
            'phone'               => '(703) 555-0100',
            'solicitation_number' => 'DEMO-2026-0001',
            'due_date'            => now()->addDays(30)->format('Y-m-d'),
            'value'               => 500000,
            'scope'               => 'No readable text was found in the uploaded file, so this is sample data. '
                . 'Upload a text-based PDF, Word document, or .txt file to extract real details.',
            '_extraction_confidence' => 0.2,
            '_provider'           => 'fake',
            '_chars'              => 0,
        ];
    }

    public function generateProposalSummary(array $context): string
    {
        $projectName = $context['project_name'] ?? 'the project';
        $agency = $context['agency'] ?? 'the agency';
        return "This proposal addresses the requirements set forth by {$agency} for {$projectName}. "
            . "QuakeLogic brings extensive expertise and a proven track record to deliver exceptional value. "
            . "Our technical approach emphasizes innovation, reliability, and cost-effectiveness. "
            . "[This is a demo AI-generated summary — connect a real AI provider to generate live summaries.]";
    }

    public function generateGoNoGoRecommendation(array $opportunityData): array
    {
        return [
            'recommendation' => 'GO',
            'confidence' => 0.75,
            'rationale' => 'This opportunity aligns well with QuakeLogic\'s core competencies. '
                . 'The technical requirements match our existing capabilities. '
                . '[Demo AI recommendation — real analysis requires AI provider credentials.]',
            'risk_factors' => [
                'Tight deadline may strain proposal team capacity',
                'Incumbent has existing relationship with agency',
            ],
            'strengths' => [
                'Strong technical alignment',
                'Competitive pricing advantage',
            ],
            'win_probability' => 0.65,
            '_provider' => 'fake',
        ];
    }

    public function estimateWinProbability(array $context): float
    {
        // Fake deterministic calculation for demo
        return 0.65;
    }

    public function generateComplianceMatrix(string $rfpText): array
    {
        return [
            [
                'requirement_reference' => 'Section L.1',
                'requirement' => 'Offeror shall submit technical volume not exceeding 30 pages.',
                'status' => 'pending',
                'compliance_approach' => 'Will ensure technical volume adheres to page limit.',
            ],
            [
                'requirement_reference' => 'Section L.2',
                'requirement' => 'Past performance references: minimum 3 relevant contracts.',
                'status' => 'pending',
                'compliance_approach' => 'Will include 3 relevant federal contracts.',
            ],
            [
                'requirement_reference' => 'Section M.1',
                'requirement' => 'Technical evaluation: demonstrated understanding of requirements.',
                'status' => 'pending',
                'compliance_approach' => 'Technical volume will address all PWS requirements.',
            ],
        ];
    }

    public function generateFollowUpEmail(array $context): string
    {
        $contactName = $context['contact_name'] ?? 'Contracting Officer';
        $proposalTitle = $context['proposal_title'] ?? 'our proposal';
        $daysSinceSubmission = $context['days_since_submission'] ?? 14;

        return "Subject: Follow-up: {$proposalTitle} - Status Inquiry\n\n"
            . "Dear {$contactName},\n\n"
            . "I hope this message finds you well. I am following up on the proposal we submitted "
            . "{$daysSinceSubmission} days ago regarding {$proposalTitle}.\n\n"
            . "We remain very interested in this opportunity and are available to provide any additional "
            . "information or clarification that may be helpful during your evaluation process.\n\n"
            . "Please let us know if you have any questions or need additional documentation.\n\n"
            . "Thank you for your consideration.\n\n"
            . "Best regards,\n[Your Name]\n[Your Title]\nQuakeLogic\n\n"
            . "[This is a demo AI-generated email — connect a real AI provider for live generation.]";
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        // Pull the user's latest message out of the conversation prompt.
        $msg = $userPrompt;
        if (preg_match_all('/^User:\s*(.+)$/m', $userPrompt, $m) && !empty($m[1])) {
            $msg = trim((string) end($m[1]));
        }
        $short = Str::limit(trim($msg), 140);

        return "Thanks for asking about \"{$short}\". "
            . "I'm QuakeAI — I can help you analyze proposals, draft summaries, flag key deadlines, and pull details from your uploaded documents. "
            . "I'm currently in demo mode, so set AI_PROVIDER=anthropic with an ANTHROPIC_API_KEY in your .env to unlock full, live answers.";
    }
}
