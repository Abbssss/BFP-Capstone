<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add User</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Full name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select id="role" name="role" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm" data-office-role>
                                @foreach(['admin' => 'Admin', 'maintenance' => 'Maintenance', 'super_admin' => 'Super Admin'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', 'maintenance') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="office_code" class="block text-sm font-medium text-gray-700">Office Code</label>
                            <input id="office_code" name="office_code" value="{{ old('office_code') }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" autocomplete="off" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" data-office-code required>
                            <p class="mt-1 text-sm text-gray-500">Super Admin accounts are not assigned to an office.</p>
                            @error('office_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Account status</label>
                            <select id="status" name="status" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            </select>
                            @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Temporary password</label>
                            <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('users.index') }}" class="rounded border px-4 py-2">Cancel</a>
                        <button type="submit" class="rounded bg-red-700 px-4 py-2 text-white">Create account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        const roleField = document.querySelector('[data-office-role]');
        const officeCodeField = document.querySelector('[data-office-code]');
        const updateOfficeRequirement = () => {
            officeCodeField.required = roleField.value !== 'super_admin';
            officeCodeField.disabled = roleField.value === 'super_admin';
            if (officeCodeField.disabled) officeCodeField.value = '';
        };
        roleField.addEventListener('change', updateOfficeRequirement);
        updateOfficeRequirement();
    </script>
</x-app-layout>
