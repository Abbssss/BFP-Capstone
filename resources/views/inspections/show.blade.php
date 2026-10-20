<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Inspection Details</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-2xl font-bold">{{ $inspection->reference_number }}</h3>
                        <p class="text-gray-600">{{ $inspection->inspection_type }}</p>
                    </div>
                    <a href="{{ route('inspections.edit', $inspection) }}" class="rounded bg-indigo-600 px-4 py-2 text-white">Edit</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><strong>Equipment:</strong> {{ $inspection->equipment?->equipment_name }}</div>
                    <div><strong>Inspector:</strong> {{ $inspection->inspector_name }}</div>
                    <div><strong>Inspection Date:</strong> {{ $inspection->inspection_date }}</div>
                    <div><strong>Outcome:</strong> {{ $inspection->outcome }}</div>
                    <div><strong>Condition Observed:</strong> {{ $inspection->condition_observed ?? 'N/A' }}</div>
                    <div><strong>Safety Concerns:</strong> {{ $inspection->safety_concerns ?? 'N/A' }}</div>
                    <div class="md:col-span-2"><strong>Functional Test Result:</strong> {{ $inspection->functional_test_result ?? 'N/A' }}</div>
                    <div class="md:col-span-2"><strong>Defects Identified:</strong> {{ $inspection->defects_identified ?? 'N/A' }}</div>
                    <div class="md:col-span-2"><strong>Recommended Action:</strong> {{ $inspection->recommended_action ?? 'N/A' }}</div>
                    <div class="md:col-span-2"><strong>Notes:</strong> {{ $inspection->notes ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
