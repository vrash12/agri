<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_field_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_plot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('crop_year');
            $table->string('season', 8);
            $table->string('crop', 20);
            $table->date('observed_on');
            $table->string('context_fingerprint', 64);
            $table->json('evidence');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['farm_plot_id', 'crop_year', 'season', 'id'], 'parcel_field_period_index');
            $table->index(['municipality_id', 'observed_on'], 'parcel_field_office_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_field_assessments');
    }
};
