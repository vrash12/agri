<?php

namespace App\Console\Commands;

use App\Support\AuditTrail;
use App\Support\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--path= : Write the dump to this directory instead of the configured one}
        {--keep= : Number of dumps to retain; 0 keeps every dump}
        {--no-compress : Write plain SQL instead of gzip}
        {--no-prune : Keep every existing dump regardless of the retention setting}';

    protected $description = 'Write a verified, restorable dump of the operational database';

    public function handle(DatabaseBackup $backup): int
    {
        $path = $this->option('path') ?: null;
        $compress = $this->option('no-compress') ? false : null;

        try {
            $result = $backup->create($path, $compress);
            // A dump nobody has read back is only a promise of a backup.
            $backup->verify($result['path'], $result['tables']);
        } catch (Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());
            report($exception);

            return self::FAILURE;
        }

        $rows = array_sum($result['tables']);
        $this->info(sprintf(
            'Backed up %d tables (%s rows) to %s',
            count($result['tables']),
            number_format($rows),
            $result['path']
        ));
        $this->line('  size     '.$this->readableSize($result['bytes']));
        $this->line('  sha256   '.$result['checksum']);

        if (! $this->option('no-prune')) {
            $keep = $this->option('keep');
            $removed = $backup->prune($path, $keep === null ? null : (int) $keep);
            if ($removed !== []) {
                $this->line('  removed  '.count($removed).' older dump(s)');
            }
        }

        $this->recordAudit($result, $rows);

        $this->warn('Copy this file off the machine; a dump beside the database does not survive losing it.');

        return self::SUCCESS;
    }

    /** @param  array{path:string,bytes:int,tables:array<string,int>,checksum:string,compressed:bool}  $result */
    private function recordAudit(array $result, int $rows): void
    {
        // Best-effort, like the rest of the audit trail: a logging failure must not
        // make a successful backup look like a failed one.
        AuditTrail::record(
            'backed_up',
            'Database backup',
            sprintf('A database backup of %d tables and %s rows was written.', count($result['tables']), number_format($rows)),
            [
                'metadata' => [
                    // The filename only, never the full path or any row content.
                    'file' => basename($result['path']),
                    'bytes' => $result['bytes'],
                    'checksum' => $result['checksum'],
                    'tables' => count($result['tables']),
                    'rows' => $rows,
                ],
            ]
        );
    }

    private function readableSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return $bytes.' B';
    }
}
