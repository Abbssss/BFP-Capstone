<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use App\Support\OfficeScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportDataService
{
    public const REPORTS = [
        'user-activity' => ['User Activity', 'Accounts, roles, status, and access activity'],
        'equipment-registry' => ['Equipment Registry', 'Asset inventory and serviceability'],
        'maintenance-summary' => ['Maintenance Summary', 'Work volume, status, and overdue items'],
        'inspection-summary' => ['Inspection Summary', 'Findings and condition trends'],
        'audit-activity' => ['Audit Activity', 'System and configuration events'],
    ];

    public function report(string $key, string $period, ?string $status, string $groupBy, ?string $officeCode = null): array
    {
        [$start, $end] = $this->dateRange($period);
        $records = $this->query($key, $start, $end, $officeCode);
        $statuses = $records->pluck('status')->filter()->unique()->sort()->values();

        if ($status !== null && $status !== '') {
            $records = $records->where('status', $status)->values();
        }

        return [
            'reportTitle' => self::REPORTS[$key][0],
            'description' => self::REPORTS[$key][1],
            'headers' => $this->headers($key),
            'periodLabel' => $this->periodLabel($period, $start, $end),
            'statuses' => $statuses,
            'records' => $records,
            'count' => $records->count(),
            'grouped' => $records
                ->groupBy(fn (array $record) => $record['groups'][$groupBy] ?? 'Unspecified')
                ->map(fn (Collection $group) => $group->count())
                ->sortDesc(),
        ];
    }

    public function headers(string $key): array
    {
        return match ($key) {
            'user-activity' => ['Name', 'Email', 'Role', 'Office Code', 'Status', 'Created'],
            'equipment-registry' => ['Asset code', 'Equipment', 'Office Code', 'Category', 'Status', 'Location', 'Registered'],
            'maintenance-summary' => ['Request', 'Equipment', 'Office Code', 'Type', 'Status', 'Technician', 'Start date'],
            'inspection-summary' => ['Reference', 'Equipment', 'Office Code', 'Type', 'Outcome', 'Inspector', 'Inspection date', 'Defects'],
            default => ['Audit activity'],
        };
    }

    private function query(string $key, ?Carbon $start, Carbon $end, ?string $officeCode = null): Collection
    {
        $range = static function ($query, string $column) use ($start, $end): void {
            if ($start) {
                $query->whereBetween($column, [$start, $end]);
            }
        };

        return match ($key) {
            'user-activity' => OfficeScope::users(User::query())
                ->when($officeCode, fn ($query) => $query->where('office_code', $officeCode))
                ->when($start, fn ($query) => $range($query, 'created_at'))
                ->orderByDesc('created_at')->get()->map(fn (User $user) => $this->record([
                    'Name' => $user->name,
                    'Email' => $user->email,
                    'Role' => str($user->role)->replace('_', ' ')->title()->toString(),
                    'Office Code' => $user->office_code ?: '—',
                    'Status' => $user->status ?: 'Active',
                    'Created' => $user->created_at?->format('Y-m-d') ?? '—',
                ], $user->status ?: 'Active', ['role' => str($user->role)->replace('_', ' ')->title()->toString(), 'status' => $user->status ?: 'Active'], $user->created_at)),
            'equipment-registry' => OfficeScope::equipment(Equipment::query())->notArchived()
                ->when($officeCode, fn ($query) => $query->where('office_code', $officeCode))
                ->when($start, fn ($query) => $range($query, 'created_at'))
                ->orderBy('asset_code')->get()->map(fn (Equipment $equipment) => $this->record([
                    'Asset code' => $equipment->asset_code,
                    'Equipment' => $equipment->equipment_name,
                    'Office Code' => $equipment->office_code ?: '—',
                    'Category' => $equipment->category,
                    'Status' => $equipment->status,
                    'Location' => $equipment->location ?: '—',
                    'Registered' => $equipment->created_at?->format('Y-m-d') ?? '—',
                ], $equipment->status, ['category' => $equipment->category, 'status' => $equipment->status], $equipment->created_at)),
            'maintenance-summary' => OfficeScope::records(Maintenance::query())->with('assignedTo', 'equipment')
                ->when($officeCode, fn ($query) => $query->whereHas('equipment', fn ($equipment) => $equipment->where('office_code', $officeCode)))
                ->when($start, fn ($query) => $range($query, 'start_date'))
                ->orderByDesc('start_date')->get()->map(fn (Maintenance $maintenance) => $this->record([
                    'Request' => $maintenance->request_number,
                    'Equipment' => $maintenance->equipment?->equipment_name ?? '—',
                    'Office Code' => $maintenance->equipment?->office_code ?: '—',
                    'Type' => $maintenance->maintenance_type,
                    'Status' => $maintenance->status,
                    'Technician' => $maintenance->assignedTo?->name ?? ($maintenance->technician_name ?: 'Unassigned'),
                    'Start date' => $maintenance->start_date?->format('Y-m-d') ?? '—',
                ], $maintenance->status, ['category' => $maintenance->maintenance_type, 'status' => $maintenance->status], $maintenance->start_date)),
            'inspection-summary' => OfficeScope::records(Inspection::query())->with('equipment')
                ->when($officeCode, fn ($query) => $query->whereHas('equipment', fn ($equipment) => $equipment->where('office_code', $officeCode)))
                ->when($start, fn ($query) => $range($query, 'inspection_date'))
                ->orderByDesc('inspection_date')->get()->map(fn (Inspection $inspection) => $this->record([
                    'Reference' => $inspection->reference_number,
                    'Equipment' => $inspection->equipment?->equipment_name ?? '—',
                    'Office Code' => $inspection->equipment?->office_code ?: '—',
                    'Type' => $inspection->inspection_type,
                    'Outcome' => $inspection->outcome,
                    'Inspector' => $inspection->inspector_name,
                    'Inspection date' => $inspection->inspection_date?->format('Y-m-d') ?? '—',
                    'Defects' => $inspection->defects_identified ?: 'None reported',
                ], $inspection->outcome, ['category' => $inspection->inspection_type, 'status' => $inspection->outcome], $inspection->inspection_date)),
            default => collect(),
        };
    }

    private function record(array $values, string $status, array $groups, mixed $date): array
    {
        $groups['month'] = $date ? Carbon::parse($date)->format('Y-m') : 'Unspecified';

        return [
            'values' => $values,
            'status' => $status,
            'groups' => $groups,
            'groupBy' => 'role',
        ];
    }

    private function dateRange(string $period): array
    {
        $today = now()->endOfDay();

        return match ($period) {
            '30-days' => [now()->subDays(29)->startOfDay(), $today],
            'year' => [now()->startOfYear(), $today],
            'all' => [null, $today],
            default => [now()->firstOfQuarter()->startOfDay(), $today],
        };
    }

    private function periodLabel(string $period, ?Carbon $start, Carbon $end): string
    {
        if ($period === 'all') {
            return 'All dates';
        }

        return match ($period) {
            '30-days' => 'Last 30 days · '.$start->format('M d').'–'.$end->format('M d, Y'),
            'year' => 'Year to date · '.$start->format('M Y').'–'.$end->format('M Y'),
            default => 'Current quarter · '.$start->format('M').'–'.$end->format('M Y'),
        };
    }
}
