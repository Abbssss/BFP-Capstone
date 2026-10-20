<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 40);
            $table->string('module', 60);
            $table->boolean('can_access')->default(false);
            $table->timestamps();
            $table->unique(['role', 'module']);
        });

        $now = now();
        $modules = [
            'dashboard', 'equipment', 'inspections', 'maintenance', 'repairs',
            'ai_predictions', 'schedule', 'personnel', 'defect_reports', 'approvals',
            'user_management', 'roles_permissions', 'audit_logs', 'reports', 'settings',
        ];
        $restricted = ['user_management', 'roles_permissions', 'audit_logs'];
        $rows = [];

        foreach (['admin', 'maintenance'] as $role) {
            foreach ($modules as $module) {
                $rows[] = [
                    'role' => $role,
                    'module' => $module,
                    'can_access' => ! in_array($module, $restricted, true),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('role_module_permissions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_module_permissions');
    }
};
