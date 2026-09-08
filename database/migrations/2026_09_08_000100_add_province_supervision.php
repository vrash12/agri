<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->foreignId('province_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('province_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['province_id', 'role', 'is_active']);
        });
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('province_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['province_id', 'created_at']);
        });

        // Preserve the legacy display province; use an explicit ID for access control.
        foreach (DB::table('municipalities')->select('province')->distinct()->pluck('province') as $displayName) {
            $name = trim((string) $displayName);
            if ($name === '') {
                continue;
            }
            $provinceId = DB::table('provinces')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
            if (! $provinceId) {
                $provinceId = DB::table('provinces')->insertGetId([
                    'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('municipalities')->where('province', $displayName)->update(['province_id' => $provinceId]);
        }

        // Historical municipality events have known ownership. Global/unknown events stay owner-only.
        DB::table('audit_logs')->whereNotNull('municipality_id')->orderBy('id')->chunkById(500, function ($logs): void {
            $provinces = DB::table('municipalities')->whereIn('id', $logs->pluck('municipality_id'))
                ->pluck('province_id', 'id');
            foreach ($logs as $log) {
                DB::table('audit_logs')->where('id', $log->id)->update([
                    'province_id' => $provinces[$log->municipality_id] ?? null,
                ]);
            }
        });
        // User assignments are deliberately explicit; use province-access:setup after migration.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot add/drop foreign keys on existing tables through Laravel 9.
            Schema::table('audit_logs', fn (Blueprint $table) => $table->dropIndex(['province_id', 'created_at']));
            Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['province_id', 'role', 'is_active']));
            foreach (['audit_logs', 'users', 'municipalities'] as $name) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('province_id'));
            }
            Schema::dropIfExists('provinces');

            return;
        }
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['province_id', 'created_at']);
            $table->dropConstrainedForeignId('province_id');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['province_id', 'role', 'is_active']);
            $table->dropConstrainedForeignId('province_id');
        });
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('province_id');
        });
        Schema::dropIfExists('provinces');
    }
};
