<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('evaluation_expires_at')->nullable();
            $table->boolean('evaluation_password_pending')->default(false);
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'gis_evaluator')->update(['is_active' => false]);
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['evaluation_expires_at', 'evaluation_password_pending']));
    }
};
