<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentStatusHistory;
use App\Models\User;
use App\Support\OfficeScope;
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
        ]);
        $equipment = OfficeScope::equipment(Equipment::query())->with('assignedUser', 'latestRiskPrediction')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('equipment_name', 'like', '%'.$search.'%')
                        ->orWhere('asset_code', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->latest()
            ->paginate(10)
            ->withQueryString();
        $users = OfficeScope::personnel(User::query())->orderBy('name')->get();
        $equipmentOptions = OfficeScope::equipment(Equipment::query())->orderBy('equipment_name')->get();
        $statuses = OfficeScope::equipment(Equipment::query())->select('status')->distinct()->orderBy('status')->pluck('status');
        $categories = EquipmentCategory::query()->orderBy('name')->pluck('name');

        return view('equipment.index', compact('equipment', 'users', 'equipmentOptions', 'statuses', 'categories'));
    }

    public function create(): View
    {
        $users = OfficeScope::personnel(User::query())->orderBy('name')->get();
        $categories = EquipmentCategory::query()->orderBy('name')->pluck('name');

        return view('equipment.create', compact('users', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $officeCode = $request->user()->role === 'super_admin'
            ? strtoupper(trim((string) $request->input('office_code')))
            : $request->user()->office_code;
        $assignedUserRule = Rule::exists('users', 'id')
            ->where(fn ($query) => $query->where('role', '<>', 'super_admin'))
            ->where(filled($officeCode)
                ? fn ($query) => $query->where('office_code', $officeCode)
                : fn ($query) => $query->whereRaw('1 = 0'));
        $validated = $request->validate([
            'equipment_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::exists('equipment_categories', 'name')],
            'office_code' => [Rule::requiredIf($request->user()->role === 'super_admin'), 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'asset_tag' => ['nullable', 'string', 'unique:equipment,asset_tag'],
            'serial_number' => ['nullable', 'string', 'unique:equipment,serial_number'],
            'manufacturer' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'commission_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string'],
            'assigned_personnel' => ['nullable', $assignedUserRule],
            'status' => ['required', 'string'],
            'service_age_years' => ['nullable', 'integer'],
            'engine_operating_hours' => ['nullable', 'integer'],
            'runtime_cycles' => ['nullable', 'integer'],
            'mechanical_failure_count' => ['nullable', 'integer'],
            'last_inspection_date' => ['nullable', 'date'],
            'next_inspection_date' => ['nullable', 'date'],
            'last_maintenance_date' => ['nullable', 'date'],
            'maintenance_interval_days' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
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

    public function edit(string $id): View
    {
        $equipment = OfficeScope::equipment(Equipment::query())->findOrFail($id);
        $users = OfficeScope::personnel(User::query())->orderBy('name')->get();
        $categories = EquipmentCategory::query()->orderBy('name')->pluck('name');

        return view('equipment.edit', compact('equipment', 'users', 'categories'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $equipment = OfficeScope::equipment(Equipment::query())->findOrFail($id);
        $officeCode = $request->user()->role === 'super_admin'
            ? strtoupper(trim((string) $request->input('office_code')))
            : $request->user()->office_code;
        $assignedUserRule = Rule::exists('users', 'id')
            ->where(fn ($query) => $query->where('role', '<>', 'super_admin'))
            ->where(filled($officeCode)
                ? fn ($query) => $query->where('office_code', $officeCode)
                : fn ($query) => $query->whereRaw('1 = 0'));
        $validated = $request->validate([
            'equipment_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::exists('equipment_categories', 'name')],
            'office_code' => [Rule::requiredIf($request->user()->role === 'super_admin'), 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'asset_tag' => ['nullable', 'string', 'unique:equipment,asset_tag,'.$equipment->id],
            'serial_number' => ['nullable', 'string', 'unique:equipment,serial_number,'.$equipment->id],
            'manufacturer' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'commission_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string'],
            'assigned_personnel' => ['nullable', $assignedUserRule],
            'status' => ['required', 'string'],
            'service_age_years' => ['nullable', 'integer'],
            'engine_operating_hours' => ['nullable', 'integer'],
            'runtime_cycles' => ['nullable', 'integer'],
            'mechanical_failure_count' => ['nullable', 'integer'],
            'last_inspection_date' => ['nullable', 'date'],
            'next_inspection_date' => ['nullable', 'date'],
            'last_maintenance_date' => ['nullable', 'date'],
            'maintenance_interval_days' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
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

        $equipment->update($validated);

        return redirect()->route('equipment.index')->with('success', 'Equipment updated successfully.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'super_admin', 403);
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:equipment_categories,name'],
        ]);

        EquipmentCategory::create(['name' => trim($validated['name'])]);

        return redirect()->route('equipment.index')->with('success', 'Equipment category added successfully.');
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
                    'commission_date' => ($row['commission_date'] ?? '') !== '' ? $row['commission_date'] : null,
                    'service_age_years' => ($row['service_age_years'] ?? '') !== '' ? $row['service_age_years'] : 0,
                    'engine_operating_hours' => ($row['engine_operating_hours'] ?? '') !== '' ? $row['engine_operating_hours'] : null,
                    'runtime_cycles' => ($row['runtime_cycles'] ?? '') !== '' ? $row['runtime_cycles'] : null,
                    'mechanical_failure_count' => ($row['mechanical_failure_count'] ?? '') !== '' ? $row['mechanical_failure_count'] : 0,
                    'last_inspection_date' => ($row['last_inspection_date'] ?? '') !== '' ? $row['last_inspection_date'] : null,
                    'next_inspection_date' => ($row['next_inspection_date'] ?? '') !== '' ? $row['next_inspection_date'] : null,
                    'last_maintenance_date' => ($row['last_maintenance_date'] ?? '') !== '' ? $row['last_maintenance_date'] : null,
                    'maintenance_interval_days' => ($row['maintenance_interval_days'] ?? '') !== '' ? $row['maintenance_interval_days'] : null,
                    'description' => ($row['description'] ?? '') !== '' ? $row['description'] : null,
                    'remarks' => ($row['remarks'] ?? '') !== '' ? $row['remarks'] : null,
                ];
                $validator = Validator::make($data, [
                    'equipment_name' => ['required', 'string', 'max:255'],
                    'category' => ['required', 'string', Rule::exists('equipment_categories', 'name')],
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
                    'service_age_years' => ['nullable', 'integer', 'min:0'],
                    'engine_operating_hours' => ['nullable', 'integer', 'min:0'],
                    'runtime_cycles' => ['nullable', 'integer', 'min:0'],
                    'mechanical_failure_count' => ['nullable', 'integer', 'min:0'],
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
                'asset_tag', 'serial_number', 'manufacturer', 'model', 'location', 'commission_date',
                'service_age_years', 'engine_operating_hours', 'runtime_cycles', 'mechanical_failure_count',
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
