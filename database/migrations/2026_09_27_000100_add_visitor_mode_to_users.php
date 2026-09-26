<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'visitor_mode')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('visitor_mode')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'visitor_mode')) {
            return;
        }

        // Never leave a visitor account as an unrestricted Super Admin when this
        // compatibility column is rolled back on an older deployment.
        \DB::table('users')->where('visitor_mode', true)->update([
            'role' => 'provincial_staff',
            'is_active' => false,
            'visitor_mode' => false,
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('visitor_mode');
        });
    }
};
