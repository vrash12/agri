<?php

namespace Tests\Unit;

use App\Support\ConcurrentWrite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConcurrentWriteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.concurrency_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('concurrency_testing');

        Schema::connection('concurrency_testing')->create(
            'concurrency_records',
            function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            }
        );
    }

    protected function tearDown(): void
    {
        DB::disconnect('concurrency_testing');

        parent::tearDown();
    }

    public function test_a_current_version_can_update_a_locked_record(): void
    {
        $record = ConcurrentWriteRecord::create(['name' => 'Original']);
        $version = ConcurrentWrite::version($record);

        app(ConcurrentWrite::class)->execute(
            $record,
            $version,
            function (ConcurrentWriteRecord $current): void {
                $current->update(['name' => 'Saved safely']);
            }
        );

        $this->assertSame('Saved safely', $record->fresh()->name);
    }

    public function test_a_stale_version_cannot_overwrite_a_newer_update(): void
    {
        $record = ConcurrentWriteRecord::create(['name' => 'Original']);
        $staleVersion = ConcurrentWrite::version($record);
        $record->update(['name' => 'Changed by another user']);

        try {
            app(ConcurrentWrite::class)->execute(
                $record,
                $staleVersion,
                fn (ConcurrentWriteRecord $current) => $current->update([
                    'name' => 'Stale overwrite',
                ])
            );
            $this->fail('A stale update should raise a validation conflict.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                '_record_version',
                $exception->errors()
            );
        }

        $this->assertSame(
            'Changed by another user',
            $record->fresh()->name
        );
    }

    public function test_a_missing_version_is_rejected_before_writing(): void
    {
        $record = ConcurrentWriteRecord::create(['name' => 'Original']);

        $this->expectException(ValidationException::class);

        app(ConcurrentWrite::class)->execute(
            $record,
            null,
            fn (ConcurrentWriteRecord $current) => $current->update([
                'name' => 'Should not save',
            ])
        );
    }

    public function test_an_immediate_action_uses_the_current_locked_row(): void
    {
        $record = ConcurrentWriteRecord::create(['name' => 'Original']);
        $record->update(['name' => 'Newest value']);

        $seen = app(ConcurrentWrite::class)->locked(
            $record,
            function (ConcurrentWriteRecord $current): string {
                $name = $current->name;
                $current->delete();

                return $name;
            }
        );

        $this->assertSame('Newest value', $seen);
        $this->assertNull($record->fresh());
    }
}

class ConcurrentWriteRecord extends Model
{
    protected $connection = 'concurrency_testing';

    protected $table = 'concurrency_records';

    protected $guarded = [];
}
