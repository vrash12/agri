<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('provinces', function (Blueprint $table): void {
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['region_id', 'role', 'is_active']);
        });
        // Membership and account issuance are explicit operations, never guessed by migration.
    }

    public function down(): void
    {
        // Old application versions must not retain usable identities for an unknown role.
        DB::table('users')->where('role', 'regional_head')->update(['is_active' => false]);
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['region_id', 'role', 'is_active']));
            foreach (['users', 'provinces'] as $name) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('region_id'));
            }
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['region_id', 'role', 'is_active']);
                $table->dropConstrainedForeignId('region_id');
            });
            Schema::table('provinces', fn (Blueprint $table) => $table->dropConstrainedForeignId('region_id'));
        }
        Schema::dropIfExists('regions');
    }
};
