<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Nightly safety net (added after the 2026-06-17 data-loss incident): dump the
 * database, gzip it, upload to the backup disk (MinIO/S3 by default), and prune
 * old backups. Uses --single-transaction for a consistent, lock-free InnoDB dump.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--disk=s3 : Filesystem disk to store the backup on}
        {--path=backups : Folder (prefix) on the disk}
        {--keep=14 : Number of most-recent backups to retain}';

    protected $description = 'Dump the database, gzip it, upload to the backup disk, and prune old backups';

    public function handle(): int
    {
        $conn = config('database.default');
        $db = config("database.connections.{$conn}.database");
        $host = config("database.connections.{$conn}.host", '127.0.0.1');
        $port = (string) config("database.connections.{$conn}.port", 3306);
        $user = config("database.connections.{$conn}.username");
        $pass = (string) config("database.connections.{$conn}.password");

        $dumpBin = $this->findBinary(['mariadb-dump', 'mysqldump']);
        if (! $dumpBin) {
            $this->error('No mariadb-dump / mysqldump binary found on PATH.');

            return self::FAILURE;
        }

        $filename = sprintf('db_%s_%s.sql.gz', $db, now()->format('Ymd_His'));
        $tmp = storage_path('app/'.$filename);

        // Dump → gzip → temp file. Password is passed via MYSQL_PWD (never on the
        // command line / process list). Args are config values, not user input.
        $cmd = sprintf(
            '%s --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 -h %s -P %s -u %s %s | gzip -c > %s',
            escapeshellarg($dumpBin),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($db),
            escapeshellarg($tmp),
        );

        $this->info("Dumping {$db} via ".basename($dumpBin).'…');
        $process = Process::fromShellCommandline($cmd, base_path(), ['MYSQL_PWD' => $pass], null, 1800);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            $this->error('Dump failed: '.trim($process->getErrorOutput()) ?: 'empty output');

            return self::FAILURE;
        }

        $sizeMb = round(filesize($tmp) / 1048576, 2);
        $disk = Storage::disk($this->option('disk'));
        $remote = trim($this->option('path'), '/')."/{$filename}";

        $stream = fopen($tmp, 'rb');
        $ok = $disk->put($remote, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($tmp);

        if ($ok === false) {
            $this->error("Upload to [{$this->option('disk')}] failed.");

            return self::FAILURE;
        }

        $this->info("Uploaded {$remote} ({$sizeMb} MB) to disk [{$this->option('disk')}].");
        $this->prune($disk, trim($this->option('path'), '/'), (int) $this->option('keep'));

        return self::SUCCESS;
    }

    /** Keep only the newest N .sql.gz backups (names sort chronologically). */
    private function prune($disk, string $path, int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $backups = collect($disk->files($path))
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->sortDesc()
            ->values();

        $stale = $backups->slice($keep);
        foreach ($stale as $file) {
            $disk->delete($file);
        }

        if ($stale->isNotEmpty()) {
            $this->info("Pruned {$stale->count()} old backup(s); keeping {$keep}.");
        }
    }

    /** @param array<int,string> $candidates */
    private function findBinary(array $candidates): ?string
    {
        foreach ($candidates as $bin) {
            $p = new Process(['sh', '-c', "command -v {$bin}"]);
            $p->run();
            $path = trim($p->getOutput());
            if ($p->isSuccessful() && $path !== '') {
                return $path;
            }
        }

        return null;
    }
}
