<?php

namespace App\Modules\Farmers\Application;

use App\Models\FarmerRegistrySourceRow;
use App\Models\User;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

final class FarmerRegistrySources
{
    public const SECTIONS = [
        FarmerRegistrySourceRow::STATUS_OUTSIDE_LGU => 'Outside LGU',
        FarmerRegistrySourceRow::STATUS_DELISTED => 'Delisted',
    ];

    public function __construct(private MunicipalityAccess $access) {}

    public function available(): bool
    {
        return Schema::hasTable('farmer_registry_source_rows');
    }

    /** @param array<int, int> $municipalityIds */
    private function scoped(User $user, array $municipalityIds): Builder
    {
        return $this->access->scope(FarmerRegistrySourceRow::query(), $user)
            ->whereIn('farmer_registry_source_rows.municipality_id', $municipalityIds)
            ->whereIn('record_status', array_keys(self::SECTIONS));
    }

    /**
     * Counts are workbook rows, not people: repeated commodities/parcels remain separate.
     *
     * @param  array<int, int>  $municipalityIds
     * @return array<string, int>
     */
    public function counts(User $user, array $municipalityIds): array
    {
        $counts = array_fill_keys(array_keys(self::SECTIONS), 0);
        if (! $this->available()) {
            return $counts;
        }

        foreach ($this->scoped($user, $municipalityIds)->selectRaw('record_status, COUNT(*) AS row_count')->groupBy('record_status')->get() as $group) {
            $counts[$group->record_status] = (int) $group->row_count;
        }

        return $counts;
    }

    /** @param array<int, int> $municipalityIds */
    public function directory(User $user, array $municipalityIds, string $status, string $search, int $perPage): LengthAwarePaginator
    {
        $query = $this->scoped($user, $municipalityIds)->where('record_status', $status);
        // Match each name word without database-specific concatenation. JSON paths
        // are fixed here; caller input never controls a field or SQL expression.
        foreach (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($word)).'%';
            $query->where(function (Builder $match) use ($like): void {
                $columns = ['source_ffrs', 'source_rsbsa_no', 'parcel_no'];
                foreach (['FIRST NAME', 'MIDDLE NAME', 'LAST NAME', 'EXT NAME', 'first_name', 'middle_name', 'last_name', 'ext_name'] as $key) {
                    $columns[] = 'payload->'.$key;
                }
                // Explicit ESCAPE is needed for matching literal %/_ on both
                // SQLite and MySQL; JSON text can have a case-sensitive collation.
                foreach ($columns as $column) {
                    $wrapped = $match->getQuery()->getGrammar()->wrap($column);
                    $match->orWhereRaw("LOWER({$wrapped}) LIKE ? ESCAPE '!'", [$like]);
                }
            });
        }

        // A corrupt/stale farmer link must not expose another LGU's identity or
        // give a delisted row an active-card action.
        return $query->leftJoin('farmers', function ($join): void {
            $join->on('farmers.id', '=', 'farmer_registry_source_rows.farmer_id')
                ->on('farmers.municipality_id', '=', 'farmer_registry_source_rows.municipality_id')
                ->where('farmer_registry_source_rows.record_status', FarmerRegistrySourceRow::STATUS_OUTSIDE_LGU);
        })->with('municipality:id,name,province')
            ->orderBy('source_sheet')->orderBy('source_row')->orderBy('farmer_registry_source_rows.id')
            ->paginate($perPage, [
                'farmer_registry_source_rows.id', 'farmer_registry_source_rows.municipality_id',
                'source_sheet', 'source_row', 'record_status', 'source_ffrs', 'source_rsbsa_no', 'parcel_no', 'payload',
                'farmers.id as linked_farmer_id',
            ])->withQueryString()->through(fn (FarmerRegistrySourceRow $row): array => $this->present($row));
    }

    /** @return array<string, mixed> */
    private function present(FarmerRegistrySourceRow $row): array
    {
        $payload = $row->payload ?? [];
        $value = fn (string $upper, string $lower): string => is_scalar($payload[$upper] ?? $payload[$lower] ?? null)
            ? trim((string) ($payload[$upper] ?? $payload[$lower])) : '';
        $name = implode(' ', array_filter([
            $value('FIRST NAME', 'first_name'), $value('MIDDLE NAME', 'middle_name'),
            $value('LAST NAME', 'last_name'), $value('EXT NAME', 'ext_name'),
        ], fn (string $part): bool => $part !== ''));
        $address = implode(', ', array_filter([
            $value('FARMER ADDRESS 1', 'farmer_address_bgy'), $value('FARMER ADDRESS 2', 'farmer_address_mun'),
            $value('FARMER ADDRESS 3', 'farmer_address_prv'),
        ], fn (string $part): bool => $part !== ''));
        $reasons = [];
        foreach (['duplicate' => 'Duplicate', 'deceased' => 'Deceased', 'inactive' => 'Inactive'] as $key => $label) {
            if (in_array(mb_strtolower($value(strtoupper($key), $key)), ['1', 'yes', 'true', $key], true)) {
                $reasons[] = $label;
            }
        }

        return [
            'name' => $name ?: 'Name not recorded', 'address' => $address ?: 'Address not recorded',
            'municipality' => $row->municipality?->name, 'province' => $row->municipality?->province,
            'ffrs' => $row->source_ffrs, 'rsbsa' => $row->source_rsbsa_no, 'parcel' => $row->parcel_no,
            'sheet' => $row->source_sheet, 'row' => $row->source_row, 'reasons' => $reasons,
            'farmer_id' => $row->linked_farmer_id ? (int) $row->linked_farmer_id : null,
            'fields' => $payload,
        ];
    }
}
