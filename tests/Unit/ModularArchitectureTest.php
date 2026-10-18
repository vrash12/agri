<?php

namespace Tests\Unit;

use App\Modules\Land\Application\RegionOneSampleData;
use App\Modules\ModuleMap;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ModularArchitectureTest extends TestCase
{
    public function test_persisted_import_identity_survives_the_namespace_move(): void
    {
        $this->assertSame('App\\Support\\RegionOneSampleData', RegionOneSampleData::AUDIT_TYPE);
    }

    public function test_modules_use_declared_public_dependencies_and_never_import_the_http_shell(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'ModuleMap.php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            preg_match('/namespace App\\\\Modules\\\\(\w+)\\\\/', $source, $owner);
            $module = $owner[1] ?? '';
            $this->assertArrayHasKey($module, ModuleMap::IMPORTS, $file->getPathname());
            foreach (token_get_all($source) as $token) {
                if (! is_array($token) || ! in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }
                $name = ltrim($token[1], '\\');
                if ($module === 'Reporting') {
                    $this->assertFalse(str_starts_with($name, 'Laravel\\Ai\\') || $name === 'Illuminate\\Support\\Facades\\Http',
                        'Local reporting must not transmit records through an AI/HTTP client');
                }
                $this->assertFalse(str_starts_with($name, 'App\\Http\\'), "{$module} imports HTTP adapter {$name}");
                $this->assertFalse(str_starts_with($name, 'App\\Providers\\'), "{$module} imports composition root {$name}");
                if (str_starts_with($name, 'App\\Support\\')) {
                    $this->assertContains(substr($name, strlen('App\\Support\\')), ModuleMap::SHARED_SUPPORT);
                }
                if (preg_match('/^App\\\\Modules\\\\(\w+)\\\\(?:Application|Infrastructure|Contracts)\\\\/', $name, $target)
                    && $target[1] !== $module) {
                    $this->assertContains($name, ModuleMap::IMPORTS[$module], "Undeclared {$module} dependency: {$name}");
                }
            }
        }
    }

    public function test_shared_support_contains_only_platform_capabilities(): void
    {
        $files = glob(dirname(__DIR__, 2).'/app/Support/*.php');
        $names = array_map(fn ($file) => basename($file, '.php'), $files);
        $this->assertEqualsCanonicalizing(ModuleMap::SHARED_SUPPORT, $names);
    }

    public function test_concrete_module_dependencies_are_acyclic(): void
    {
        $walk = function (string $module, array $path = []) use (&$walk): void {
            $this->assertNotContains($module, $path, 'Concrete module cycle: '.implode(' -> ', [...$path, $module]));
            foreach (ModuleMap::IMPORTS[$module] as $dependency) {
                if (str_contains($dependency, '\\Contracts\\')) {
                    continue;
                }
                $target = explode('\\', $dependency)[2];
                $walk($target, [...$path, $module]);
            }
        };
        foreach (array_keys(ModuleMap::IMPORTS) as $module) {
            $walk($module);
        }
    }

    public function test_shared_observability_has_no_business_or_http_dependencies(): void
    {
        foreach (glob(dirname(__DIR__, 2).'/app/Platform/Observability/*.php') as $file) {
            $source = file_get_contents($file);
            foreach (['App\\Modules\\', 'App\\Models\\', 'App\\Http\\', 'App\\Providers\\'] as $dependency) {
                $this->assertStringNotContainsString($dependency, $source);
            }
        }
    }
}
