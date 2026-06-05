<?php

namespace App\Services\Reporting;

use App\Models\Export;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportExportService
{
    public function createExport(User $user, string $type, string $format, array $filters = []): Export
    {
        return Export::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'type' => $type,
            'format' => $format,
            'status' => 'pending',
            'filters' => $filters,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function generateCsv(Export $export, array $data): string
    {
        $filename = sprintf('%s-%s-%s.csv', $export->type, $export->id, now()->format('YmdHis'));
        $path = "exports/{$filename}";

        $csv = '';
        if (!empty($data)) {
            $csv .= implode(',', array_keys($data[0])) . "\n";
            foreach ($data as $row) {
                $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row)) . "\n";
            }
        }

        Storage::disk('local')->put($path, $csv);

        $export->update([
            'status' => 'completed',
            'file_path' => $path,
            'file_size' => Storage::disk('local')->size($path),
            'row_count' => count($data),
            'completed_at' => now(),
        ]);

        return $path;
    }
}
