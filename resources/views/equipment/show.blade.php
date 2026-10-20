<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Equipment Details</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h3 class="text-2xl font-bold">{{ $equipment->equipment_name }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                    <div><strong>Asset Code:</strong> {{ $equipment->asset_code }}</div>
                    <div><strong>Category:</strong> {{ $equipment->category }}</div>
                    <div><strong>Status:</strong> {{ $equipment->status }}</div>
                    <div><strong>Location:</strong> {{ $equipment->location }}</div>
                    <div><strong>Last Inspection:</strong> {{ $equipment->last_inspection_date ?? 'N/A' }}</div>
                    <div><strong>Next Inspection:</strong> {{ $equipment->next_inspection_date ?? 'N/A' }}</div>
                    <div><strong>Maintenance Interval:</strong> {{ $equipment->maintenance_interval_days ?? 'N/A' }} days</div>
                    <div><strong>Service Age:</strong> {{ $equipment->service_age_years ?? 'N/A' }} years</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
