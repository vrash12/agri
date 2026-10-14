<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_risk_source_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->foreignId('farm_plot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            $table->string('metric', 64);
            $table->date('observed_on');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('value', 14, 4)->nullable();
            $table->decimal('baseline_value', 14, 4)->nullable();
            $table->decimal('anomaly_percent', 9, 2)->nullable();
            $table->string('unit', 24)->nullable();
            $table->string('status', 20)->default('available');
            $table->string('source_label', 180);
            $table->string('source_url', 500);
            $table->json('metadata')->nullable();
            $table->string('source_key', 64)->unique();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->index(['municipality_id', 'provider', 'metric', 'observed_on'], 'climate_source_lookup');
            $table->index(['farm_plot_id', 'provider', 'observed_on'], 'climate_source_plot_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_risk_source_readings');
    }
};
