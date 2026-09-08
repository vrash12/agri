<?php

namespace Tests\Support;

use App\Models\Province;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait ReferenceProvinceSchema
{
    private function createProvinceSchema(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        foreach (['users', 'municipalities', 'audit_logs'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->unsignedBigInteger('province_id')->nullable());
        }
        foreach (['Tarlac', 'Benguet', 'Bulacan'] as $name) {
            Province::create(['name' => $name, 'is_active' => true]);
        }
    }

    private function referenceProvinceId(string $name): int
    {
        return (int) Province::where('name', $name)->sole()->id;
    }
}
