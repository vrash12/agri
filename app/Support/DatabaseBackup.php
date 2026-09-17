<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Write a restorable logical dump of the operational database.
 *
 * The dump is produced in PHP rather than by shelling out to mysqldump. The
 * client installed beside a server is not always a matching version, shared
 * hosting frequently forbids shell execution altogether, and this repository has
 * no complete migration history, so the dump is the only way back from a lost
 * database. Doing the work in PHP keeps that path available everywhere the
 * application already runs.
 *
 * Reading happens inside one consistent snapshot, so a dump taken while staff are
 * working is still a coherent point in time rather than a mix of before and after.
 */
class DatabaseBackup
{
    /**
     * Statements that make a restore predictable regardless of server defaults.
     */
    private const HEADER = [
        'SET NAMES utf8mb4',
        'SET FOREIGN_KEY_CHECKS = 0',
        'SET UNIQUE_CHECKS = 0',
        'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"',
        'SET autocommit = 0',
    ];

    private const FOOTER = [
        'COMMIT',
        'SET UNIQUE_CHECKS = 1',
        'SET FOREIGN_KEY_CHECKS = 1',
    ];

    /**
     * Produce one dump and return what was written.
     *
     * @return array{path:string,bytes:int,tables:array<string,int>,checksum:string,compressed:bool}
     */
    public function create(?string $directory = null, ?bool $compress = null): array
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException(
                'Database backups support the MySQL/MariaDB connection this application runs on; got '
                .$connection->getDriverName().'.'
            );
        }

        $directory = $directory ?: (string) config('backup.path');
        $compress = $compress ?? (bool) config('backup.compress', true);
        if ($compress && ! function_exists('gzopen')) {
            throw new RuntimeException('Compressed backups need the zlib extension. Set BACKUP_DATABASE_COMPRESS=false.');
        }

        $this->ensureDirectory($directory);
        $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR
            .$connection->getDatabaseName().'-'.LocalTime::now()->format('Ymd-His')
            .'.sql'.($compress ? '.gz' : '');

        $handle = $compress ? gzopen($path, 'wb9') : fopen($path, 'wb');
        if (! $handle) {
            throw new RuntimeException('The backup file could not be opened for writing: '.$path);
        }

        try {
            $tables = $this->write($handle, $connection, $compress);
        } catch (Throwable $exception) {
            // A partial dump is worse than none: it looks like a backup and is not one.
            $this->close($handle, $compress);
            @unlink($path);

            throw $exception;
        }

        $this->close($handle, $compress);

        return [
            'path' => $path,
            'bytes' => (int) filesize($path),
            'tables' => $tables,
            'checksum' => (string) hash_file('sha256', $path),
            'compressed' => $compress,
        ];
    }

    /**
     * Confirm a dump is readable and contains every table it claims to.
     *
     * @param  array<string,int>  $tables
     */
    public function verify(string $path, array $tables): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('The backup file is missing or empty: '.$path);
        }

        $compressed = str_ends_with($path, '.gz');
        $handle = $compressed ? gzopen($path, 'rb') : fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('The backup file could not be read back: '.$path);
        }

        $seen = [];
        while (! ($compressed ? gzeof($handle) : feof($handle))) {
            $line = $compressed ? gzgets($handle) : fgets($handle);
            if ($line === false) {
                break;
            }
            if (preg_match('/^CREATE TABLE `([^`]+)`/', $line, $match)) {
                $seen[$match[1]] = true;
            }
        }
        $this->close($handle, $compressed);

        $missing = array_diff(array_keys($tables), array_keys($seen));
        if ($missing !== []) {
            throw new RuntimeException(
                'The backup is missing table definitions and cannot be trusted: '.implode(', ', $missing)
            );
        }
    }

    /**
     * Remove the oldest dumps, keeping the most recent $keep.
     *
     * @return array<int,string> Paths that were removed.
     */
    public function prune(?string $directory = null, ?int $keep = null): array
    {
        $directory = $directory ?: (string) config('backup.path');
        $keep = $keep ?? (int) config('backup.keep', 14);
        if ($keep <= 0 || ! is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'*.sql*') ?: [];
        // Newest first; the filename carries a sortable timestamp.
        rsort($files, SORT_STRING);

        $removed = [];
        foreach (array_slice($files, $keep) as $file) {
            if (@unlink($file)) {
                $removed[] = $file;
            }
        }

        return $removed;
    }

    /**
     * @param  resource  $handle
     * @return array<string,int> Table name to row count.
     */
    private function write($handle, Connection $connection, bool $compress): array
    {
        $this->line($handle, $compress, '-- Agriculture Information System database backup');
        $this->line($handle, $compress, '-- Database: '.$connection->getDatabaseName());
        $this->line($handle, $compress, '-- Taken: '.LocalTime::now()->toDateTimeString().' ('.config('app.display_timezone', 'UTC').')');
        $this->line($handle, $compress, '-- Restore instructions are in AGENTS.md, section 11.');
        $this->line($handle, $compress, '');
        foreach (self::HEADER as $statement) {
            $this->line($handle, $compress, $statement.';');
        }
        $this->line($handle, $compress, '');

        $snapshot = $this->beginSnapshot($connection);

        try {
            $tables = [];
            foreach ($this->tables($connection) as $table) {
                $tables[$table] = $this->writeTable($handle, $connection, $compress, $table);
            }
        } finally {
            if ($snapshot) {
                // Read-only snapshot; nothing to keep.
                try {
                    $connection->rollBack();
                } catch (Throwable) {
                    // The snapshot was never opened; nothing to undo.
                }
            }
        }

        foreach (self::FOOTER as $statement) {
            $this->line($handle, $compress, $statement.';');
        }

        return $tables;
    }

    /** @param  resource  $handle */
    private function writeTable($handle, Connection $connection, bool $compress, string $table): int
    {
        $create = (array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`');
        $ddl = $create['Create Table'] ?? $create['Create View'] ?? null;
        if (! is_string($ddl)) {
            throw new RuntimeException('The definition for table '.$table.' could not be read.');
        }

        $this->line($handle, $compress, '');
        $this->line($handle, $compress, '-- Table: '.$table);
        $this->line($handle, $compress, 'DROP TABLE IF EXISTS `'.$table.'`;');
        $this->line($handle, $compress, $ddl.';');

        $pdo = $connection->getPdo();
        $batch = max(1, (int) config('backup.insert_batch', 200));
        $columns = null;
        $rows = 0;
        $values = [];

        foreach ($connection->cursor('SELECT * FROM `'.$table.'`') as $row) {
            $row = (array) $row;
            $columns ??= '(`'.implode('`, `', array_keys($row)).'`)';
            $values[] = '('.implode(', ', array_map(
                fn ($value): string => $value === null ? 'NULL' : $this->quote($pdo, (string) $value),
                $row
            )).')';
            $rows++;

            if (count($values) >= $batch) {
                $this->line($handle, $compress, 'INSERT INTO `'.$table.'` '.$columns.' VALUES '.implode(', ', $values).';');
                $values = [];
            }
        }

        if ($values !== []) {
            $this->line($handle, $compress, 'INSERT INTO `'.$table.'` '.$columns.' VALUES '.implode(', ', $values).';');
        }

        return $rows;
    }

    /**
     * Quote a value so the statement stays on a single line.
     *
     * A newline inside a note or address would otherwise split a statement across
     * lines. The driver has already escaped quotes and backslashes, so turning the
     * remaining control characters into their escape sequences round-trips exactly
     * and keeps the dump one statement per line.
     */
    private function quote(\PDO $pdo, string $value): string
    {
        return str_replace(["\n", "\r"], ['\\n', '\\r'], $pdo->quote($value));
    }

    /**
     * Read every row from one moment in time, so a dump taken during office hours
     * is not a mixture of before and after a staff member's save.
     */
    private function beginSnapshot(Connection $connection): bool
    {
        try {
            $connection->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $connection->getPdo()->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

            return true;
        } catch (Throwable) {
            // Non-InnoDB tables cannot offer this; the dump is still taken.
            return false;
        }
    }

    /** @return array<int,string> */
    private function tables(Connection $connection): array
    {
        $tables = [];
        foreach ($connection->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $tables[] = (string) array_values((array) $row)[0];
        }
        sort($tables, SORT_STRING);

        return $tables;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('The backup directory could not be created: '.$directory);
        }
        if (! is_writable($directory)) {
            throw new RuntimeException('The backup directory is not writable: '.$directory);
        }
    }

    /** @param  resource  $handle */
    private function line($handle, bool $compress, string $text): void
    {
        $written = $compress ? gzwrite($handle, $text."\n") : fwrite($handle, $text."\n");
        if ($written === false) {
            throw new RuntimeException('Writing to the backup file failed; the dump is incomplete.');
        }
    }

    /** @param  resource  $handle */
    private function close($handle, bool $compress): void
    {
        $compress ? gzclose($handle) : fclose($handle);
    }
}
