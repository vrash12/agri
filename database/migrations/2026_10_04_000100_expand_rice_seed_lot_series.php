<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->requireColumn();
        $this->changeType('TEXT');
    }

    public function down(): void
    {
        $this->requireColumn();
        $length = DB::connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
        if (DB::table('rice_seed_distributions')->whereRaw($length.'(lot_series) > ?', [120])->exists()) {
            throw new RuntimeException('Cannot restore the 120-character Lot Series limit while longer references exist. Export and review those records first; rollback never truncates them.');
        }

        $this->changeType('VARCHAR(120)');
    }

    private function requireColumn(): void
    {
        if (! Schema::hasColumn('rice_seed_distributions', 'lot_series')) {
            throw new RuntimeException('The rice distribution baseline with Lot Series is required.');
        }
    }

    private function changeType(string $type): void
    {
        $driver = DB::connection()->getDriverName();
        // SQLite already stores VARCHAR as unrestricted text; application limits
        // remain enforced in tests. MySQL/MariaDB requires a real column expansion.
        if ($driver === 'sqlite') {
            return;
        }
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Lot Series expansion requires MySQL/MariaDB.');
        }

        $column = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'rice_seed_distributions')
            ->where('COLUMN_NAME', 'lot_series')
            ->first(['CHARACTER_SET_NAME', 'COLLATION_NAME']);
        $charset = $column->CHARACTER_SET_NAME ?? '';
        $collation = $column->COLLATION_NAME ?? '';
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $charset) || ! preg_match('/^[a-zA-Z0-9_]+$/', $collation)) {
            throw new RuntimeException('Cannot safely preserve the Lot Series character set and collation.');
        }

        // Type is one of the two constants passed above; metadata identifiers are
        // validated. Preserve collation so exact repeat-import matching is unchanged.
        DB::statement("ALTER TABLE `rice_seed_distributions` MODIFY `lot_series` {$type} CHARACTER SET {$charset} COLLATE {$collation} NULL");
    }
};
