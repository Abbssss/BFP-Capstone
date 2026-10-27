<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\EquipmentPriorityService;
use App\Services\RolePermissionService;
use App\Support\OfficeScope;
use App\Support\SearchQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', Rule::in(['all', 'archive'])],
        ]);
        $isArchive = ($filters['section'] ?? 'all') === 'archive';
        $scopedEquipment = OfficeScope::equipment(Equipment::query());
        $archiveCount = (clone $scopedEquipment)
            ->whereIn('status', ['Out of Service', 'Retired'])
            ->count();
        $equipmentQuery = (clone $scopedEquipment)->with('assignedUser', 'latestInspection', 'maintenances')
            ->when(
                $isArchive,
                fn ($query) => $query->whereIn('status', ['Out of Service', 'Retired']),
                fn ($query) => $query->whereNotIn('status', ['Out of Service', 'Retired'])
            )
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category));
        SearchQuery::apply($equipmentQuery, $filters['search'] ?? null, [
            'equipment_name', 'asset_code', 'asset_tag', 'serial_number', 'category',
            'manufacturer', 'model', 'location', 'office_code', 'status',
        ], ['assignedUser' => ['name']]);
        $equipment = $equipmentQuery->latest()->paginate(10)->withQueryString();
        $equipment->getCollection()->transform(function (Equipment $item): Equipment {
            $item->setAttribute('maintenance_priority', app(EquipmentPriorityService::class)->assess($item));

            return $item;
        });
        $users = OfficeScope::personnel(User::query())->whereNotNull('office_code')->orderBy('name')->get();
        $equipmentOptions = OfficeScope::equipment(Equipment::query())->orderBy('equipment_name')->get();
        $statuses = (clone $scopedEquipment)
            ->when(
                $isArchive,
                fn ($query) => $query->whereIn('status', ['Out of Service', 'Retired']),
                fn ($query) => $query->whereNotIn('status', ['Out of Service', 'Retired'])
            )
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');
        $categories = Equipment::CATEGORIES;

        return view('equipment.index', compact('equipment', 'users', 'equipmentOptions', 'statuses', 'categories', 'isArchive', 'archiveCount'));
    }

    public function create(): View
    {
        $categories = Equipment::CATEGORIES;

        return view('equipment.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $officeCode = $request->user()->role === 'super_admin'
            ? strtoupper(trim((string) $request->input('office_code')))
            : $request->user()->office_code;
        $validated = $request->validate([
            'equipment_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(Equipment::CATEGORIES)],
            'office_code' => [Rule::requiredIf($request->user()->role === 'super_admin'), 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'asset_tag' => ['required', 'string', 'unique:equipment,asset_tag'],
            'serial_number' => ['required', 'string', 'unique:equipment,serial_number'],
            'manufacturer' => ['required', 'string'],
            'model' => ['required', 'string'],
            'commission_date' => ['required', 'date'],
            'location' => ['required', 'string'],
            'status' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['office_code'] = $officeCode ?: null;
        $validated['asset_code'] = 'BFP-'.strtoupper(substr(md5(uniqid((string) now()->timestamp, true)), 0, 8));
        $equipment = Equipment::create($validated);

        EquipmentStatusHistory::create([
            'equipment_id' => $equipment->id,
            'old_status' => null,
            'new_status' => $equipment->status,
            'changed_by' => auth()->id(),
            'reason' => 'Asset registration',
            'changed_at' => now(),
        ]);

        return redirect()->route('equipment.index')->with('success', 'Equipment registered successfully.');
    }

    public function show(string $id): View
    {
        $equipment = OfficeScope::equipment(Equipment::query())->with('history', 'inspections', 'maintenances')->findOrFail($id);

        return view('equipment.show', compact('equipment'));
    }

    public function records(string $id, RolePermissionService $permissions): View
    {
        $equipment = OfficeScope::equipment(Equipment::query())->with('assignedUser')->findOrFail($id);
        $user = request()->user();
        $records = collect();

        if ($permissions->allows($user->role, 'inspections')) {
            $records = $records->concat(OfficeScope::records(Inspection::query())
                ->with('equipment')
                ->where('equipment_id', $equipment->id)
                ->get()
                ->map(fn (Inspection $inspection) => [
                    'type' => 'Inspection',
                    'reference' => $inspection->reference_number,
                    'date' => $inspection->inspection_date,
                    'details' => $inspection->inspection_type.' · '.$inspection->outcome,
                    'url' => route('inspections.show', $inspection),
                    'status_url' => route('inspections.status', $inspection),
                    'model' => $inspection,
                ]));
        }

        if ($permissions->allows($user->role, 'maintenance')) {
            $maintenanceRecords = OfficeScope::records(Maintenance::query())
                ->with('equipment.assignedUser', 'assignedTo')
                ->where('equipment_id', $equipment->id)
                ->whereRaw('LOWER(maintenance_type) NOT LIKE ?', ['%repair%'])
                ->where(function ($query) {
                    $query->whereRaw('LOWER(maintenance_type) LIKE ?', ['%preventive%'])
                        ->orWhereNull('reported_issue')
                        ->orWhere('reported_issue', '');
                })
                ->get()
                ->map(fn (Maintenance $maintenance) => [
                    'type' => 'Maintenance',
                    'reference' => $maintenance->request_number,
                    'date' => $maintenance->completion_date ?? $maintenance->start_date,
                    'details' => $maintenance->maintenance_type.' · '.$maintenance->status,
                    'url' => route('maintenances.show', $maintenance),
                    'status_url' => route('maintenances.status', $maintenance),
                    'model' => $maintenance,
                ]);
            $records = $records->concat($maintenanceRecords);
        }

        if ($permissions->allows($user->role, 'repairs')) {
            $repairs = OfficeScope::records(Maintenance::query())
                ->with('equipment.assignedUser', 'assignedTo')
                ->where('equipment_id', $equipment->id)
                ->where(function ($query) {
                    $query->whereRaw('LOWER(maintenance_type) LIKE ?', ['%repair%'])
                        ->orWhere(function ($query) {
                            $query->whereRaw('LOWER(maintenance_type) NOT LIKE ?', ['%preventive%'])
                                ->whereNotNull('reported_issue')
                                ->where('reported_issue', '<>', '');
                        });
                })
                ->get()
                ->map(fn (Maintenance $repair) => [
                    'type' => 'Repair',
                    'reference' => $repair->request_number,
                    'date' => $repair->completion_date ?? $repair->start_date,
                    'details' => $repair->reported_issue ?: $repair->maintenance_type.' · '.$repair->status,
                    'url' => route('maintenances.show', $repair),
                    'status_url' => route('repairs.status', $repair),
                    'model' => $repair,
                ]);
            $records = $records->concat($repairs);
        }

        $records = $records->sortByDesc(fn (array $record) => $record['date']?->timestamp ?? 0)->values();

        return view('equipment.records', compact('equipment', 'records'));
    }

    public function edit(string $id): View
    {
        $equipment = OfficeScope::equipment(Equipment::query())->findOrFail($id);
        $users = OfficeScope::personnel(User::query())->orderBy('name')->get();
        $categories = Equipment::CATEGORIES;

        return view('equipment.edit', compact('equipment', 'users', 'categories'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $equipment = OfficeScope::equipment(Equipment::query())->findOrFail($id);
        $officeCode = $request->user()->role === 'super_admin'
            ? strtoupper(trim((string) $request->input('office_code')))
            : $request->user()->office_code;
        $assignmentOfficeCode = $request->user()->role === 'maintenance'
            ? $equipment->office_code
            : $officeCode;
        $assignedUserRule = Rule::exists('users', 'id')
            ->where(fn ($query) => $query->where('role', '<>', 'super_admin'))
            ->where(filled($assignmentOfficeCode)
                ? fn ($query) => $query->where('office_code', $assignmentOfficeCode)
                : fn ($query) => $query->whereRaw('1 = 0'));
        if ($request->user()->role === 'maintenance') {
            $validated = $request->validate([
                'last_inspection_date' => ['nullable', 'date'],
                'next_inspection_date' => ['nullable', 'date'],
                'last_maintenance_date' => ['nullable', 'date'],
                'maintenance_interval_days' => ['nullable', 'integer', 'min:0'],
                'last_inspection_outcome' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'remarks' => ['nullable', 'string'],
            ]);
        } else {
            $validated = $request->validate([
                'equipment_name' => ['required', 'string', 'max:255'],
                'category' => ['required', 'string', Rule::in(Equipment::CATEGORIES)],
                'office_code' => [Rule::requiredIf($request->user()->role === 'super_admin'), 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
                'asset_tag' => ['nullable', 'string', 'unique:equipment,asset_tag,'.$equipment->id],
                'serial_number' => ['nullable', 'string', 'unique:equipment,serial_number,'.$equipment->id],
                'manufacturer' => ['nullable', 'string'],
                'model' => ['nullable', 'string'],
                'commission_date' => ['nullable', 'date'],
                'location' => ['nullable', 'string'],
                'assigned_personnel' => ['nullable', $assignedUserRule],
                'status' => ['required', 'string'],
                'description' => ['nullable', 'string'],
            ]);

            $validated['office_code'] = $officeCode ?: null;
            if ($equipment->status !== $validated['status']) {
                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'old_status' => $equipment->status,
                    'new_status' => $validated['status'],
                    'changed_by' => auth()->id(),
                    'reason' => 'Status update',
                    'changed_at' => now(),
                ]);
            }
        }

        $equipment->update($validated);

        return redirect()->route('equipment.index')->with('success', 'Equipment updated successfully.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'max:10240'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV file could not be read.']);
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '\\');
            if (! is_array($headers)) {
                throw ValidationException::withMessages(['file' => 'The uploaded CSV file is empty.']);
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
            if (count($headers) !== count(array_unique($headers)) || ! in_array('equipment_name', $headers, true) || ! in_array('category', $headers, true)) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV must contain unique equipment_name and category columns. Download the template for all supported columns.',
                ]);
            }

            $rows = [];
            $errors = [];
            $seenValues = ['asset_code' => [], 'asset_tag' => [], 'serial_number' => []];
            $processedRows = 0;
            $lineNumber = 1;
            while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                $lineNumber++;
                if ($values === [null] || count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                $processedRows++;
                if ($processedRows > 500) {
                    $errors['file'][] = 'A maximum of 500 equipment records can be imported at a time.';
                    break;
                }
                if (count($values) !== count($headers)) {
                    $errors["rows.{$lineNumber}"][] = "Row {$lineNumber} has a different number of values than the header.";

                    continue;
                }

                $row = array_combine($headers, array_map(fn ($value) => trim((string) $value), $values));
                $officeCode = $request->user()->role === 'super_admin'
                    ? strtoupper(trim((string) ($row['office_code'] ?? '')))
                    : $request->user()->office_code;
                $data = [
                    'equipment_name' => $row['equipment_name'] ?? '',
                    'category' => $row['category'] ?? '',
                    'office_code' => $officeCode,
                    'status' => ($row['status'] ?? '') !== '' ? $row['status'] : 'Operational',
                    'asset_code' => ($row['asset_code'] ?? '') !== '' ? $row['asset_code'] : null,
                    'asset_tag' => ($row['asset_tag'] ?? '') !== '' ? $row['asset_tag'] : null,
                    'serial_number' => ($row['serial_number'] ?? '') !== '' ? $row['serial_number'] : null,
                    'manufacturer' => ($row['manufacturer'] ?? '') !== '' ? $row['manufacturer'] : null,
                    'model' => ($row['model'] ?? '') !== '' ? $row['model'] : null,
                    'location' => ($row['location'] ?? '') !== '' ? $row['location'] : null,
                    'commission_date' => ($row['date_acquired'] ?? $row['commission_date'] ?? '') !== ''
                        ? ($row['date_acquired'] ?? $row['commission_date'])
                        : null,
                    'last_inspection_date' => ($row['last_inspection_date'] ?? '') !== '' ? $row['last_inspection_date'] : null,
                    'next_inspection_date' => ($row['next_inspection_date'] ?? '') !== '' ? $row['next_inspection_date'] : null,
                    'last_maintenance_date' => ($row['last_maintenance_date'] ?? '') !== '' ? $row['last_maintenance_date'] : null,
                    'maintenance_interval_days' => ($row['maintenance_interval_days'] ?? '') !== '' ? $row['maintenance_interval_days'] : null,
                    'description' => ($row['description'] ?? '') !== '' ? $row['description'] : null,
                    'remarks' => ($row['remarks'] ?? '') !== '' ? $row['remarks'] : null,
                ];
                $validator = Validator::make($data, [
                    'equipment_name' => ['required', 'string', 'max:255'],
                    'category' => ['required', 'string', Rule::in(Equipment::CATEGORIES)],
                    'office_code' => [
                        Rule::requiredIf($request->user()->role === 'super_admin'),
                        'nullable',
                        'string',
                        'max:50',
                        'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/',
                    ],
                    'status' => ['required', Rule::in(['Operational', 'Operational with Restrictions', 'Under Inspection', 'Under Maintenance', 'Under Repair', 'Out of Service', 'Retired'])],
                    'asset_code' => ['nullable', 'string', 'max:255', 'unique:equipment,asset_code'],
                    'asset_tag' => ['nullable', 'string', 'max:255', 'unique:equipment,asset_tag'],
                    'serial_number' => ['nullable', 'string', 'max:255', 'unique:equipment,serial_number'],
                    'manufacturer' => ['nullable', 'string', 'max:255'],
                    'model' => ['nullable', 'string', 'max:255'],
                    'location' => ['nullable', 'string', 'max:255'],
                    'commission_date' => ['nullable', 'date'],
                    'last_inspection_date' => ['nullable', 'date'],
                    'next_inspection_date' => ['nullable', 'date'],
                    'last_maintenance_date' => ['nullable', 'date'],
                    'maintenance_interval_days' => ['nullable', 'integer', 'min:0'],
                    'description' => ['nullable', 'string'],
                    'remarks' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $errors["rows.{$lineNumber}"][] = "Row {$lineNumber}: {$message}";
                    }

                    continue;
                }

                foreach ($seenValues as $field => &$seen) {
                    $value = $data[$field] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    if (in_array($value, $seen, true)) {
                        $errors["rows.{$lineNumber}"][] = "Row {$lineNumber}: {$field} is duplicated within the uploaded file.";
                    }
                    $seen[] = $value;
                }
                unset($seen);

                $rows[] = $data;
            }
        } finally {
            fclose($handle);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The CSV does not contain any equipment records to import.']);
        }

        DB::transaction(function () use ($rows, $request) {
            foreach ($rows as $row) {
                $row['asset_code'] ??= 'BFP-'.strtoupper(substr(md5(uniqid((string) now()->timestamp, true)), 0, 8));
                $equipment = Equipment::create($row);
                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'old_status' => null,
                    'new_status' => $equipment->status,
                    'changed_by' => $request->user()->id,
                    'reason' => 'Bulk equipment import',
                    'changed_at' => now(),
                ]);
            }
        });

        return redirect()->route('equipment.index')->with('success', count($rows).' equipment records imported successfully.');
    }

    public function importTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'equipment_name', 'category', 'office_code', 'status', 'asset_code',
                'asset_tag', 'serial_number', 'manufacturer', 'model', 'location', 'date_acquired',
                'last_inspection_date', 'next_inspection_date', 'last_maintenance_date',
                'maintenance_interval_days', 'description', 'remarks',
            ]);
            fclose($output);
        }, 'equipment-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, Equipment $equipment): RedirectResponse
    {
        $equipment = OfficeScope::equipment(Equipment::query())->findOrFail($equipment->id);
        $validated = $request->validate([
            '_form' => ['required', 'in:delete-equipment'],
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password does not match the signed-in Super Admin account.',
            ]);
        }

        $equipment->delete();

        return redirect()->route('equipment.index')->with('success', 'Equipment removed successfully.');
    }
}
