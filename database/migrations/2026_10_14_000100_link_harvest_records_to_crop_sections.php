<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Give a harvest an optional, explicit reference to one saved planted section.
 *
 * Planted sections are currently stored as a JSON projection on
 * `parcel_crop_seasons`, so the reference carries both the parent season id and a
 * server-generated area key. It is intentionally nullable: old harvests, municipal
 * totals and release-projected rice harvests have no section attribution.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('harvest_records') || ! Schema::hasTable('parcel_crop_seasons')) {
            return;
        }

        Schema::table('harvest_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('harvest_records', 'parcel_crop_season_id')) {
                $table->unsignedBigInteger('parcel_crop_season_id')->nullable()->after('farm_plot_id');
                $table->index('parcel_crop_season_id', 'harvest_records_crop_season_index');
                $table->foreign('parcel_crop_season_id', 'harvest_records_crop_season_fk')
                    ->references('id')->on('parcel_crop_seasons')->nullOnDelete();
            }
            if (! Schema::hasColumn('harvest_records', 'planted_area_key')) {
                $table->string('planted_area_key', 64)->nullable()->after('parcel_crop_season_id');
                $table->index(['parcel_crop_season_id', 'planted_area_key'], 'harvest_records_crop_section_index');
            }
        });

        // Existing JSON marks have no identity. Add one without changing their
        // geometry or display values so future edits and harvest links are stable.
        if (! Schema::hasColumn('parcel_crop_seasons', 'planted_areas')) {
            return;
        }

        DB::table('parcel_crop_seasons')->select(['id', 'planted_areas'])->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $areas = is_string($row->planted_areas)
                    ? json_decode($row->planted_areas, true)
                    : $row->planted_areas;

                if (! is_array($areas) || $areas === []) {
                    continue;
                }

                $changed = false;
                foreach ($areas as &$area) {
                    if (! is_array($area)) {
                        continue;
                    }
                    if (! isset($area['area_key']) || ! is_string($area['area_key']) || $area['area_key'] === '') {
                        $area['area_key'] = (string) Str::uuid();
                        $changed = true;
                    }
                }
                unset($area);

                if ($changed) {
                    DB::table('parcel_crop_seasons')->where('id', $row->id)->update([
                        'planted_areas' => json_encode($areas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('harvest_records')) {
            return;
        }

        Schema::table('harvest_records', function (Blueprint $table): void {
            if (Schema::hasColumn('harvest_records', 'parcel_crop_season_id') && Schema::hasColumn('harvest_records', 'planted_area_key')) {
                $table->dropIndex('harvest_records_crop_section_index');
            }
            if (Schema::hasColumn('harvest_records', 'parcel_crop_season_id')) {
                $table->dropForeign('harvest_records_crop_season_fk');
                $table->dropIndex('harvest_records_crop_season_index');
                $table->dropColumn('parcel_crop_season_id');
            }
            if (Schema::hasColumn('harvest_records', 'planted_area_key')) {
                $table->dropColumn('planted_area_key');
            }
        });
    }
};
