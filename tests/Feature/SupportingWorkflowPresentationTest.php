<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackupFile;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SupportingWorkflowPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
        DB::purge('sqlite');
        $this->withViewErrors([]);
    }

    public function test_account_edit_retains_record_version_and_reveals_password_errors(): void
    {
        $manager = $this->staff(User::ROLE_SUPER_ADMIN);
        $this->actingAs($manager);
        $account = $this->staff(User::ROLE_MUNICIPAL_STAFF);
        $account->exists = true;
        $account->updated_at = '2026-01-01 00:00:00';
        $data = $this->accountData($account, false);
        $html = (string) $this->view('admins.edit', $data);
        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//details[@id="accountPasswordSection" and not(@open)]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="_record_version" and string-length(@value)>0]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="password" and @required]')->length);

        $data['errors'] = (new ViewErrorBag())->put('default', new MessageBag(['password' => 'The confirmation does not match.']));
        $xpath = $this->xpath((string) $this->view('admins.edit', $data));
        $this->assertSame(1, $xpath->query('//details[@id="accountPasswordSection" and @open]')->length);
    }

    public function test_account_create_shows_required_password_and_locks_municipal_scope(): void
    {
        $this->actingAs($this->staff(User::ROLE_MUNICIPAL_HEAD));
        $html = (string) $this->view('admins.create', $this->accountData(new User(), true));
        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//details[@id="accountPasswordSection" and @open]')->length);
        $this->assertSame(2, $xpath->query('//input[@type="password" and @required]')->length);
        $this->assertSame(1, $xpath->query('//input[@type="hidden" and @name="role" and @value="municipal_staff"]')->length);
        $this->assertSame(1, $xpath->query('//input[@type="hidden" and @name="municipality_id" and @value="1"]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="role"]')->length);
    }

    public function test_backup_directory_keeps_actions_and_places_technical_details_in_disclosures(): void
    {
        $staff = $this->staff(User::ROLE_MUNICIPAL_STAFF);
        $this->actingAs($staff);
        $file = new BackupFile(['municipality_id' => 1, 'original_name' => 'Sample report.pdf', 'folder' => 'reports', 'mime' => 'application/pdf', 'size' => 1024, 'sha256' => str_repeat('a', 64), 'notes' => 'Synthetic fixture']);
        $file->id = 5;
        $file->setRelation('municipality', $staff->municipality)->setRelation('uploader', $staff);
        $html = (string) $this->view('backups.index', [
            'files' => new LengthAwarePaginator([$file], 1, 20),
            'folders' => collect(['reports']), 'uploaders' => collect([$staff]), 'extPresets' => ['pdf'],
            'municipalities' => collect([$staff->municipality]), 'canChooseMunicipality' => false,
            'selectedMunicipalityId' => null, 'filteredFileCount' => 1, 'filteredBytes' => 1024,
            'hashedFileCount' => 1, 'filteredFolderCount' => 1, 'latestUploadAt' => null,
        ]);
        $xpath = $this->xpath($html);

        $this->assertSame(5, $xpath->query('//table[contains(@class,"backup-table")]/thead/tr/th')->length);
        $this->assertSame(1, $xpath->query('//details[contains(@class,"backup-file-details") and not(@open)]')->length);
        $this->assertStringContainsString(route('backups.preview', $file), $html);
        $this->assertStringContainsString(route('backups.download', $file), $html);
        $this->assertSame(1, $xpath->query('//input[@type="file" and @name="files[]" and @required]')->length);
        $this->assertSame(1, $xpath->query('//details[@id="backupUpload" and not(@open)]')->length);
    }

    public function test_audit_filters_remain_submittable_and_reports_do_not_crowd_the_ledger(): void
    {
        $this->actingAs($this->staff(User::ROLE_SUPER_ADMIN));
        $filters = ['q' => '', 'event' => '', 'module' => 'Farmers', 'municipality_id' => '', 'user_id' => '', 'date_from' => '', 'date_to' => '', 'per_page' => 15];
        $html = (string) $this->view('audit_logs.index', [
            'records' => new LengthAwarePaginator([], 0, 15),
            'stats' => ['total' => 0, 'today' => 0, 'seven_days' => 0, 'alerts' => 0],
            'eventCounts' => collect(), 'moduleCounts' => collect(), 'eventLabels' => AuditLog::EVENT_LABELS,
            'modules' => collect(['Farmers']), 'municipalities' => collect(), 'users' => collect(), 'filters' => $filters,
        ]);
        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//form//details[contains(@class,"audit-advanced") and @open]')->length);
        $this->assertSame(1, $xpath->query('//form//select[@name="module"]/option[@value="Farmers" and @selected]')->length);
        $this->assertSame(1, $xpath->query('//details[contains(@class,"audit-reports") and not(@open)]')->length);
        $this->assertStringContainsString('Module: Farmers', $html);
        $this->assertStringContainsString('Export filtered CSV', $html);
    }

    private function staff(string $role): User
    {
        $municipality = new Municipality(['name' => 'Sample Municipality', 'is_active' => true]);
        $municipality->id = 1;
        $user = new User(['name' => 'Sample Staff', 'email' => 'sample@example.test', 'role' => $role, 'municipality_id' => 1, 'is_active' => true]);
        $user->id = $role === User::ROLE_SUPER_ADMIN ? 2 : 1;
        $user->setRelation('municipality', $municipality);

        return $user;
    }

    private function accountData(User $account, bool $municipalManager): array
    {
        return [
            'account' => $account, 'isMunicipalHeadManager' => $municipalManager, 'isOwnAccount' => false,
            'municipalities' => collect([$this->staff(User::ROLE_MUNICIPAL_STAFF)->municipality]),
            'roleOptions' => [User::ROLE_MUNICIPAL_STAFF => 'Municipal Staff', User::ROLE_PROVINCIAL_STAFF => 'Provincial Staff'],
        ];
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }
}
