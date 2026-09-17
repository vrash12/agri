<?php

namespace Tests\Support;

use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The smallest schema a rendered view now needs.
 *
 * Presentation suites render with unsaved fixtures and no operational data. Since
 * province supervision was added, two things in a rendered page still reach the
 * database: `User::hasUsableScope()`, which confirms an account's province or its
 * municipality's supervising province, and the `province` relation behind labels
 * such as "<province> Administration". Both fail closed without a row to find,
 * which silently hides navigation and headings the test is asserting on.
 *
 * These two tables and two rows satisfy those lookups. No operational table is
 * created, so a view that tries to query records still fails loudly.
 */
trait PresentationProvinceSchema
{
    private int $presentationProvinceId;

    private int $presentationMunicipalityId;

    private function createPresentationScope(string $province = 'Tarlac'): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->string('code')->unique();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->presentationProvinceId = (int) Province::query()
            ->create(['name' => $province, 'is_active' => true])->id;

        // The fixtures place every synthetic account in municipality 1.
        $this->presentationMunicipalityId = (int) Municipality::withoutEvents(
            fn (): Municipality => Municipality::query()->create([
                'name' => 'Preview Municipality',
                'province' => $province,
                'code' => 'PREVIEW',
                'province_id' => $this->presentationProvinceId,
                'is_active' => true,
            ])
        )->id;
    }
}
