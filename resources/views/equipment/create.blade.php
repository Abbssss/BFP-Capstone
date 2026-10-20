<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Register Equipment</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('equipment.store') }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Equipment Name</label>
                            <input type="text" name="equipment_name" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category</label>
                            <select name="category" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                                @foreach($categories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Office Code</label>
                            @if(auth()->user()->role === 'super_admin')
                                <input type="text" name="office_code" value="{{ old('office_code') }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                            @else
                                <input type="text" value="{{ auth()->user()->office_code }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" readonly>
                            @endif
                            @error('office_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Asset Tag</label>
                            <input type="text" name="asset_tag" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Serial Number</label>
                            <input type="text" name="serial_number" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Manufacturer</label>
                            <input type="text" name="manufacturer" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Model</label>
                            <input type="text" name="model" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Commission Date</label>
                            <input type="date" name="commission_date" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option>Operational</option>
                                <option>Operational with Restrictions</option>
                                <option>Under Inspection</option>
                                <option>Under Maintenance</option>
                                <option>Under Repair</option>
                                <option>Out of Service</option>
                                <option>Retired</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Location</label>
                            <input type="text" name="location" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Assigned Personnel</label>
                            <select name="assigned_personnel" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">None</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Service Age Years</label>
                            <input type="number" name="service_age_years" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" min="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Engine Operating Hours</label>
                            <input type="number" name="engine_operating_hours" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" min="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Runtime Cycles</label>
                            <input type="number" name="runtime_cycles" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" min="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mechanical Failure Count</label>
                            <input type="number" name="mechanical_failure_count" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" min="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Maintenance Date</label>
                            <input type="date" name="last_maintenance_date" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Maintenance Interval (days)</label>
                            <input type="number" name="maintenance_interval_days" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" min="0">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Remarks</label>
                        <textarea name="remarks" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('equipment.index') }}" class="rounded border px-4 py-2">Cancel</a>
                        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">Save Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
