<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentTextExtractionService
{
    /**
     * Extract plain text from a stored document. Routing is by extension first
     * (more reliable than the browser-supplied MIME type) and MIME type second.
     */
    public function extract(string $filePath, string $mimeType = ''): string
    {
        if (!Storage::disk('local')->exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $absolutePath = Storage::disk('local')->path($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $text = match (true) {
            $ext === 'pdf' || $mimeType === 'application/pdf' => $this->extractPdf($absolutePath),
            in_array($ext, ['txt', 'csv', 'md'], true) || str_starts_with($mimeType, 'text/') => (string) file_get_contents($absolutePath),
            in_array($ext, ['docx', 'dotx'], true) || str_contains($mimeType, 'wordprocessingml') => $this->extractDocx($absolutePath),
            $ext === 'doc' || $mimeType === 'application/msword' => $this->extractLegacyDoc($absolutePath),
            default => '',
        };

        return $this->normalize($text);
    }

    private function extractPdf(string $path): string
    {
        // Prefer the poppler `pdftotext` binary when present (fastest, best layout).
        if (@shell_exec('which pdftotext')) {
            $output = @shell_exec(sprintf('pdftotext -layout %s - 2>/dev/null', escapeshellarg($path)));
            if (is_string($output) && trim($output) !== '') {
                return $output;
            }
        }

        // Pure-PHP fallback (smalot/pdfparser) — no system binary required.
        try {
            return (new PdfParser())->parseFile($path)->getText();
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractDocx(string $path): string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    // Turn paragraph/row/tab/break markers into whitespace, then strip tags.
                    $xml = str_replace(
                        ['</w:p>', '</w:tr>', '<w:tab/>', '<w:br/>', '<w:cr/>'],
                        ["\n", "\n", "\t", "\n", "\n"],
                        $xml
                    );
                    return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        } catch (\Throwable) {
            // fall through
        }

        return '';
    }

    private function extractLegacyDoc(string $path): string
    {
        // Best-effort for the old binary .doc format: pull readable ASCII runs.
        $raw = (string) @file_get_contents($path);
        if ($raw === '') {
            return '';
        }
        $clean = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]+/u', ' ', $raw) ?? '';
        return $clean;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * Remove a trailing References / Bibliography / Works Cited section. Those
     * sections are full of author names and citation initials (e.g. "A. Apamuk,
     * et al., 2019") that would otherwise be mistaken for proposal contacts.
     *
     * Only a heading in the latter part of the document is cut, so an early
     * "Reference Number:" field is never affected.
     */
    public function stripReferenceSections(string $text): string
    {
        $lines = preg_split('/\n/', $text) ?: [];
        $count = count($lines);
        if ($count < 12) {
            return $text;
        }

        $headingRe = '/^\s*(?:\d+[.)]\s*|[IVXLC]+\.\s*|appendix\s+[a-z]\s*[:.\-]?\s*)?'
            . '(references|reference list|bibliography|works cited|literature cited|citations|sources cited)\s*:?\s*$/i';

        $threshold = (int) floor($count * 0.4);
        foreach ($lines as $i => $line) {
            if ($i < $threshold) {
                continue; // ignore early matches (form fields, table of contents)
            }
            if (preg_match($headingRe, trim($line))) {
                return implode("\n", array_slice($lines, 0, $i));
            }
        }

        return $text;
    }
}
