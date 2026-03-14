<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'head_admin']);
    }

    public function index(Request $request)
    {
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(5, min($perPage, 100));

        $baseQuery = User::query()
            ->where('role', 'admin')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            });

        $totalAdmins = (clone $baseQuery)->count();

        $admins = $baseQuery
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('admins.index', compact('admins', 'q', 'perPage', 'totalAdmins'));
    }

    public function create()
    {
        return view('admins.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'admin', // force admin
        ]);

        return redirect()->route('admins.index')->with('success', 'Admin created successfully.');
    }

    public function edit(User $admin)
    {
        if (($admin->role ?? null) !== 'admin') {
            abort(404);
        }

        return view('admins.edit', compact('admin'));
    }

    public function update(Request $request, User $admin)
    {
        if (($admin->role ?? null) !== 'admin') {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($admin->id),
            ],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $admin->name = $data['name'];
        $admin->email = $data['email'];

        if (!empty($data['password'])) {
            $admin->password = Hash::make($data['password']);
        }

        $admin->save();

        return redirect()->route('admins.index')->with('success', 'Admin updated successfully.');
    }

    public function destroy(User $admin)
    {
        if (($admin->role ?? null) !== 'admin') {
            abort(404);
        }

        // extra safety: don't allow deleting yourself (even though head_admin isn’t in this list)
        if ($admin->id === auth()->id()) {
            return back()->with('success', 'You cannot delete your own account.');
        }

        $admin->delete();

        return redirect()->route('admins.index')->with('success', 'Admin deleted successfully.');
    }
}
