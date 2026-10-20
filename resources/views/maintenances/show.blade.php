<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Maintenance Details</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-2xl font-bold">{{ $maintenance->request_number }}</h3>
                        <p class="text-gray-600">{{ $maintenance->maintenance_type }}</p>
                    </div>
                    <a href="{{ route('maintenances.edit', $maintenance) }}" class="rounded bg-emerald-600 px-4 py-2 text-white">Edit</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><strong>Equipment:</strong> {{ $maintenance->equipment?->equipment_name }}</div>
                    <div><strong>Priority:</strong> {{ $maintenance->priority }}</div>
                    <div><strong>Status:</strong> {{ $maintenance->status }}</div>
                    <div><strong>Technician:</strong> {{ $maintenance->technician_name ?? 'N/A' }}</div>
                    <div><strong>Start Date:</strong> {{ $maintenance->start_date ?? 'N/A' }}</div>
                    <div><strong>Completion Date:</strong> {{ $maintenance->completion_date ?? 'N/A' }}</div>
                    <div class="md:col-span-2"><strong>Reported Issue:</strong> {{ $maintenance->reported_issue ?? 'N/A' }}</div>
                    <div class="md:col-span-2"><strong>Diagnosis:</strong> {{ $maintenance->diagnosis ?? 'N/A' }}</div>
                    <div class="md:col-span-2"><strong>Action Taken:</strong> {{ $maintenance->action_taken ?? 'N/A' }}</div>
                    <div class="md:col-span-2"><strong>Parts Replaced:</strong> {{ $maintenance->parts_replaced ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
