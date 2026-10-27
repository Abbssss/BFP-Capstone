<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Maintenance Task</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('maintenances.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Equipment</label>
                            <div class="equipment-select-search-wrapper" data-equipment-select-search-wrapper>
                                <input type="search" aria-label="Search and select equipment" placeholder="Select or search equipment..." data-equipment-select-search hidden>
                                <select name="equipment_id" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required data-assignee-equipment-select data-searchable-equipment>
                                    <option value="">Select equipment</option>
                                    @foreach($equipment as $item)
                                        <option value="{{ $item->id }}" data-assigned-personnel-id="{{ $item->assigned_personnel }}" data-assigned-personnel-name="{{ $item->assignedUser?->name ?? '' }}" @selected(old('equipment_id') == $item->id)>{{ $item->equipment_name }}</option>
                                    @endforeach
                                </select>
                                <small data-equipment-select-empty hidden>No equipment matches your search.</small>
                            </div>
                        </div>
                        <div data-equipment-assignee-field hidden>
                            <label class="block text-sm font-medium text-gray-700" for="maintenance-assigned-to">Who will do the job?</label>
                            <select id="maintenance-assigned-to" name="assigned_to_id" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" data-maintenance-assignee-select>
                                <option value="">Select personnel</option>
                                @foreach($personnelOptions as $person)
                                    <option value="{{ $person->id }}" @selected(old('assigned_to_id') == $person->id)>{{ $person->name }}</option>
                                @endforeach
                            </select>
                            <small data-equipment-assigned-personnel></small>
                            @error('assigned_to_id')<span class="text-red-700 text-sm">{{ $message }}</span>@enderror
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700">Request Number</label><p>Generated when saved: first 3 equipment-name letters-MM-DD-YYYY-sequence.</p></div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Maintenance Type</label>
                            <input type="text" name="maintenance_type" value="{{ old('maintenance_type', $defaultMaintenanceType) }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Priority</label>
                            <select name="priority" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option>Low</option>
                                <option selected>Medium</option>
                                <option>High</option>
                                <option>Critical</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option>Pending</option>
                                <option>Approved</option>
                                <option>Scheduled</option>
                                <option>In Progress</option>
                                <option>Completed</option>
                                <option>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" name="start_date" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Completion Date</label>
                            <input type="date" name="completion_date" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reported Issue</label>
                        <textarea name="reported_issue" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Diagnosis</label>
                        <textarea name="diagnosis" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Action Taken</label>
                        <textarea name="action_taken" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Parts Replaced</label>
                        <textarea name="parts_replaced" rows="2" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('maintenances.index') }}" class="rounded border px-4 py-2">Cancel</a>
                        <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-white">Save Maintenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
