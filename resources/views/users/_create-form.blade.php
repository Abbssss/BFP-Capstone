<form method="POST" action="{{ route('users.store') }}">
    @csrf
    <input type="hidden" name="_form" value="create-user">
    <div class="app-modal-grid">
        <div class="app-modal-field full-width">
            <label for="{{ $formPrefix }}-name">Full name</label>
            <input id="{{ $formPrefix }}-name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name">
            @error('name')<span class="app-modal-error">{{ $message }}</span>@enderror
        </div>
        <div class="app-modal-field full-width">
            <label for="{{ $formPrefix }}-email">Email address</label>
            <input id="{{ $formPrefix }}-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
            @error('email')<span class="app-modal-error">{{ $message }}</span>@enderror
        </div>
        <div class="app-modal-field">
            <label for="{{ $formPrefix }}-role">Role</label>
            <select id="{{ $formPrefix }}-role" name="role" required data-office-role>
                @foreach(['admin' => 'Admin', 'maintenance' => 'Maintenance', 'super_admin' => 'Super Admin'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', 'maintenance') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('role')<span class="app-modal-error">{{ $message }}</span>@enderror
        </div>
        <div class="app-modal-field">
            <label for="{{ $formPrefix }}-office-code">Office Code</label>
            <input id="{{ $formPrefix }}-office-code" name="office_code" value="{{ old('office_code') }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" autocomplete="off" data-office-code required>
            <small>Assign this account to its station office. Super Admin accounts are not assigned to an office.</small>
            @error('office_code')<span class="app-modal-error">{{ $message }}</span>@enderror
        </div>
        <div class="app-modal-field">
            <label for="{{ $formPrefix }}-status">Account status</label>
            <select id="{{ $formPrefix }}-status" name="status" required>
                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
            </select>
            @error('status')<span class="app-modal-error">{{ $message }}</span>@enderror
        </div>
        <div class="app-modal-field">
            <label for="{{ $formPrefix }}-password">Temporary password</label>
            <input id="{{ $formPrefix }}-password" name="password" type="password" required autocomplete="new-password">
            @error('password')<span class="app-modal-error">{{ $message }}</span>@enderror
        </div>
        <div class="app-modal-field">
            <label for="{{ $formPrefix }}-password-confirmation">Confirm password</label>
            <input id="{{ $formPrefix }}-password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
    </div>
    <div class="app-modal-footer">
        <button type="button" class="app-modal-button" data-modal-close>Cancel</button>
        <button type="submit" class="app-modal-button primary">Create account</button>
    </div>
</form>
<script>
    document.querySelectorAll('[data-office-role]').forEach((roleField) => {
        const officeCodeField = roleField.form.querySelector('[data-office-code]');
        const updateOfficeRequirement = () => {
            officeCodeField.required = roleField.value !== 'super_admin';
            officeCodeField.disabled = roleField.value === 'super_admin';
            if (officeCodeField.disabled) officeCodeField.value = '';
        };
        roleField.addEventListener('change', updateOfficeRequirement);
        updateOfficeRequirement();
    });
</script>
