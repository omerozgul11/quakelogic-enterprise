<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The proposal_files DB records were wiped, but the uploaded files survived on
 * the local disk under proposals/{proposal_id}/{ulid}.{ext}. Since proposal IDs
 * were preserved during recovery, re-link every surviving file to its proposal.
 * Idempotent (skips files already linked by path).
 */
class RecoverProposalFilesSeeder extends Seeder
{
    public function run(): void
    {
        $root = storage_path('app/private/proposals');
        if (! is_dir($root)) {
            $this->command->warn('No proposals file directory found.');

            return;
        }

        $adminId = DB::table('users')->where('email', 'admin@quakelogic.net')->value('id')
            ?? DB::table('users')->min('id');

        $proposalIds = DB::table('proposal_submissions')->pluck('id')->flip();
        $linked = 0;
        $skippedNoProposal = 0;

        foreach (scandir($root) as $dir) {
            if ($dir === '.' || $dir === '..' || ! ctype_digit($dir)) {
                continue;
            }
            $proposalId = (int) $dir;
            if (! isset($proposalIds[$proposalId])) {
                $skippedNoProposal++;
                continue;
            }

            foreach (scandir("{$root}/{$dir}") as $file) {
                $abs = "{$root}/{$dir}/{$file}";
                if ($file === '.' || $file === '..' || ! is_file($abs)) {
                    continue;
                }

                $path = "proposals/{$proposalId}/{$file}";
                if (DB::table('proposal_files')->where('path', $path)->exists()) {
                    continue;
                }

                $name = pathinfo($file, PATHINFO_FILENAME);
                $mime = @mime_content_type($abs) ?: 'application/octet-stream';

                DB::table('proposal_files')->insert([
                    'ulid' => ctype_alnum($name) && strlen($name) === 26 ? $name : (string) Str::ulid(),
                    'proposal_submission_id' => $proposalId,
                    'uploaded_by' => $adminId,
                    'display_name' => $file,
                    'original_filename' => $file,
                    'stored_filename' => $file,
                    'disk' => 'local',
                    'path' => $path,
                    'mime_type' => $mime,
                    'size' => filesize($abs) ?: 0,
                    'status' => 'uploaded',
                    'version' => 1,
                    'is_current_version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $linked++;
            }
        }

        $this->command->info("Re-linked {$linked} proposal file(s); skipped {$skippedNoProposal} folder(s) with no matching proposal.");
    }
}
