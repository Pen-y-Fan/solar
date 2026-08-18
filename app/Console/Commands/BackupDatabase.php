<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database {--keep=14 : Number of daily backups to retain}';

    protected $description = 'Checkpoint and copy the SQLite database file, pruning old backups';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite') {
            $this->info('Database connection is not sqlite; nothing to do.');

            return self::SUCCESS;
        }

        $sourcePath = config('database.connections.sqlite.database');
        if (! is_string($sourcePath) || ! file_exists($sourcePath)) {
            $this->error("SQLite database file not found at: {$sourcePath}");

            return self::FAILURE;
        }

        // Force a WAL checkpoint so the main db file reflects every committed
        // write before we copy it — a plain file copy of a WAL database can
        // otherwise miss transactions still sitting in the -wal file.
        DB::statement('PRAGMA wal_checkpoint(TRUNCATE);');

        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $destination = $backupDir . '/database-' . Carbon::now()->format('Y-m-d_His') . '.sqlite';

        if (! copy($sourcePath, $destination)) {
            throw new RuntimeException("Failed to copy database to {$destination}");
        }

        $this->info("Backed up database to {$destination}");

        $keep = max(1, (int) $this->option('keep'));
        $backups = collect(glob($backupDir . '/database-*.sqlite') ?: [])
            ->sortByDesc(fn (string $path): int => (int) filemtime($path))
            ->values();

        $backups->slice($keep)->each(function (string $path): void {
            unlink($path);
            $this->line("Pruned old backup: {$path}");
        });

        return self::SUCCESS;
    }
}
