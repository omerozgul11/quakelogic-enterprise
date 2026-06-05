<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;

class DocumentTextExtractionService
{
    public function extract(string $filePath, string $mimeType): string
    {
        if (!Storage::disk('local')->exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $absolutePath = Storage::disk('local')->path($filePath);

        return match (true) {
            $mimeType === 'application/pdf' => $this->extractPdf($absolutePath),
            in_array($mimeType, ['text/plain', 'text/csv']) => file_get_contents($absolutePath),
            str_contains($mimeType, 'word') => $this->extractDocx($absolutePath),
            default => '',
        };
    }

    private function extractPdf(string $path): string
    {
        // Uses pdftotext if available (installed via poppler-utils in Docker)
        if (shell_exec('which pdftotext')) {
            $output = shell_exec(sprintf('pdftotext %s - 2>/dev/null', escapeshellarg($path)));
            return $output ?: '';
        }

        return '';
    }

    private function extractDocx(string $path): string
    {
        // Basic extraction from .docx ZIP structure
        try {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $content = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($content) {
                    return strip_tags(str_replace(['</w:p>', '</w:tr>'], "\n", $content));
                }
            }
        } catch (\Throwable) {
            // Silent — return empty
        }

        return '';
    }
}
