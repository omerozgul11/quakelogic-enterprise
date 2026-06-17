<?php

namespace App\Services\Proposals;

use Illuminate\Support\Facades\Storage;

/**
 * Learns a reusable proposal "template" from an exemplar proposal (e.g. a prior
 * winning bid): it splits the document into its standard sections and stores
 * each as a gold-standard example. The Proposal Writer then matches each new
 * draft to the corresponding exemplar's structure, depth and standard/boilerplate
 * language — so generated proposals stop coming up short. Stored per-org as a
 * JSON file (sections can be large), not in the DB.
 */
class ProposalTemplateService
{
    private const MAX_CHARS = 7000;

    /**
     * Heading keyword (uppercase, first match wins) => [writer section key, type].
     * type 'standard' = boilerplate reused closely; 'tailored' = structure model.
     */
    private const HEADING_MAP = [
        'EXECUTIVE SUMMARY' => ['executive_summary', 'tailored'],
        'SNAPSHOT' => ['solution_snapshot', 'tailored'],
        'INTRODUCTION' => ['introduction', 'standard'],
        'GENERAL BACKGROUND' => ['general_background', 'standard'],
        'BACKGROUND' => ['general_background', 'standard'],
        'SYSTEM OVERVIEW' => ['system_overview', 'tailored'],
        'TECHNICAL SOLUTION' => ['technical_solution', 'tailored'],
        'TECHNICAL APPROACH' => ['technical_solution', 'tailored'],
        'COMPLIANCE' => ['compliance_matrix', 'tailored'],
        'INSTALLATION' => ['installation_deployment', 'tailored'],
        'DEPLOYMENT' => ['installation_deployment', 'tailored'],
        'OPERATIONS' => ['operations_maintenance', 'standard'],
        'MAINTENANCE' => ['operations_maintenance', 'standard'],
        'SCHEDULE' => ['schedule_deliverables', 'tailored'],
        'DELIVERABLES' => ['schedule_deliverables', 'tailored'],
        'COMMERCIAL' => ['commercial_proposal', 'tailored'],
        'PRICING' => ['commercial_proposal', 'tailored'],
        'CAPABILITIES' => ['capabilities_references', 'standard'],
        'PAST PERFORMANCE' => ['capabilities_references', 'standard'],
        'REFERENCES' => ['capabilities_references', 'standard'],
        'QUALITY' => ['quality_control', 'standard'],
        'STAFFING' => ['staffing_plan', 'tailored'],
        'KEY PERSONNEL' => ['staffing_plan', 'tailored'],
        'MANAGEMENT' => ['management_plan', 'tailored'],
    ];

    private function path(int $orgId): string
    {
        return "proposal-templates/org-{$orgId}.json";
    }

    /**
     * Parse an exemplar's text into sections and store them. Returns the count of
     * sections learned (0 if none were recognised).
     */
    public function learnFromText(int $orgId, string $text, string $source): int
    {
        $sections = $this->splitIntoSections($text);
        if ($sections === []) {
            return 0;
        }

        Storage::disk('local')->put($this->path($orgId), (string) json_encode([
            'source' => $source,
            'learned_at' => now()->toIso8601String(),
            'sections' => $sections,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return count($sections);
    }

    /** @return array<string,array{heading:string,type:string,content:string}> */
    public function forOrg(int $orgId): array
    {
        try {
            $raw = Storage::disk('local')->exists($this->path($orgId))
                ? Storage::disk('local')->get($this->path($orgId))
                : null;
        } catch (\Throwable) {
            $raw = null;
        }
        $data = $raw ? json_decode($raw, true) : null;

        return is_array($data['sections'] ?? null) ? $data['sections'] : [];
    }

    /** @return array{heading:string,type:string,content:string}|null */
    public function exemplar(int $orgId, string $sectionKey): ?array
    {
        return $this->forOrg($orgId)[$sectionKey] ?? null;
    }

    public function hasTemplate(int $orgId): bool
    {
        return $this->forOrg($orgId) !== [];
    }

    /**
     * Split exemplar text into mapped sections keyed by writer section key.
     *
     * @return array<string,array{heading:string,type:string,content:string}>
     */
    private function splitIntoSections(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Numbered ALL-CAPS headings like "1. EXECUTIVE SUMMARY" / "10. OPERATIONS & MAINTENANCE (O&M)".
        if (! preg_match_all('/^[ \t]*(\d{1,2})\.?[ \t]+([A-Z][A-Z0-9 &\/()\-\.]{4,})[ \t]*$/m', $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }

        $sections = [];
        $count = count($m);
        foreach ($m as $i => $match) {
            $heading = trim($match[2][0]);
            $mapped = $this->mapHeading($heading);
            if ($mapped === null) {
                continue;
            }
            [$key, $type] = $mapped;

            $start = $match[0][1] + strlen($match[0][0]);
            $end = $i + 1 < $count ? $m[$i + 1][0][1] : strlen($text);
            $content = trim(mb_substr($text, $start, $end - $start));
            $content = trim(preg_replace('/\n{3,}/', "\n\n", $content));
            if (mb_strlen($content) < 120) {
                continue; // skip stubs (e.g. a TOC line)
            }

            $sections[$key] = [
                'heading' => $heading,
                'type' => $type,
                'content' => mb_substr($content, 0, self::MAX_CHARS),
            ];
        }

        return $sections;
    }

    /** @return array{0:string,1:string}|null */
    private function mapHeading(string $heading): ?array
    {
        $up = mb_strtoupper($heading);
        foreach (self::HEADING_MAP as $keyword => $target) {
            if (str_contains($up, $keyword)) {
                return $target;
            }
        }

        return null;
    }
}
