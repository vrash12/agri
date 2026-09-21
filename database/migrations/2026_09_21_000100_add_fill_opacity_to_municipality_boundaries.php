<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipality_boundaries', function (Blueprint $table) {
            $table->decimal('fill_opacity', 3, 2)->default(0.20);
        });
    }

    public function down(): void
    {
        Schema::table('municipality_boundaries', function (Blueprint $table) {
            $table->dropColumn('fill_opacity');
        });
    }
};
