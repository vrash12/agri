<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('rice_seed_distributions')
            || ! Schema::hasTable('farmers')
            || ! Schema::hasColumn(
                'rice_seed_distributions',
                'municipality_id'
            )
        ) {
            return;
        }

        DB::table('rice_seed_distributions as distributions')
            ->join(
                'farmers',
                'farmers.id',
                '=',
                'distributions.farmer_id'
            )
            ->whereNull('distributions.municipality_id')
            ->whereNotNull('farmers.municipality_id')
            ->select([
                'distributions.id',
                'farmers.municipality_id',
            ])
            ->orderBy('distributions.id')
            ->chunk(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('rice_seed_distributions')
                        ->where('id', $row->id)
                        ->whereNull('municipality_id')
                        ->update([
                            'municipality_id' => $row->municipality_id,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Municipality ownership is business data and must not be erased.
    }
};
