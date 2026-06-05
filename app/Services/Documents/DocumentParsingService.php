<?php

namespace App\Services\Documents;

use App\Models\DocumentParsingJob;
use App\Services\Ai\AiProviderInterface;
use Illuminate\Support\Facades\Log;

class DocumentParsingService
{
    public function __construct(
        private readonly AiProviderInterface $ai,
        private readonly DocumentTextExtractionService $textExtractor
    ) {}

    public function parse(DocumentParsingJob $job, array $schema = []): array
    {
        $job->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $text = $this->textExtractor->extract($job->file_path, $job->mime_type);

            if (empty($text)) {
                throw new \RuntimeException('No text could be extracted from the document.');
            }

            $result = $this->ai->extractDocumentData($text, $schema ?: $job->extraction_schema ?? []);

            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Document parsing failed', ['job_id' => $job->id, 'error' => $e->getMessage()]);
            $job->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
