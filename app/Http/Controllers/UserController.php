<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Support\SearchQuery;
use App\Support\RolePermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,pending'],
            'role' => ['nullable', 'in:super_admin,admin,maintenance,pending'],
        ]);

        $userQuery = User::query()
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['role'] ?? null, fn ($query, $role) => $query->where('role', $role));
        SearchQuery::apply($userQuery, $validated['search'] ?? null, ['name', 'email', 'office_code', 'role', 'status']);
        $users = $userQuery->orderBy('name')->paginate(10)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function permissions(Request $request, RolePermissionService $permissionService): View
    {
        $roles = [
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Full governance and system administration',
                'icon' => '⛨',
            ],
            'admin' => [
                'name' => 'Admin',
                'description' => 'Station operations and equipment oversight',
                'icon' => '♙',
            ],
            'maintenance' => [
                'name' => 'Maintenance Personnel',
                'description' => 'Inspection and maintenance operations',
                'icon' => '⚒',
            ],
        ];

        foreach ($roles as $key => &$role) {
            $role['user_count'] = User::where('role', $key)->count();
        }
        unset($role);

        $modules = RolePermissionRegistry::MODULES;
        $permissions = $permissionService->matrix();

        return view('users.permissions', compact('roles', 'modules', 'permissions'));
    }

    public function updatePermissions(Request $request, RolePermissionService $permissionService): RedirectResponse
    {
        $moduleKeys = array_keys(array_filter(
            RolePermissionRegistry::MODULES,
            fn (array $module) => empty($module['locked'])
        ));
        $rules = ['permissions' => ['nullable', 'array']];

        foreach (['admin', 'maintenance'] as $role) {
            $rules['permissions.'.$role] = ['nullable', 'array'];
            foreach ($moduleKeys as $module) {
                $rules['permissions.'.$role.'.'.$module] = ['sometimes', 'boolean'];
            }
        }

        $validated = $request->validate($rules);
        $permissionService->update($validated['permissions'] ?? []);

        return redirect()->route('roles.permissions')->with('success', 'Role permissions saved and applied.');
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:super_admin,admin,maintenance'],
            'office_code' => ['required_if:role,admin,maintenance', 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'status' => ['required', 'in:active,inactive'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $validated['office_code'] = $validated['role'] === 'super_admin'
            ? null
            : strtoupper(trim($validated['office_code']));
        $validated['password'] = Hash::make($validated['password']);
        User::create($validated);

        return redirect()->route('users.index')->with('success', 'User account created.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:super_admin,admin,maintenance'],
            'office_code' => ['required_if:role,admin,maintenance', 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
        ]);

        $officeCode = $validated['role'] === 'super_admin'
            ? null
            : strtoupper(trim($validated['office_code']));

        DB::transaction(function () use ($user, $validated, $officeCode): void {
            if ($validated['role'] === 'super_admin' || $user->office_code !== $officeCode) {
                Equipment::query()
                    ->where('assigned_personnel', $user->id)
                    ->update(['assigned_personnel' => null]);
            }

            $user->update([
                'role' => $validated['role'],
                'office_code' => $officeCode,
                'status' => $validated['status'] ?? $user->status,
            ]);
        });

        return back()->with('success', 'User role and office assignment updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'password' => 'You cannot delete your own Super Admin account.',
            ]);
        }

        $validated = $request->validate([
            '_form' => ['required', 'in:delete-user'],
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password does not match the signed-in Super Admin account.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            Equipment::query()
                ->where('assigned_personnel', $user->id)
                ->update(['assigned_personnel' => null]);

            $user->delete();
        });

        return redirect()->route('users.index')->with('success', 'User account deleted.');
    }
}
