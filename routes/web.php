<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\OperationsPagesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:dashboard');
    Route::get('/search', SearchController::class)->name('search');

    Route::get('/equipment/import-template', [EquipmentController::class, 'importTemplate'])
        ->name('equipment.import-template')
        ->middleware(['permission:equipment', 'role:admin,super_admin']);
    Route::post('/equipment/import', [EquipmentController::class, 'import'])
        ->name('equipment.import')
        ->middleware(['permission:equipment', 'role:admin,super_admin']);
    Route::post('/equipment/categories', [EquipmentController::class, 'storeCategory'])
        ->name('equipment.categories.store')
        ->middleware(['permission:equipment', 'role:super_admin']);
    Route::resource('equipment', EquipmentController::class)->only('index')->middleware('permission:equipment');
    Route::resource('equipment', EquipmentController::class)
        ->only(['create', 'store'])
        ->middleware(['permission:equipment', 'role:admin,super_admin']);
    Route::get('/equipment/{equipment}/edit', [EquipmentController::class, 'edit'])
        ->name('equipment.edit')
        ->middleware(['permission:equipment', 'role:admin,super_admin']);
    Route::match(['put', 'patch'], '/equipment/{equipment}', [EquipmentController::class, 'update'])
        ->name('equipment.update')
        ->middleware('permission:equipment');
    Route::resource('equipment', EquipmentController::class)->only('show')->middleware('permission:equipment');
    Route::delete('/equipment/{equipment}', [EquipmentController::class, 'destroy'])
        ->name('equipment.destroy')
        ->middleware(['permission:equipment', 'role:super_admin']);
    Route::resource('inspections', InspectionController::class)->middleware('permission:inspections');
    Route::resource('maintenances', MaintenanceController::class)->middleware('permission:maintenance');
    Route::post('/maintenance/equipment/{equipment}/status-record', [MaintenanceController::class, 'storeEquipmentMaintenance'])
        ->name('maintenance.equipment-work.store')
        ->middleware('permission:maintenance');
    Route::post('/repairs/equipment/{equipment}/status-record', [MaintenanceController::class, 'storeEquipmentRepair'])
        ->name('repairs.equipment-work.store')
        ->middleware('permission:repairs');

    Route::get('/repairs', [OperationsPagesController::class, 'repairs'])->name('repairs.index')->middleware('permission:repairs');
    Route::get('/schedule', [OperationsPagesController::class, 'schedule'])->name('schedule.index')->middleware('permission:schedule');
    Route::get('/personnel', [OperationsPagesController::class, 'personnel'])->name('personnel.index')->middleware('permission:personnel');
    Route::get('/defect-reports', [OperationsPagesController::class, 'defects'])->name('defects.index')->middleware('permission:defect_reports');
    Route::get('/approvals', [OperationsPagesController::class, 'approvals'])->name('approvals.index')->middleware('permission:approvals');

    Route::get('/risk', [RiskController::class, 'index'])->name('risk.index')->middleware('permission:ai_predictions');
    Route::post('/risk/run', [RiskController::class, 'run'])->name('risk.run')->middleware('permission:ai_predictions');
    Route::post('/risk/retrain', [RiskController::class, 'retrain'])->name('risk.retrain')->middleware('permission:ai_predictions');

    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('role:super_admin');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create')->middleware('role:super_admin');
    Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('role:super_admin');
    Route::post('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role')->middleware('role:super_admin');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('role:super_admin');
    Route::get('/roles-permissions', [UserController::class, 'permissions'])->name('roles.permissions')->middleware('role:super_admin');

    Route::post('/roles-permissions', [UserController::class, 'updatePermissions'])->name('roles.permissions.update')->middleware('role:super_admin');
    Route::get('/audit-logs', [OperationsPagesController::class, 'audit'])->name('audit.index')->middleware(['role:super_admin', 'permission:audit_logs']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:reports');
    Route::get('/reports/export/{format}', [ReportController::class, 'export'])->name('reports.export')->middleware('permission:reports');
    Route::get('/settings', [OperationsPagesController::class, 'settings'])->name('settings.index')->middleware('permission:settings');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
