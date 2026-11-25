<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive archive of period reports. Rows hold aggregate figures only, never
 * farmer names, identifiers, addresses or record text, so dropping the table on
 * rollback loses no operational data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('period_type', 8);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('scope_type', 16);
            $table->foreignId('municipality_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('municipality_ids');
            $table->string('title', 200);
            $table->json('summary');
            $table->string('source', 12);
            // Set only for scheduled reports so a repeated run cannot add a second copy.
            $table->string('dedupe_key', 120)->nullable()->unique();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['scope_type', 'municipality_id', 'period_start'], 'generated_reports_municipality_index');
            $table->index(['scope_type', 'province_id', 'period_start'], 'generated_reports_province_index');
            $table->index(['scope_type', 'region_id', 'period_start'], 'generated_reports_region_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
