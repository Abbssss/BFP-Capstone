<?php

namespace App\Support;

class RolePermissionRegistry
{
    public const ROLES = ['super_admin', 'admin', 'maintenance'];

    public const MODULES = [
        'dashboard' => ['name' => 'Dashboard', 'defaults' => ['admin' => true, 'maintenance' => true]],
        'equipment' => ['name' => 'Equipment records', 'defaults' => ['admin' => true, 'maintenance' => true]],
        'inspections' => ['name' => 'Inspections', 'defaults' => ['admin' => true, 'maintenance' => true]],
        'maintenance' => ['name' => 'Maintenance tasks', 'defaults' => ['admin' => true, 'maintenance' => true]],
        'repairs' => ['name' => 'Repairs', 'defaults' => ['admin' => true, 'maintenance' => true]],
        'schedule' => ['name' => 'Schedule', 'defaults' => ['admin' => true, 'maintenance' => true]],
        'personnel' => ['name' => 'Personnel', 'defaults' => ['admin' => true, 'maintenance' => true]],
        'defect_reports' => ['name' => 'Defect Reports', 'defaults' => ['admin' => true, 'maintenance' => true]],
        'approvals' => ['name' => 'Approvals', 'defaults' => ['admin' => true, 'maintenance' => false]],
        'user_management' => ['name' => 'User management', 'defaults' => ['admin' => false, 'maintenance' => false], 'locked' => true],
        'roles_permissions' => ['name' => 'Roles & permissions', 'defaults' => ['admin' => false, 'maintenance' => false], 'locked' => true],
        'audit_logs' => ['name' => 'Audit logs', 'defaults' => ['admin' => false, 'maintenance' => false], 'locked' => true, 'note' => 'Audit logging is not configured.'],
        'reports' => ['name' => 'Reports', 'defaults' => ['admin' => true, 'maintenance' => true]],
        'settings' => ['name' => 'Settings', 'defaults' => ['admin' => true, 'maintenance' => true]],
    ];

    public static function defaultAccess(string $role, string $module): bool
    {
        if ($role === 'super_admin') {
            return true;
        }

        return (bool) (self::MODULES[$module]['defaults'][$role] ?? false);
    }
}
