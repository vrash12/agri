<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_registry_source_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
            $table->char('source_file_sha256', 64);
            $table->string('source_sheet', 64);
            $table->unsignedInteger('source_row');
            $table->string('record_status', 32)->default('active');
            $table->string('source_ffrs')->nullable();
            $table->string('source_rsbsa_no')->nullable();
            $table->string('parcel_no')->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->unique(
                ['source_file_sha256', 'source_sheet', 'source_row'],
                'farmer_registry_source_row_unique'
            );
            $table->index(
                ['municipality_id', 'record_status'],
                'farmer_registry_source_scope_status'
            );
            $table->index(['farmer_id', 'source_sheet'], 'farmer_registry_source_farmer');
            $table->index('source_ffrs', 'farmer_registry_source_ffrs');
            $table->index('source_rsbsa_no', 'farmer_registry_source_rsbsa');
            $table->index('parcel_no', 'farmer_registry_source_parcel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_registry_source_rows');
    }
};
