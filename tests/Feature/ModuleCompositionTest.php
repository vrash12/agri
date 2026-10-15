<?php

namespace Tests\Feature;

use App\Modules\Agriculture\Application\RecordHarvest;
use App\Modules\Assistant\Application\AssistantConversation;
use App\Modules\Assistant\Application\OfficeAssistant;
use App\Modules\Assistant\Contracts\OfficeRecordReader;
use App\Modules\Land\Application\CropSectionHarvestGuard;
use App\Modules\Land\Contracts\HarvestSectionGuard;
use App\Modules\Reporting\Application\AssistantRecords;
use App\Policies\OfficeAssistantPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ModuleCompositionTest extends TestCase
{
    public function test_every_public_application_service_resolves_without_provider_requests(): void
    {
        Http::preventStrayRequests();
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules'))) as $file) {
            if ($file->getExtension() !== 'php' || $file->getPathInfo()->getFilename() !== 'Application') {
                continue;
            }
            $class = 'App\\'.str_replace(['/', '\\'], '\\', substr($file->getPathname(), strlen(app_path()) + 1, -4));
            $this->assertInstanceOf($class, app($class));
        }
    }

    public function test_module_contracts_resolve_in_web_and_command_containers(): void
    {
        $this->assertInstanceOf(AssistantRecords::class, app(OfficeRecordReader::class));
        $this->assertInstanceOf(CropSectionHarvestGuard::class, app(HarvestSectionGuard::class));
        $this->assertInstanceOf(AssistantConversation::class, app(AssistantConversation::class));
        $this->assertInstanceOf(RecordHarvest::class, app(RecordHarvest::class));
    }

    public function test_local_reports_and_assistant_entry_share_the_existing_policy(): void
    {
        $this->assertInstanceOf(OfficeAssistantPolicy::class, Gate::getPolicyFor(OfficeRecordReader::class));
        $this->assertInstanceOf(OfficeAssistantPolicy::class, Gate::getPolicyFor(OfficeAssistant::class));
    }
}
