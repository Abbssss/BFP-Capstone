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
                            <input type="text" name="equipment_name" value="{{ old('equipment_name') }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                            @error('equipment_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category</label>
                            <select name="category" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                                @foreach($categories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Office Code</label>
                            @if(auth()->user()->role === 'super_admin')
                                <input type="text" name="office_code" value="{{ old('office_code') }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required data-equipment-office-code>
                            @else
                                <input type="text" value="{{ auth()->user()->office_code }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" readonly data-equipment-office-code>
                            @endif
                            @error('office_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Asset Tag</label>
                            <input type="text" name="asset_tag" value="{{ old('asset_tag') }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                            @error('asset_tag')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Serial Number</label>
                            <input type="text" name="serial_number" value="{{ old('serial_number') }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                            @error('serial_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Manufacturer</label>
                            <input type="text" name="manufacturer" value="{{ old('manufacturer') }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                            @error('manufacturer')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Model</label>
                            <input type="text" name="model" value="{{ old('model') }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                            @error('model')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                                @foreach(['Operational','Operational with Restrictions','Under Inspection','Under Maintenance','Under Repair','Out of Service','Retired'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'Operational') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Location</label>
                            <input type="text" name="location" value="{{ old('location') }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                            @error('location')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date Acquired</label>
                            <input type="date" name="commission_date" value="{{ old('commission_date') }}" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                            @error('commission_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('description') }}</textarea>
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
