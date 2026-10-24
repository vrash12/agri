<?php

namespace App\Modules;

/** Explicit cross-module APIs; enforced by ModularArchitectureTest. */
final class ModuleMap
{
    public const IMPORTS = [
        'Agriculture' => [Land\Contracts\HarvestSectionGuard::class],
        'AnimalHealth' => [],
        'Assistant' => [Farmers\Application\AssistantDataQuality::class],
        'Climate' => [Land\Application\BarangayBoundaryReferences::class],
        'Farmers' => [Land\Application\BarangayBoundaryReferences::class],
        'Land' => [Agriculture\Application\SeedReleaseQuantity::class],
        'LocalGovernment' => [],
        'Reporting' => [
            Assistant\Contracts\OfficeRecordReader::class,
            Farmers\Application\AssistantDataQuality::class,
            Farmers\Application\FarmerDataQuality::class,
            Farmers\Application\FarmerIdentifier::class,
        ],
    ];

    // Shared platform capabilities; business workflows belong in a module.
    public const SHARED_SUPPORT = [
        'AuditTrail', 'Cdn', 'ConcurrentWrite', 'CsvExport', 'DatabaseBackup',
        'GeoGeometry', 'LocalTime', 'MunicipalityAccess', 'ProviderUsage', 'RegionSupervision', 'ReportDateSql',
    ];
}
