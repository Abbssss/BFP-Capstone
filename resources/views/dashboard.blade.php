<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('FIRE-PMIS Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                    <div class="text-sm text-gray-500">Total Equipment</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $equipmentCount }}</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                    <div class="text-sm text-gray-500">Inspection Records</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $inspectionCount }}</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                    <div class="text-sm text-gray-500">Maintenance Tasks</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $maintenanceCount }}</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                    <div class="text-sm text-gray-500">Personnel</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $personnelCount }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Operational Status</h3>
                    <ul class="space-y-2">
                        @foreach($riskSummary as $status => $total)
                            <li class="flex justify-between">
                                <span>{{ $status }}</span>
                                <span class="font-semibold">{{ $total }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="{{ route('equipment.index') }}" class="block rounded bg-blue-600 px-4 py-2 text-white text-center">Manage Assets</a>
                        <a href="{{ route('inspections.index') }}" class="block rounded bg-indigo-600 px-4 py-2 text-white text-center">Inspection Records</a>
                        <a href="{{ route('maintenances.index') }}" class="block rounded bg-emerald-600 px-4 py-2 text-white text-center">Maintenance Tasks</a>
                        <a href="{{ route('risk.index') }}" class="block rounded bg-amber-500 px-4 py-2 text-white text-center">Risk Classification</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
