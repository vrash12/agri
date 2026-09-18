<?php

namespace App\Http\Requests;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\User;
use App\Support\MunicipalityAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation and ownership resolution for creating or editing a farmer.
 *
 * `authorize()` runs the same policy the controller used to call, and Laravel runs
 * it before the rules. That ordering is deliberate: an account with no right to
 * write still gets a refusal rather than a field-by-field description of the form.
 *
 * `farmerData()` is the important part: a municipal account's submitted
 * municipality is ignored in favour of its own, and a provincial account must
 * choose one it is entitled to. That decision is made server-side, here, rather
 * than being trusted from the form.
 */
class StoreFarmerRequest extends FormRequest
{
    /**
     * Fields stored as null rather than an empty string.
     */
    private const NULLABLE_TEXT = [
        'rsbsa_no', 'ffrs', 'middle_name', 'ext_name', 'owner_name', 'contact_number',
        'farm_location', 'farm_province', 'farm_municipality', 'ecosystem', 'ecosystem_source',
    ];

    /**
     * Eligibility flags, which must persist as false rather than absent.
     */
    private const FLAGS = ['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'];

    public function authorize(): bool
    {
        $farmer = $this->existingFarmer();

        return (bool) ($farmer
            ? $this->user()?->can('update', $farmer)
            : $this->user()?->can('create', Farmer::class));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $farmerId = $this->existingFarmer()?->id;

        $rules = [
            'rsbsa_no' => ['nullable', 'string', 'max:255', Rule::unique('farmers', 'rsbsa_no')->ignore($farmerId)],
            'ffrs' => ['nullable', 'string', 'max:255', Rule::unique('farmers', 'ffrs')->ignore($farmerId)],

            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'ext_name' => ['nullable', 'string', 'max:50'],
            'owner_name' => ['nullable', 'string', 'max:255'],

            'date_of_birth' => ['nullable', 'date'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:3072',
                'dimensions:min_width=200,min_height=200,max_width=5000,max_height=5000',
            ],
            'remove_profile_photo' => ['nullable', 'boolean'],
            'gender' => ['nullable', Rule::in(Farmer::GENDERS)],

            'farm_location' => ['nullable', 'string', 'max:255'],
            'farm_province' => ['nullable', 'string', 'max:255'],
            'farm_municipality' => ['nullable', 'string', 'max:255'],
            'ecosystem' => ['nullable', 'string', 'max:255'],
            'ecosystem_source' => ['nullable', 'string', 'max:255'],

            'farm_area_ha' => ['nullable', 'numeric', 'min:0'],
        ];

        foreach (self::FLAGS as $flag) {
            $rules[$flag] = ['nullable', 'boolean'];
        }

        // Only a province-wide account picks the owning municipality; a municipal
        // account always writes to its own.
        if ($this->authenticatedUser()->isProvincialUser()) {
            $rules['municipality_id'] = ['required', 'integer'];
        }

        return $rules;
    }

    /**
     * Trim the identifiers before anything is checked against them.
     *
     * `rsbsa_no` and `ffrs` carry unique rules, and those rules compare whatever was
     * submitted. Trimming afterwards meant " FFRS-123 " was checked with its spaces,
     * passed because no stored value matched, and was only then trimmed to a value
     * that did already exist — so the database's unique index rejected the insert and
     * the officer saw a database error instead of a message on the field.
     *
     * Only the identifiers are normalised here. Every other text field keeps its
     * existing handling in `farmerData()`, where trimming affects storage but not
     * whether a check passes.
     */
    protected function prepareForValidation(): void
    {
        foreach (['rsbsa_no', 'ffrs'] as $identifier) {
            if (! $this->has($identifier)) {
                continue;
            }

            $value = $this->input($identifier);
            $value = $value === null ? null : trim((string) $value);

            $this->merge([$identifier => $value === '' ? null : $value]);
        }
    }

    /**
     * The validated attributes, with ownership and blank values resolved.
     *
     * @return array<string, mixed>
     */
    public function farmerData(MunicipalityAccess $municipalityAccess): array
    {
        $data = $this->validated();
        // Handled separately against the protected disk, never mass-assigned.
        unset($data['profile_photo'], $data['remove_profile_photo']);

        $municipality = $this->resolveMunicipality($municipalityAccess);
        $data['municipality_id'] = $municipality->id;
        $data['farm_municipality'] = $municipality->name;
        $data['farm_province'] = $municipality->province ?: $municipality->supervisingProvince?->name;

        foreach (self::FLAGS as $flag) {
            $data[$flag] = $this->boolean($flag);
        }

        foreach (self::NULLABLE_TEXT as $field) {
            $value = $data[$field] ?? null;
            $value = $value === null ? null : trim((string) $value);
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }

    /**
     * The municipality this write belongs to, decided by the account's scope.
     */
    private function resolveMunicipality(MunicipalityAccess $municipalityAccess): Municipality
    {
        return Municipality::query()
            ->whereKey($municipalityAccess->resolveForWrite(
                $this->authenticatedUser(),
                $this->input('municipality_id')
            ))
            ->active()
            ->firstOrFail();
    }

    private function authenticatedUser(): User
    {
        $user = $this->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /**
     * Keep the route model available to rules() when editing.
     */
    public function existingFarmer(): ?Farmer
    {
        $farmer = $this->route('farmer');

        return $farmer instanceof Farmer ? $farmer : null;
    }
}
