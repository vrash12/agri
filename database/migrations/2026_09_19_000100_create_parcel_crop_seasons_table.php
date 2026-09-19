<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_crop_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->foreignId('farm_plot_id')->constrained('farm_plots')->cascadeOnDelete();
            $table->unsignedSmallInteger('crop_year');
            $table->string('season', 12);
            $table->string('crop', 24);
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['farm_plot_id', 'crop_year', 'season'], 'parcel_crop_period_unique');
            $table->index(['municipality_id', 'crop_year', 'season', 'crop'], 'parcel_crop_scope_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_crop_seasons');
    }
};
