<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Support\ConcurrentWrite;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(private ConcurrentWrite $concurrentWrite, private MunicipalityAccess $municipalityAccess)
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $manager = $this->authorizedManager($request, 'viewAny');
        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $status = trim((string) $request->query('status', ''));
        $municipalityId = $manager->isMunicipalHead()
            ? (int) $manager->municipality_id
            : ($request->integer('municipality_id') ?: null);
        $provinceId = $manager->canChooseProvince() ? ($request->integer('province_id') ?: null) : $manager->province_id;
        $perPage = max(5, min((int) $request->query('per_page', 10), 100));
        $manageableUsers = $this->manageableUsersQuery($manager);
        $query = (clone $manageableUsers)
            ->with(['municipality:id,name,province,province_id', 'province:id,name'])
            ->when(Schema::hasTable('regions'), fn ($builder) => $builder->with('region:id,name'))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when(array_key_exists($role, $this->roleOptions($manager, true)), fn ($builder) => $builder->where('role', $role))
            ->when($status === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($status === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->when(! $manager->isMunicipalHead() && $municipalityId, fn ($builder) => $builder->where('municipality_id', $municipalityId))
            ->when($manager->canChooseProvince() && $provinceId, fn ($builder) => $this->scopeUsersToProvince($builder, (int) $provinceId));

        $users = $query
            ->orderByRaw("CASE role WHEN 'system_owner' THEN 0 WHEN 'regional_head' THEN 1 WHEN 'super_admin' THEN 2 WHEN 'provincial_staff' THEN 3 WHEN 'provincial_vet' THEN 4 WHEN 'municipal_head' THEN 5 WHEN 'municipal_staff' THEN 6 ELSE 7 END")
            ->orderBy('name')->paginate($perPage)->withQueryString();
        $stats = [
            'total' => (clone $manageableUsers)->count(),
            'active' => (clone $manageableUsers)->where('is_active', true)->count(),
            'provincial' => (clone $manageableUsers)->whereIn('role', User::PROVINCIAL_ROLES)->count(),
            'municipal_heads' => (clone $manageableUsers)->where('role', User::ROLE_MUNICIPAL_HEAD)->count(),
            'municipal_staff' => (clone $manageableUsers)->where('role', User::ROLE_MUNICIPAL_STAFF)->count(),
        ];

        return view('admins.index', array_merge($this->formOptions($manager), compact(
            'users', 'stats', 'q', 'role', 'status', 'municipalityId', 'provinceId', 'perPage'
        ), ['roleOptions' => $this->roleOptions($manager, true)]));
    }

    public function create(Request $request): View
    {
        $manager = $this->authorizedManager($request, 'create');

        return view('admins.create', array_merge($this->formOptions($manager), [
            'account' => new User(['role' => User::ROLE_MUNICIPAL_STAFF, 'is_active' => true]),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = $this->authorizedManager($request, 'create');
        $user = $this->concurrentWrite->transaction(function () use ($request, $manager): User {
            $currentManager = $this->lockedManager($manager, 'create');
            $data = $this->validatedAccountData($request, $currentManager);
            $data['password'] = Hash::make($data['password']);

            return User::create($data);
        });

        return redirect()->route('admins.index')->with('success', "{$user->name} was created successfully.");
    }

    public function edit(Request $request, User $admin): View
    {
        $manager = $this->authorizedManager($request, 'update', $admin);

        return view('admins.edit', array_merge($this->formOptions($manager), [
            'account' => $admin,
            'isOwnAccount' => $admin->is($manager),
        ]));
    }

    public function update(Request $request, User $admin): RedirectResponse
    {
        $manager = $this->authorizedManager($request, 'update', $admin);
        $admin = $this->concurrentWrite->execute($admin, $request->input('_record_version'), function (User $current) use ($request, $manager): User {
            $currentManager = $this->lockedManager($manager, 'update', $current);
            $data = $this->validatedAccountData($request, $currentManager, $current);
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
            $current->fill($data)->save();

            return $current;
        });

        return redirect()->route('admins.index')->with('success', "{$admin->name} was updated successfully.");
    }

    public function destroy(Request $request, User $admin): RedirectResponse
    {
        $manager = $this->authorizedManager($request, 'delete', $admin);
        $name = $this->concurrentWrite->locked($admin, function (User $current) use ($manager): string {
            $this->lockedManager($manager, 'delete', $current);
            $name = $current->name;
            $current->delete();

            return $name;
        });

        return redirect()->route('admins.index')->with('success', "{$name} was deleted successfully.");
    }

    private function validatedAccountData(Request $request, User $manager, ?User $account = null): array
    {
        $ownAccount = $account?->is($manager) ?? false;
        // An administrator's own form edits profile details only, including for crafted submissions.
        $role = $ownAccount ? $account->role : ($manager->isMunicipalHead() ? User::ROLE_MUNICIPAL_STAFF : $request->input('role'));
        $municipal = in_array($role, User::MUNICIPAL_ROLES, true);
        $provincial = in_array($role, User::PROVINCIAL_ROLES, true);
        $regional = $role === User::ROLE_REGIONAL_HEAD;
        $input = $request->all();
        if ($ownAccount) {
            $input = array_merge($input, $account->only(['role', 'region_id', 'province_id', 'municipality_id', 'is_active']));
        } elseif ($manager->isMunicipalHead()) {
            $input['role'] = User::ROLE_MUNICIPAL_STAFF;
            $input['municipality_id'] = $manager->municipality_id;
        }
        if ($provincial && ! $manager->canChooseProvince() && ! array_key_exists('province_id', $input)) {
            $input['province_id'] = $manager->province_id;
        }
        $data = validator($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account?->id)],
            'role' => ['required', Rule::in($ownAccount ? [$account->role] : array_keys($this->roleOptions($manager)))],
            'region_id' => [Rule::requiredIf($regional), 'nullable', 'integer'],
            'province_id' => [Rule::requiredIf($provincial), 'nullable', 'integer'],
            'municipality_id' => [Rule::requiredIf($municipal), 'nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            // Length plus a breach check, rather than composition rules that push
            // staff toward predictable substitutions. The breach lookup is
            // k-anonymous and passes if the service cannot be reached, so an office
            // without internet can still create accounts.
            'password' => [! $account || (($account->isSuperAdmin() || $account->isRegionalHead()) && ! $account->is_active && ! empty($input['is_active'])) ? 'required' : 'nullable', 'string', Password::min(12)->uncompromised(), 'confirmed'],
        ])->validate();

        if ($regional) {
            $region = Region::query()->active()->whereKey($data['region_id'])->lockForUpdate()->first();
            if (! $region || (! $manager->isSystemOwner() && ! $ownAccount)) {
                throw ValidationException::withMessages(['region_id' => 'Select an active region. Only the System Owner assigns Regional Heads.']);
            }
            $data['region_id'] = (int) $region->id;
        } elseif ($account?->region_id !== null) {
            $data['region_id'] = null;
        } else {
            // Ignore crafted region assignments on roles that do not use them.
            unset($data['region_id']);
        }
        if ($provincial) {
            $province = Province::query()->whereKey($data['province_id'])->where('is_active', true)->lockForUpdate()->first();
            if (! $province || ! $manager->canAccessProvince($province->id)) {
                throw ValidationException::withMessages(['province_id' => 'Select an active province within your administration scope.']);
            }
        }
        if ($municipal) {
            $municipality = Municipality::query()->whereKey($data['municipality_id'])->where('is_active', true)->lockForUpdate()->first();
            $activeProvince = $municipality && Province::query()->whereKey($municipality->province_id)->where('is_active', true)->lockForUpdate()->exists();
            if (! $municipality || ! $activeProvince || ! $manager->canAccessMunicipality($municipality->id)) {
                throw ValidationException::withMessages(['municipality_id' => 'Select an active municipality within your administration scope.']);
            }
        }
        $data['province_id'] = $provincial ? (int) $data['province_id'] : null;
        $data['municipality_id'] = $municipal ? (int) $data['municipality_id'] : null;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        // The municipality row is locked above so two assignments cannot create concurrent active heads.
        if ($role === User::ROLE_MUNICIPAL_HEAD && $data['is_active']) {
            $existingHead = User::query()->where('role', User::ROLE_MUNICIPAL_HEAD)
                ->where('municipality_id', $data['municipality_id'])->where('is_active', true)
                ->when($account, fn ($query) => $query->whereKeyNot($account->id))->exists();
            if ($existingHead) {
                throw ValidationException::withMessages(['municipality_id' => 'This municipality already has an active head agriculturist.']);
            }
        }

        return $data;
    }

    private function formOptions(User $manager): array
    {
        return [
            'manager' => $manager,
            'municipalities' => $this->municipalityAccess->choices($manager),
            'provinces' => $this->municipalityAccess->scopeProvinces(Province::query()->active(), $manager)
                ->orderBy('name')->get(['id', 'name']),
            // Keep the existing office forms usable during the additive schema rollout.
            'regions' => $manager->isSystemOwner() && Schema::hasTable('regions')
                ? Region::query()->active()->orderBy('name')->get(['id', 'name']) : collect(),
            'roleOptions' => $this->roleOptions($manager),
            'isMunicipalHeadManager' => $manager->isMunicipalHead(),
        ];
    }

    private function roleOptions(User $manager, bool $forFilter = false): array
    {
        if ($manager->isMunicipalHead()) {
            return [User::ROLE_MUNICIPAL_STAFF => 'Municipal Staff'];
        }
        $roles = [
            User::ROLE_PROVINCIAL_STAFF => 'Provincial Staff',
            User::ROLE_PROVINCIAL_VET => 'Provincial Veterinary Office',
            User::ROLE_MUNICIPAL_HEAD => 'Head Agriculturist',
            User::ROLE_MUNICIPAL_STAFF => 'Municipal Staff',
        ];
        if ($manager->canChooseProvince() || $forFilter) {
            $roles = [User::ROLE_SUPER_ADMIN => 'Super Admin', ...$roles];
        }
        if ($manager->isSystemOwner() || ($manager->isRegionalHead() && $forFilter)) {
            $roles = [User::ROLE_REGIONAL_HEAD => 'Regional Head', ...$roles];
        }
        if ($manager->isSystemOwner() && $forFilter) {
            $roles = [User::ROLE_SYSTEM_OWNER => 'System Owner', ...$roles];
        }

        return $roles;
    }

    private function authorizedManager(Request $request, string $ability, ?User $account = null): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->authorize($ability, $account ?? User::class);

        return $user;
    }

    private function lockedManager(User $manager, string $ability, ?User $account = null): User
    {
        $current = User::query()->lockForUpdate()->findOrFail($manager->id);
        Gate::forUser($current)->authorize($ability, $account ?? User::class);

        return $current;
    }

    private function manageableUsersQuery(User $manager): Builder
    {
        $query = User::query();
        if ($manager->isSystemOwner()) {
            return $query;
        }
        if ($manager->isMunicipalHead()) {
            return $query->where('role', User::ROLE_MUNICIPAL_STAFF)->where('municipality_id', $manager->municipality_id);
        }
        if ($manager->isRegionalHead()) {
            return $query->where(function (Builder $scope) use ($manager): void {
                $scope->whereKey($manager->id)->orWhere(function (Builder $accounts) use ($manager): void {
                    $accounts->where(function (Builder $provincial) use ($manager): void {
                        $provincial->whereIn('role', User::PROVINCIAL_ROLES)
                            ->whereIn('province_id', $this->municipalityAccess->scopeProvinces(Province::query(), $manager)->select('provinces.id'));
                    })->orWhere(function (Builder $municipal) use ($manager): void {
                        $municipal->whereIn('role', User::MUNICIPAL_ROLES)
                            ->whereIn('municipality_id', $this->municipalityAccess->scopeMunicipalities(Municipality::query(), $manager)->select('municipalities.id'));
                    });
                });
            });
        }

        return $query->where(function ($scope) use ($manager) {
            $scope->whereKey($manager->id)->orWhere(function ($sub) use ($manager) {
                $sub->whereNotIn('role', [User::ROLE_SYSTEM_OWNER, User::ROLE_REGIONAL_HEAD, User::ROLE_SUPER_ADMIN]);
                $this->scopeUsersToProvince($sub, (int) $manager->province_id);
            });
        });
    }

    private function scopeUsersToProvince(Builder $query, int $provinceId): Builder
    {
        return $query->where(function ($scope) use ($provinceId) {
            $scope->where(function ($provincial) use ($provinceId) {
                $provincial->whereIn('role', User::PROVINCIAL_ROLES)->where('province_id', $provinceId);
            })->orWhere(function ($municipal) use ($provinceId) {
                $municipal->whereIn('role', User::MUNICIPAL_ROLES)
                    ->whereHas('municipality', fn ($municipality) => $municipality->where('province_id', $provinceId));
            });
        });
    }
}
