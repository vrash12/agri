<?php

namespace App\Support;

use App\Models\Farmer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The beneficiary picker shared by the assistance and harvest forms.
 *
 * Both forms used to render every farmer the account can see into a `<select>`. For
 * Ramos that is 1,546 people: 724 KB on the assistance form and 299 KB on the harvest
 * form, sent on every load, mostly to be scrolled past. The registry is searched on
 * the server instead, and the page carries only the farmer already chosen.
 *
 * One class builds the option shape for both the JSON endpoint and the Blade partial,
 * for the same reason `mapFarmerPayload` is shared with the map: the two must never
 * disagree about what a farmer looks like, or an option rendered on page load would
 * describe a farmer differently from the same option fetched a second later.
 *
 * The profile block is opt-in. The assistance form shows a beneficiary preview and
 * needs a contact number and eligibility flags; the harvest form shows a name and
 * does not. Sending the contact number to a form that never displays it would be
 * handing out personal data for nothing.
 */
class FarmerPicker
{
    /**
     * Identity columns: enough to choose the right person and no more.
     */
    private const IDENTITY_COLUMNS = [
        'id', 'municipality_id', 'last_name', 'first_name', 'middle_name', 'ext_name', 'ffrs', 'rsbsa_no',
    ];

    /**
     * Added only when a caller asks for the profile preview.
     */
    private const PROFILE_COLUMNS = [
        'farm_location', 'farm_municipality', 'farm_province', 'farm_area_ha', 'contact_number',
        'is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw',
    ];

    /**
     * Eligibility flags, in the order the preview lists them.
     */
    private const TAGS = [
        'ARB' => 'is_arb',
        '4Ps' => 'is_4ps',
        'IP' => 'is_ip',
        'PWD' => 'is_pwd',
        'SC' => 'is_sc',
        'OFW' => 'is_ofw',
    ];

    /**
     * Below this, a search matches most of a registry. The caller is asked to be more
     * specific rather than handed an arbitrary slice of everyone.
     */
    public const MINIMUM_TERM = 2;

    public const MAXIMUM_LIMIT = 50;

    public function __construct(private MunicipalityAccess $municipalityAccess)
    {
    }

    /**
     * Farmers matching a typed term, within what this account may read.
     *
     * @return array{farmers: array<int, array<string, mixed>>, total: int, returned: int, truncated: bool, needs_more_input: bool}
     */
    public function search(
        User $user,
        string $term,
        ?int $municipalityId = null,
        int $limit = 20,
        bool $withProfile = false
    ): array {
        $term = trim($term);
        $limit = max(1, min($limit, self::MAXIMUM_LIMIT));

        if (mb_strlen($term) < self::MINIMUM_TERM) {
            return [
                'farmers' => [], 'total' => 0, 'returned' => 0,
                'truncated' => false, 'needs_more_input' => true,
            ];
        }

        $query = $this->scoped($user, $municipalityId);

        // Escaped so a name containing % or _ searches for those characters rather
        // than acting as a wildcard.
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

        $query->where(function (Builder $search) use ($like) {
            $search->where('last_name', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('middle_name', 'like', $like)
                ->orWhere('ffrs', 'like', $like)
                ->orWhere('rsbsa_no', 'like', $like);
        });

        $total = (clone $query)->count();

        $farmers = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get($this->columns($withProfile))
            ->map(fn (Farmer $farmer) => $this->option($farmer, $withProfile))
            ->values()
            ->all();

        return [
            'farmers' => $farmers,
            'total' => $total,
            'returned' => count($farmers),
            'truncated' => $total > count($farmers),
            'needs_more_input' => false,
        ];
    }

    /**
     * The options a form renders before anyone types.
     *
     * Just the farmer already chosen, so an existing record opens showing who it is
     * for and saves unchanged without a single search. `$includeAll` is the escape
     * hatch behind the form's "browse the whole registry" link, for an operator who
     * would rather scroll than type, or whose browser is not running the picker.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function initialOptions(
        User $user,
        ?int $selectedId = null,
        bool $includeAll = false,
        bool $withProfile = false
    ): Collection {
        $query = $this->scoped($user, null);

        if (! $includeAll) {
            if (! $selectedId) {
                return collect();
            }

            $query->whereKey($selectedId);
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get($this->columns($withProfile))
            ->map(fn (Farmer $farmer) => $this->option($farmer, $withProfile))
            ->values();
    }

    /**
     * One farmer as the picker shows them.
     *
     * `label` is what a person reads in the list: the name they know, then the
     * identifier that tells two people with the same name apart. `dataset` becomes
     * the select element's data attributes, which is how the assistance form's
     * preview reads the selected beneficiary without knowing where the option came
     * from.
     *
     * @return array<string, mixed>
     */
    public function option(Farmer $farmer, bool $withProfile = false): array
    {
        $name = $this->displayName($farmer);
        $identifier = $farmer->ffrs ?: $farmer->rsbsa_no;

        $dataset = [
            'name' => $name,
            'ffrs' => $identifier ?: 'Not assigned',
            'municipalityId' => (string) $farmer->municipality_id,
        ];

        if ($withProfile) {
            $dataset += [
                'location' => $farmer->farm_location ?: 'Not recorded',
                'municipality' => $farmer->farm_municipality ?: 'Not recorded',
                'province' => $farmer->farm_province ?: 'Not recorded',
                'area' => $farmer->farm_area_ha !== null
                    ? number_format((float) $farmer->farm_area_ha, 2).' ha'
                    : 'Not recorded',
                'contact' => $farmer->contact_number ?: 'Not recorded',
                'tags' => $this->tags($farmer),
            ];
        }

        return [
            'value' => (string) $farmer->id,
            'label' => $identifier ? $name.' — '.$identifier : $name,
            'municipality_id' => (int) $farmer->municipality_id,
            'dataset' => $dataset,
        ];
    }

    public function displayName(Farmer $farmer): string
    {
        $surname = trim((string) $farmer->last_name);
        $rest = trim(collect([$farmer->first_name, $farmer->middle_name, $farmer->ext_name])
            ->filter()
            ->implode(' '));

        if ($surname !== '' && $rest !== '') {
            return $surname.', '.$rest;
        }

        return $surname !== '' ? $surname : ($rest !== '' ? $rest : 'Farmer #'.$farmer->id);
    }

    private function tags(Farmer $farmer): string
    {
        $present = collect(self::TAGS)
            ->filter(fn (string $column) => (bool) $farmer->getAttribute($column))
            ->keys();

        return $present->isEmpty() ? 'None' : $present->implode(', ');
    }

    /**
     * @return array<int, string>
     */
    private function columns(bool $withProfile): array
    {
        return $withProfile
            ? array_merge(self::IDENTITY_COLUMNS, self::PROFILE_COLUMNS)
            : self::IDENTITY_COLUMNS;
    }

    /**
     * Municipality scope first, before any search term or limit, so no query can
     * match a farmer the account may not read.
     */
    private function scoped(User $user, ?int $municipalityId): Builder
    {
        $query = $this->municipalityAccess->scope(Farmer::query(), $user);

        // A submitted municipality only ever narrows what the scope already allows.
        if ($municipalityId !== null && $user->canAccessAllMunicipalities()) {
            $query->where('municipality_id', $municipalityId);
        }

        return $query;
    }
}
