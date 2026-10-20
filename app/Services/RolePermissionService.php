<?php

namespace App\Services;

use App\Support\RolePermissionRegistry;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    public function allows(string $role, string $module): bool
    {
        if ($role === 'super_admin') {
            return true;
        }

        if (! in_array($role, RolePermissionRegistry::ROLES, true)
            || ! array_key_exists($module, RolePermissionRegistry::MODULES)) {
            return false;
        }

        $permission = DB::table('role_module_permissions')
            ->where('role', $role)
            ->where('module', $module)
            ->value('can_access');

        return $permission === null
            ? RolePermissionRegistry::defaultAccess($role, $module)
            : (bool) $permission;
    }

    public function matrix(): array
    {
        $stored = DB::table('role_module_permissions')
            ->get(['role', 'module', 'can_access'])
            ->keyBy(fn (object $permission) => $permission->role.'.'.$permission->module);
        $matrix = [];

        foreach (RolePermissionRegistry::ROLES as $role) {
            foreach (RolePermissionRegistry::MODULES as $module => $definition) {
                $permission = $stored->get($role.'.'.$module);
                $matrix[$role][$module] = $role === 'super_admin'
                    ? true
                    : ($permission ? (bool) $permission->can_access : RolePermissionRegistry::defaultAccess($role, $module));
            }
        }

        return $matrix;
    }

    public function update(array $permissions): void
    {
        $now = now();
        $rows = [];

        foreach (['admin', 'maintenance'] as $role) {
            foreach (RolePermissionRegistry::MODULES as $module => $definition) {
                if (! empty($definition['locked'])) {
                    continue;
                }

                $rows[] = [
                    'role' => $role,
                    'module' => $module,
                    'can_access' => (bool) ($permissions[$role][$module] ?? false),
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            }
        }

        DB::table('role_module_permissions')->upsert(
            $rows,
            ['role', 'module'],
            ['can_access', 'updated_at']
        );
    }
}
