<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recorded harvest, for any commodity.
 *
 * Until now the only harvest figures in this system lived on a rice seed release:
 * `avg_area_harvested_ha`, `total_production_bags` and `avg_weight_per_bag_kg`. That
 * shape can only ever answer for rice, and only for farmers who happened to receive
 * seed — corn, vegetables, fisheries and livestock had nowhere to go at all, and a
 * farmer who planted from their own seed could not be recorded.
 *
 * A harvest is its own event. It belongs to a farmer and a municipality, states its
 * commodity and the season it was taken in, and carries a quantity with the unit that
 * quantity was measured in. It is deliberately not attached to a release: what came
 * out of the ground is not a property of what was handed out.
 *
 * Additive and reversible, per AGENTS.md section 9. Nothing existing is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('harvest_records')) {
            return;
        }

        Schema::create('harvest_records', function (Blueprint $table) {
            $table->id();

            // Scope, on the same column name every other operational table uses so
            // MunicipalityAccess can restrict it without a special case.
            $table->unsignedBigInteger('municipality_id')->index();
            $table->unsignedBigInteger('farmer_id')->nullable()->index();

            // Optional: which parcel this came off, when the office knows.
            $table->unsignedBigInteger('farm_plot_id')->nullable()->index();

            $table->string('commodity', 40)->index();
            $table->string('variety', 120)->nullable();

            // Season is stated, never inferred from a date — the same rule the
            // assistance register already follows.
            $table->string('season', 10)->nullable();
            $table->unsignedSmallInteger('harvest_year')->nullable()->index();
            $table->date('date_harvested')->nullable()->index();

            $table->decimal('area_harvested_ha', 12, 4)->nullable();

            // Quantity carries its own unit. Nothing in this system converts between
            // units, so a total is only ever meaningful within one of them.
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('quantity_unit', 20)->nullable();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            // The dashboard trend groups by commodity within a period inside a scope.
            $table->index(['municipality_id', 'commodity', 'harvest_year'], 'harvest_scope_commodity_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_records');
    }
};
