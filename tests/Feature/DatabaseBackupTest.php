<?php

namespace Tests\Feature;

use App\Support\DatabaseBackup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

/**
 * This repository has no complete migration history, so the dump is the only route
 * back from a lost database. These cases run against the real connection because a
 * backup that has only been exercised against a stand-in proves nothing.
 *
 * Reading is all that touches the database; everything written goes to a temporary
 * directory that is removed again.
 */
class DatabaseBackupTest extends TestCase
{
    use DatabaseTransactions;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'agri-backup-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        // Dumps hold farmer personal data; never leave one behind.
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);

        parent::tearDown();
    }

    public function test_a_dump_contains_every_table_and_restores_its_own_row_counts(): void
    {
        $result = app(DatabaseBackup::class)->create($this->directory);

        $this->assertFileExists($result['path']);
        $this->assertGreaterThan(0, $result['bytes']);
        $this->assertSame(64, strlen($result['checksum']));
        $this->assertTrue($result['compressed']);

        // The tables the office cannot lose.
        foreach (['farmers', 'municipalities', 'municipality_boundaries', 'users', 'audit_logs'] as $table) {
            $this->assertArrayHasKey($table, $result['tables'], $table.' is missing from the dump.');
        }
        $this->assertSame(
            \DB::table('farmers')->count(),
            $result['tables']['farmers'],
            'The dump must record the same number of farmers the database holds.'
        );

        $sql = $this->readDump($result['path']);
        foreach (array_keys($result['tables']) as $table) {
            $this->assertStringContainsString('CREATE TABLE `'.$table.'`', $sql);
            $this->assertStringContainsString('DROP TABLE IF EXISTS `'.$table.'`;', $sql);
        }
        // A restore must not be rejected by constraints while tables load.
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 0;', $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 1;', $sql);
    }

    public function test_values_never_break_a_statement_across_lines(): void
    {
        $result = app(DatabaseBackup::class)->create($this->directory);
        $sql = $this->readDump($result['path']);

        foreach (explode("\n", $sql) as $number => $line) {
            if (str_starts_with($line, 'INSERT INTO ')) {
                $this->assertStringEndsWith(
                    ';',
                    $line,
                    'INSERT on line '.($number + 1).' is split across lines, which breaks line-based tooling.'
                );
            }
        }
    }

    public function test_verification_rejects_a_dump_that_lost_a_table(): void
    {
        $backup = app(DatabaseBackup::class);
        $result = $backup->create($this->directory);

        // Passes as written.
        $backup->verify($result['path'], $result['tables']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing table definitions');
        $backup->verify($result['path'], $result['tables'] + ['a_table_that_was_never_dumped' => 1]);
    }

    public function test_verification_rejects_an_empty_file(): void
    {
        mkdir($this->directory, 0775, true);
        $empty = $this->directory.DIRECTORY_SEPARATOR.'empty.sql';
        touch($empty);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or empty');
        app(DatabaseBackup::class)->verify($empty, []);
    }

    public function test_retention_keeps_the_newest_dumps_and_removes_the_rest(): void
    {
        mkdir($this->directory, 0775, true);
        $names = [];
        foreach (['20260101-000000', '20260102-000000', '20260103-000000', '20260104-000000'] as $stamp) {
            $names[$stamp] = $this->directory.DIRECTORY_SEPARATOR.'ag_system-'.$stamp.'.sql.gz';
            file_put_contents($names[$stamp], 'placeholder');
        }

        $removed = app(DatabaseBackup::class)->prune($this->directory, 2);

        $this->assertCount(2, $removed);
        $this->assertFileDoesNotExist($names['20260101-000000']);
        $this->assertFileDoesNotExist($names['20260102-000000']);
        $this->assertFileExists($names['20260103-000000']);
        $this->assertFileExists($names['20260104-000000']);
    }

    public function test_retention_of_zero_keeps_every_dump(): void
    {
        mkdir($this->directory, 0775, true);
        $file = $this->directory.DIRECTORY_SEPARATOR.'ag_system-20260101-000000.sql.gz';
        file_put_contents($file, 'placeholder');

        $this->assertSame([], app(DatabaseBackup::class)->prune($this->directory, 0));
        $this->assertFileExists($file);
    }

    public function test_the_command_reports_success_and_writes_a_usable_file(): void
    {
        $this->artisan('db:backup', ['--path' => $this->directory])
            ->expectsOutputToContain('Backed up')
            ->assertSuccessful();

        $files = glob($this->directory.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];
        $this->assertCount(1, $files);
        $this->assertStringContainsString('CREATE TABLE `farmers`', $this->readDump($files[0]));
    }

    private function readDump(string $path): string
    {
        return str_ends_with($path, '.gz')
            ? (string) gzdecode((string) file_get_contents($path))
            : (string) file_get_contents($path);
    }
}
