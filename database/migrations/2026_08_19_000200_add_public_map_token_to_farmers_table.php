<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->string('public_map_token', 40)
                ->nullable()
                ->unique()
                ->after('profile_photo_path');
        });

        DB::table('farmers')
            ->whereNull('public_map_token')
            ->orderBy('id')
            ->chunkById(100, function ($farmers) {
                foreach ($farmers as $farmer) {
                    DB::table('farmers')
                        ->where('id', $farmer->id)
                        ->update(['public_map_token' => Str::random(40)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropUnique(['public_map_token']);
            $table->dropColumn('public_map_token');
        });
    }
};
