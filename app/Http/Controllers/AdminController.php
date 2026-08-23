<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(
        private ConcurrentWrite $concurrentWrite
    )
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $manager = $this->authorizedManager($request, 'viewAny');

        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $status = trim((string) $request->query('status', ''));
        $municipalityId = $manager->isSuperAdmin()
            ? ($request->integer('municipality_id') ?: null)
            : (int) $manager->municipality_id;

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(5, min($perPage, 100));

        $manageableUsers = $this->manageableUsersQuery($manager);
        $query = (clone $manageableUsers)
            ->with('municipality:id,name,province')
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when(
                array_key_exists($role, $this->roleOptions($manager, true)),
                function ($builder) use ($role) {
                    $builder->where('role', $role);
                }
            )
            ->when($status === 'active', function ($builder) {
                $builder->where('is_active', true);
            })
            ->when($status === 'inactive', function ($builder) {
                $builder->where('is_active', false);
            })
            ->when(
                $manager->isSuperAdmin() && $municipalityId,
                function ($builder) use ($municipalityId) {
                    $builder->where('municipality_id', $municipalityId);
                }
            );

        $users = $query
            ->orderByRaw("CASE role
                WHEN 'super_admin' THEN 1
                WHEN 'provincial_staff' THEN 2
                WHEN 'municipal_head' THEN 3
                WHEN 'municipal_staff' THEN 4
                ELSE 5
            END")
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            'total' => (clone $manageableUsers)->count(),
            'active' => (clone $manageableUsers)
                ->where('is_active', true)
                ->count(),
            'provincial' => (clone $manageableUsers)
                ->whereIn('role', User::PROVINCIAL_ROLES)
                ->count(),
            'municipal_heads' => (clone $manageableUsers)
                ->where('role', User::ROLE_MUNICIPAL_HEAD)
                ->count(),
            'municipal_staff' => (clone $manageableUsers)
                ->where('role', User::ROLE_MUNICIPAL_STAFF)
                ->count(),
        ];

        return view('admins.index', [
            'users' => $users,
            'stats' => $stats,
            'municipalities' => $this->municipalityOptions($manager),
            'roleOptions' => $this->roleOptions($manager, true),
            'q' => $q,
            'role' => $role,
            'status' => $status,
            'municipalityId' => $municipalityId,
            'perPage' => $perPage,
            'manager' => $manager,
            'isMunicipalHeadManager' => $manager->isMunicipalHead(),
        ]);
    }

    public function create(Request $request): View
    {
        $manager = $this->authorizedManager($request, 'create');

        return view('admins.create', [
            'account' => new User([
                'role' => User::ROLE_MUNICIPAL_STAFF,
                'is_active' => true,
            ]),
            'municipalities' => $this->municipalityOptions($manager),
            'roleOptions' => $this->roleOptions($manager, false),
            'manager' => $manager,
            'isMunicipalHeadManager' => $manager->isMunicipalHead(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = $this->authorizedManager($request, 'create');

        $data = $this->validatedAccountData($request, $manager);

        $user = $this->concurrentWrite->transaction(
            fn () => User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'municipality_id' => $this->municipalityIdForRole($data),
                'is_active' => $request->boolean('is_active'),
            ])
        );

        return redirect()
            ->route('admins.index')
            ->with('success', "{$user->name} was created successfully.");
    }

    public function edit(Request $request, User $admin): View
    {
        $manager = $this->authorizedManager($request, 'update', $admin);

        return view('admins.edit', [
            'account' => $admin,
            'municipalities' => $this->municipalityOptions($manager),
            'roleOptions' => $this->roleOptions(
                $manager,
                $admin->isSuperAdmin()
            ),
            'isOwnAccount' => $admin->is($manager),
            'manager' => $manager,
            'isMunicipalHeadManager' => $manager->isMunicipalHead(),
        ]);
    }

    public function update(Request $request, User $admin): RedirectResponse
    {
        $manager = $this->authorizedManager($request, 'update', $admin);
        $isOwnAccount = $admin->is($manager);
        $isProtectedSuperAdmin = $admin->isSuperAdmin();

        $data = $this->validatedAccountData(
            $request,
            $manager,
            $admin,
            $isProtectedSuperAdmin
        );

        $admin = $this->concurrentWrite->execute(
            $admin,
            $request->input('_record_version'),
            function (User $current) use (
                $data,
                $isProtectedSuperAdmin,
                $isOwnAccount,
                $request
            ): User {
                $current->name = $data['name'];
                $current->email = $data['email'];

                if (! $isProtectedSuperAdmin && ! $isOwnAccount) {
                    $current->role = $data['role'];
                    $current->municipality_id = $this->municipalityIdForRole($data);
                    $current->is_active = $request->boolean('is_active');
                }

                if (! empty($data['password'])) {
                    $current->password = Hash::make($data['password']);
                }

                $current->save();

                return $current;
            }
        );

        return redirect()
            ->route('admins.index')
            ->with('success', "{$admin->name} was updated successfully.");
    }

    public function destroy(Request $request, User $admin): RedirectResponse
    {
        $manager = $this->authorizedManager($request, 'delete', $admin);

        $result = $this->concurrentWrite->locked(
            $admin,
            function (User $current) use ($manager): array {
                if ($current->is($manager)) {
                    return ['error' => 'You cannot delete your own account.'];
                }

                if ($current->isSuperAdmin()) {
                    return [
                        'error' => 'A super-admin account cannot be deleted here.',
                    ];
                }

                $name = $current->name;
                $current->delete();

                return ['name' => $name];
            }
        );

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect()
            ->route('admins.index')
            ->with(
                'success',
                "{$result['name']} was deleted successfully."
            );
    }

    private function validatedAccountData(
        Request $request,
        User $manager,
        ?User $account = null,
        bool $lockRole = false
    ): array {
        $accountId = $account?->id;

        $allowedRoles = $manager->isMunicipalHead()
            ? [User::ROLE_MUNICIPAL_STAFF]
            : ($lockRole
            ? [User::ROLE_SUPER_ADMIN]
            : [
                User::ROLE_PROVINCIAL_STAFF,
                User::ROLE_MUNICIPAL_HEAD,
                User::ROLE_MUNICIPAL_STAFF,
            ]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($accountId),
            ],
            'role' => ['required', Rule::in($allowedRoles)],
            'municipality_id' => [
                Rule::requiredIf(function () use ($request, $lockRole) {
                    if ($lockRole) {
                        return false;
                    }

                    return in_array(
                        $request->input('role'),
                        User::MUNICIPAL_ROLES,
                        true
                    );
                }),
                'nullable',
                'integer',
                Rule::exists('municipalities', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'is_active' => ['nullable', 'boolean'],
            'password' => [
                $account ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];

        $data = $request->validate($rules);

        if ($manager->isMunicipalHead()) {
            $data['role'] = User::ROLE_MUNICIPAL_STAFF;
            $data['municipality_id'] = (int) $manager->municipality_id;
        }

        if ($manager->isSuperAdmin() && $lockRole) {
            $data['role'] = User::ROLE_SUPER_ADMIN;
            $data['municipality_id'] = null;
        }

        if (
            ($data['role'] ?? null) === User::ROLE_MUNICIPAL_HEAD
            && $request->boolean('is_active')
        ) {
            $existingHead = User::query()
                ->where('role', User::ROLE_MUNICIPAL_HEAD)
                ->where('municipality_id', $data['municipality_id'])
                ->where('is_active', true)
                ->when($accountId, fn ($query) => $query->where('id', '!=', $accountId))
                ->exists();

            if ($existingHead) {
                throw ValidationException::withMessages([
                    'municipality_id' => 'This municipality already has an active head agriculturist.',
                ]);
            }
        }

        return $data;
    }

    private function municipalityIdForRole(array $data): ?int
    {
        if (in_array($data['role'], User::PROVINCIAL_ROLES, true)) {
            return null;
        }

        return isset($data['municipality_id'])
            ? (int) $data['municipality_id']
            : null;
    }

    private function municipalityOptions(User $manager)
    {
        return Municipality::query()
            ->where('is_active', true)
            ->when(
                $manager->isMunicipalHead(),
                fn ($query) => $query->whereKey($manager->municipality_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'province']);
    }

    private function roleOptions(
        User $manager,
        bool $includeSuperAdmin
    ): array {
        if ($manager->isMunicipalHead()) {
            return [
                User::ROLE_MUNICIPAL_STAFF => 'Municipal Staff',
            ];
        }

        $roles = [
            User::ROLE_PROVINCIAL_STAFF => 'Provincial Staff',
            User::ROLE_MUNICIPAL_HEAD => 'Head Agriculturist',
            User::ROLE_MUNICIPAL_STAFF => 'Municipal Staff',
        ];

        if ($includeSuperAdmin) {
            $roles = [
                User::ROLE_SUPER_ADMIN => 'Super Admin',
                ...$roles,
            ];
        }

        return $roles;
    }

    private function authorizedManager(
        Request $request,
        string $ability,
        ?User $account = null
    ): User {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $this->authorize($ability, $account ?? User::class);

        return $user;
    }

    private function manageableUsersQuery(User $manager)
    {
        return User::query()
            ->when($manager->isMunicipalHead(), function ($query) use ($manager) {
                $query->where('role', User::ROLE_MUNICIPAL_STAFF)
                    ->where('municipality_id', $manager->municipality_id);
            });
    }
}
