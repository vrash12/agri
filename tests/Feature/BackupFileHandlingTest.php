<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceIdleSession;
use App\Models\BackupFile;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

/**
 * Backup Folder files are uploaded by staff and served back from the application's
 * own origin, so what goes in has to be bounded and what comes out must never be
 * rendered as markup.
 */
class BackupFileHandlingTest extends TestCase
{
    use ReferenceProvinceSchema;

    private User $staff;

    private Municipality $municipality;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Cache::clear();
        Storage::fake('local');

        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('backup_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->string('disk');
            $table->string('folder')->nullable();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            $table->string('sha256')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        $this->createProvinceSchema();

        $province = Province::query()->where('name', 'Tarlac')->sole();
        $this->municipality = Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => 'Backup Test Municipality', 'code' => 'BKPTEST', 'province' => 'Tarlac',
            'province_id' => $province->id, 'is_active' => true,
        ])->refresh());
        $this->staff = User::withoutEvents(fn () => User::query()->create([
            'name' => 'Backup Test Head', 'email' => 'backup-head@example.test',
            'password' => Hash::make('unused-test-placeholder'), 'role' => User::ROLE_MUNICIPAL_HEAD,
            'municipality_id' => $this->municipality->id, 'province_id' => $province->id, 'is_active' => true,
        ])->refresh());
    }

    public function test_a_markup_upload_is_refused_and_nothing_is_stored(): void
    {
        $response = $this->actor()->post(route('backups.store'), [
            'files' => [UploadedFile::fake()->createWithContent('notice.html', '<script>alert(1)</script>')],
        ]);

        $response->assertSessionHasErrors('files.0');
        $this->assertSame(0, BackupFile::query()->count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_an_executable_upload_is_refused(): void
    {
        $this->actor()->post(route('backups.store'), [
            'files' => [UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;')],
        ])->assertSessionHasErrors('files.0');

        $this->assertSame(0, BackupFile::query()->count());
    }

    public function test_a_folder_cannot_climb_out_of_the_backups_directory(): void
    {
        $this->actor()->post(route('backups.store'), [
            'folder' => '../../../public/uploads',
            'files' => [UploadedFile::fake()->createWithContent('records.txt', 'safe content')],
        ])->assertSessionHasErrors('folder');

        $this->assertSame(0, BackupFile::query()->count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_an_ordinary_folder_is_still_accepted(): void
    {
        $this->actor()->post(route('backups.store'), [
            'folder' => 'Assistance Records/2026',
            'files' => [UploadedFile::fake()->createWithContent('records.txt', 'safe content')],
        ])->assertSessionHasNoErrors();

        $record = BackupFile::query()->sole();
        $this->assertSame('Assistance Records/2026', $record->folder);
        $this->assertStringStartsWith('backups/Assistance Records/2026/', $record->path);
    }

    public function test_the_recorded_type_is_detected_from_the_file_not_the_browser_claim(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bkp');
        file_put_contents($path, "plain text content\n");
        // A browser is free to claim anything; the upload declares itself as markup.
        $lying = new UploadedFile($path, 'records.txt', 'text/html', null, true);

        $this->actor()->post(route('backups.store'), ['files' => [$lying]])
            ->assertSessionHasNoErrors();

        $record = BackupFile::query()->sole();
        $this->assertNotSame('text/html', $record->mime);
        $this->assertStringStartsWith('text/plain', $record->mime);
    }

    public function test_a_text_file_is_streamed_as_inert_plain_text(): void
    {
        $record = $this->storedFile('daily-notes.txt', 'text/plain', 'operational notes');

        $response = $this->actor()->get(route('backups.stream', $record->id));

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_a_stored_markup_file_is_downloaded_instead_of_rendered(): void
    {
        // A record from before uploads were bounded: the browser's claimed type is in the database.
        $record = $this->storedFile('legacy-report.html', 'text/html', '<script>alert(1)</script>');

        $response = $this->actor()->get(route('backups.stream', $record->id));

        $response->assertOk();
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        // A download renders nothing, so it is fully sandboxed rather than left to
        // inherit the interface's own policy.
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $policy);
        $this->assertStringContainsString('sandbox', $policy);
    }

    public function test_a_previewable_file_keeps_its_own_policy_and_can_never_run_script(): void
    {
        $record = $this->storedFile('summary.pdf', 'application/pdf', '%PDF-1.4 test');

        $policy = $this->actor()->get(route('backups.stream', $record->id))
            ->headers->get('Content-Security-Policy');

        // The application-wide policy allows inline script; a stored file must not.
        $this->assertStringContainsString("script-src 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringNotContainsString('unsafe-inline\' \'unsafe-eval', $policy);
        // Not sandboxed, so the PDF and image viewers still render.
        $this->assertStringNotContainsString('sandbox', $policy);
    }

    public function test_a_stored_svg_is_also_downloaded_instead_of_rendered(): void
    {
        $record = $this->storedFile('logo.svg', 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $response = $this->actor()->get(route('backups.stream', $record->id));

        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_a_pdf_is_still_previewable_inline(): void
    {
        $record = $this->storedFile('report.pdf', 'application/pdf', '%PDF-1.4 test');

        $response = $this->actor()->get(route('backups.stream', $record->id));

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_a_file_owned_by_another_municipality_stays_unreachable(): void
    {
        $foreign = Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => 'Other Municipality', 'code' => 'OTHERBKP', 'province' => 'Tarlac',
            'province_id' => $this->referenceProvinceId('Tarlac'), 'is_active' => true,
        ])->refresh());
        $record = $this->storedFile('their-notes.txt', 'text/plain', 'not yours', $foreign);

        $this->actor()->get(route('backups.stream', $record->id))->assertForbidden();
    }

    private function actor(): self
    {
        return $this->actingAs($this->staff)->withSession([
            EnforceIdleSession::LAST_ACTIVITY_KEY => now()->timestamp,
        ]);
    }

    private function storedFile(
        string $originalName,
        string $mime,
        string $contents,
        ?Municipality $municipality = null
    ): BackupFile {
        $path = 'backups/2026/09/bkp_test_'.$originalName;
        Storage::disk('local')->put($path, $contents);

        return BackupFile::query()->create([
            'municipality_id' => ($municipality ?? $this->municipality)->id,
            'disk' => 'local',
            'folder' => '2026/09',
            'original_name' => $originalName,
            'stored_name' => basename($path),
            'path' => $path,
            'size' => strlen($contents),
            'mime' => $mime,
            'sha256' => hash('sha256', $contents),
            'uploaded_by' => $this->staff->id,
        ])->refresh();
    }
}
