<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Inspection</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('inspections.update', $inspection) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Equipment</label>
                            <div class="equipment-select-search-wrapper" data-equipment-select-search-wrapper>
                                <input type="search" aria-label="Search and select equipment" placeholder="Select or search equipment..." data-equipment-select-search hidden>
                                <select name="equipment_id" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required data-searchable-equipment>
                                    <option value="">Select equipment</option>
                                @foreach($equipment as $item)
                                    <option value="{{ $item->id }}" @selected(old('equipment_id', $inspection->equipment_id) == $item->id)>{{ $item->equipment_name }}</option>
                                @endforeach
                                </select>
                                <small data-equipment-select-empty hidden>No equipment matches your search.</small>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Reference Number</label>
                            <input type="text" name="reference_number" value="{{ old('reference_number', $inspection->reference_number) }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Inspection Date</label>
                            <input type="datetime-local" name="inspection_date" value="{{ old('inspection_date', $inspection->inspection_date?->format('Y-m-d\TH:i')) }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Inspector</label>
                            <input type="text" name="inspector_name" value="{{ old('inspector_name', $inspection->inspector_name) }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Inspection Type</label>
                            <input type="text" name="inspection_type" value="{{ old('inspection_type', $inspection->inspection_type) }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Outcome</label>
                            <select name="outcome" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach(['Passed','Passed with Findings','Failed','Requires Further Assessment'] as $value)
                                    <option value="{{ $value }}" @selected(old('outcome', $inspection->outcome) == $value)>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Observed Condition</label>
                        <textarea name="condition_observed" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('condition_observed', $inspection->condition_observed) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Functional Test Result</label>
                        <textarea name="functional_test_result" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('functional_test_result', $inspection->functional_test_result) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Defects Identified</label>
                        <textarea name="defects_identified" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('defects_identified', $inspection->defects_identified) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Safety Concerns</label>
                        <textarea name="safety_concerns" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('safety_concerns', $inspection->safety_concerns) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Recommended Action</label>
                        <textarea name="recommended_action" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('recommended_action', $inspection->recommended_action) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('notes', $inspection->notes) }}</textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('inspections.index') }}" class="rounded border px-4 py-2">Cancel</a>
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-white">Update Inspection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
