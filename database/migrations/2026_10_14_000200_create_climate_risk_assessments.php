<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_risk_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->string('area_key', 64);
            $table->string('barangay', 120)->nullable();
            $table->string('risk_level', 20);
            $table->string('drought_status', 24);
            $table->string('rainfall_outlook', 24)->default('not_recorded');
            $table->date('observed_on');
            $table->date('valid_until');
            $table->string('source_type', 20);
            $table->string('source_label', 180);
            $table->string('source_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['municipality_id', 'area_key'], 'climate_risk_area_unique');
            $table->index(['municipality_id', 'valid_until', 'observed_on'], 'climate_risk_current_index');
        });
        Schema::create('climate_risk_assessment_plots', function (Blueprint $table): void {
            $table->foreignId('climate_risk_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_plot_id')->constrained()->cascadeOnDelete();
            $table->primary(['climate_risk_assessment_id', 'farm_plot_id'], 'climate_risk_plot_primary');
            $table->index('farm_plot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_risk_assessment_plots');
        Schema::dropIfExists('climate_risk_assessments');
    }
};
