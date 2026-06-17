<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Restore the database from a gzipped backup created by `db:backup`. DESTRUCTIVE:
 * it overwrites the current schema + data, so it confirms the exact target
 * database first (unless --force) — the safeguard the original incident lacked.
 */
class RestoreDatabase extends Command
{
    protected $signature = 'db:restore
        {file? : Backup filename on the disk (defaults to the most recent)}
        {--disk=s3 : Filesystem disk the backup lives on}
        {--path=backups : Folder (prefix) on the disk}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Restore the database from a gzipped backup on the backup disk (DESTRUCTIVE)';

    public function handle(): int
    {
        $conn = config('database.default');
        $db = config("database.connections.{$conn}.database");
        $host = config("database.connections.{$conn}.host", '127.0.0.1');
        $port = (string) config("database.connections.{$conn}.port", 3306);
        $user = config("database.connections.{$conn}.username");
        $pass = (string) config("database.connections.{$conn}.password");

        $disk = Storage::disk($this->option('disk'));
        $path = trim($this->option('path'), '/');

        $file = $this->argument('file');
        if (! $file) {
            $file = collect($disk->files($path))->filter(fn ($f) => str_ends_with($f, '.sql.gz'))->sortDesc()->first();
            if (! $file) {
                $this->error("No backups found on [{$this->option('disk')}] under {$path}/.");

                return self::FAILURE;
            }
        } elseif (! str_contains($file, '/')) {
            $file = "{$path}/{$file}";
        }

        if (! $disk->exists($file)) {
            $this->error("Backup not found: {$file}");

            return self::FAILURE;
        }

        $this->warn("About to restore [{$file}] INTO database \"{$db}\" on {$host}.");
        $this->warn('This OVERWRITES all current data in that database.');
        if (! $this->option('force') && ! $this->confirm("Type confirm: restore into \"{$db}\"?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $client = $this->findBinary(['mariadb', 'mysql']);
        if (! $client) {
            $this->error('No mariadb / mysql client binary found on PATH.');

            return self::FAILURE;
        }

        // Stream the backup down to a temp file, then gunzip | client into the DB.
        $tmp = storage_path('app/restore_'.basename($file));
        $in = $disk->readStream($file);
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        if (is_resource($in)) {
            fclose($in);
        }

        $cmd = sprintf(
            'gunzip -c %s | %s -h %s -P %s -u %s %s',
            escapeshellarg($tmp),
            escapeshellarg($client),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($db),
        );

        $this->info('Restoring…');
        $process = Process::fromShellCommandline($cmd, base_path(), ['MYSQL_PWD' => $pass], null, 1800);
        $process->run();
        @unlink($tmp);

        if (! $process->isSuccessful()) {
            $this->error('Restore failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info("Restored \"{$db}\" from {$file}.");

        return self::SUCCESS;
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
