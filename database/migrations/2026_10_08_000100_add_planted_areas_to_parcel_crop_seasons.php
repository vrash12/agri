<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_crop_seasons', function (Blueprint $table): void {
            $table->json('planted_areas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parcel_crop_seasons', fn (Blueprint $table) => $table->dropColumn('planted_areas'));
    }
};
