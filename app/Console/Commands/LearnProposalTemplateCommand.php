<?php

namespace App\Console\Commands;

use App\Services\Documents\DocumentTextExtractionService;
use App\Services\Proposals\ProposalTemplateService;
use Illuminate\Console\Command;

/**
 * Learn a reusable proposal template from an exemplar proposal (a prior winning
 * bid). The exemplar's standard sections become gold-standard examples the
 * Proposal Writer matches for depth and structure.
 *
 *   php artisan proposal:learn-template storage/app/reference/FINAL_QL_ATL_Proposal_v2.pdf --org=1
 */
class LearnProposalTemplateCommand extends Command
{
    protected $signature = 'proposal:learn-template
        {path : Path to an exemplar proposal (PDF, or pre-extracted .txt)}
        {--org=1 : Organization id to learn the template for}';

    protected $description = 'Learn a reusable proposal template (gold-standard sections) from an exemplar proposal.';

    public function handle(ProposalTemplateService $templates, DocumentTextExtractionService $extractor): int
    {
        $abs = $this->resolve($this->argument('path'));
        if ($abs === null) {
            $this->error('File not found: ' . $this->argument('path'));

            return self::FAILURE;
        }

        $orgId = max(1, (int) $this->option('org'));

        $text = str_ends_with(strtolower($abs), '.txt')
            ? (string) file_get_contents($abs)
            : $this->extractPdf($abs);

        if (trim($text) === '') {
            $this->error('Could not extract text from the document.');

            return self::FAILURE;
        }

        $count = $templates->learnFromText($orgId, $text, basename($abs));
        if ($count === 0) {
            $this->warn('No standard sections recognised. The exemplar needs numbered ALL-CAPS headings like "1. EXECUTIVE SUMMARY", "7. TECHNICAL SOLUTION".');

            return self::FAILURE;
        }

        $this->info("Learned {$count} template section(s) for organization {$orgId} from " . basename($abs) . ':');
        foreach ($templates->forOrg($orgId) as $key => $s) {
            $this->line("  - " . str_pad($key, 24) . " [{$s['type']}]  " . mb_strlen($s['content']) . " chars   <- {$s['heading']}");
        }

        return self::SUCCESS;
    }

    private function resolve(string $path): ?string
    {
        foreach ([$path, base_path($path), storage_path('app/' . $path)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractPdf(string $abs): string
    {
        $out = @shell_exec('pdftotext -layout ' . escapeshellarg($abs) . ' - 2>/dev/null');
        if (is_string($out) && trim($out) !== '') {
            return $out;
        }
        try {
            return (new \Smalot\PdfParser\Parser())->parseFile($abs)->getText();
        } catch (\Throwable) {
            return '';
        }
    }
}
