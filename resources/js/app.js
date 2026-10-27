import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const openButton = event.target.closest('[data-modal-open]');
    if (openButton) {
        const dialog = document.getElementById(openButton.dataset.modalOpen);
        if (openButton.hasAttribute('data-inspection-equipment-id') && dialog) {
            dialog.querySelector('[name="equipment_id"]').value = openButton.dataset.inspectionEquipmentId;
            dialog.querySelector('[name="_equipment_id"]').value = openButton.dataset.inspectionEquipmentId;
            dialog.querySelector('#complete-inspection-equipment-name').textContent =
                `${openButton.dataset.inspectionEquipmentName} · ${openButton.dataset.inspectionEquipmentId}`;
        }
        if (openButton.hasAttribute('data-work-equipment-id') && dialog) {
            const form = dialog.querySelector('[data-equipment-work-record-form]');
            form.action = openButton.dataset.workRecordUrl;
            form.querySelector('[name="_equipment_id"]').value = openButton.dataset.workEquipmentId;
            dialog.querySelector('#equipment-work-record-equipment').textContent =
                `${openButton.dataset.workEquipmentName} · ${openButton.dataset.workEquipmentId}`;
            dialog.querySelector('#equipment-work-record-title').textContent = `Record ${openButton.dataset.workKind.toLowerCase()}`;
            dialog.querySelector('#equipment-work-type').value =
                openButton.dataset.workKind === 'Repair' ? 'Repair' : 'Preventive maintenance';
            dialog.querySelector('#equipment-work-resulting-status').value = openButton.dataset.workDefaultStatus;
            dialog.querySelector('#equipment-work-issue').required = openButton.dataset.workKind === 'Repair';
        }
        dialog?.showModal();
        return;
    }

    const closeButton = event.target.closest('[data-modal-close]');
    if (closeButton) {
        closeButton.closest('dialog')?.close();
        return;
    }

    if (event.target instanceof HTMLDialogElement && event.target.classList.contains('app-modal')) {
        event.target.close();
    }
});

document.querySelectorAll('dialog.app-modal[data-open-on-load="true"]').forEach((dialog) => {
    if (!dialog.open) {
        dialog.showModal();
    }
});

document.querySelectorAll('[data-pending-status-select]').forEach((select) => {
    const dialog = select.closest('dialog');
    const completionFields = dialog?.querySelectorAll('[data-completion-fields]');
    const syncCompletionFields = () => {
        const isCompleted = select.value === 'Completed';
        completionFields?.forEach((field) => {
            field.hidden = !isCompleted;
            const input = field.querySelector('input, textarea');
            if (input) {
                input.required = isCompleted;
            }
        });
    };

    syncCompletionFields();
    select.addEventListener('change', syncCompletionFields);
});

document.querySelectorAll('[data-searchable-equipment]').forEach((select, index) => {
    const wrapper = select.closest('[data-equipment-select-search-wrapper]');
    const searchInput = wrapper?.querySelector('[data-equipment-select-search]');
    const emptyMessage = wrapper?.querySelector('[data-equipment-select-empty]');

    if (!wrapper || !searchInput || !emptyMessage) {
        return;
    }

    const listbox = document.createElement('div');
    const listboxId = `equipment-options-${index + 1}`;
    listbox.id = listboxId;
    listbox.className = 'equipment-select-options';
    listbox.setAttribute('role', 'listbox');
    listbox.hidden = true;
    emptyMessage.hidden = true;
    listbox.append(emptyMessage);
    wrapper.append(listbox);

    searchInput.hidden = false;
    searchInput.required = true;
    searchInput.setAttribute('role', 'combobox');
    searchInput.setAttribute('aria-autocomplete', 'list');
    searchInput.setAttribute('aria-haspopup', 'listbox');
    searchInput.setAttribute('aria-expanded', 'false');
    searchInput.setAttribute('aria-controls', listboxId);
    select.required = false;
    select.hidden = true;

    const selectedOption = select.selectedOptions[0];
    if (selectedOption?.value) {
        searchInput.value = selectedOption.textContent.trim();
    }

    let activeIndex = -1;

    const closeListbox = () => {
        listbox.hidden = true;
        searchInput.setAttribute('aria-expanded', 'false');
        searchInput.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    };

    const chooseOption = (option) => {
        select.value = option.value;
        searchInput.value = option.textContent.trim();
        searchInput.setCustomValidity('');
        closeListbox();
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const renderOptions = () => {
        const term = searchInput.value.trim().toLocaleLowerCase();
        listbox.querySelectorAll('[role="option"]').forEach((option) => option.remove());

        const matches = [...select.options].filter((option) =>
            option.value && option.textContent.trim().toLocaleLowerCase().includes(term)
        );

        matches.forEach((option, optionIndex) => {
            const result = document.createElement('div');
            result.id = `${listboxId}-option-${optionIndex}`;
            result.className = 'equipment-select-option';
            result.setAttribute('role', 'option');
            result.setAttribute('aria-selected', String(option.value === select.value));
            result.dataset.equipmentId = option.value;
            result.textContent = option.textContent.trim();
            result.addEventListener('mousedown', (event) => event.preventDefault());
            result.addEventListener('click', () => chooseOption(option));
            listbox.insertBefore(result, emptyMessage);
        });

        emptyMessage.hidden = matches.length > 0;
        listbox.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
        activeIndex = -1;
        searchInput.removeAttribute('aria-activedescendant');
    };

    searchInput.addEventListener('focus', renderOptions);
    searchInput.addEventListener('input', () => {
        if (select.value) {
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        searchInput.setCustomValidity('Select an equipment item from the list.');
        renderOptions();
    });
    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (listbox.hidden) {
                renderOptions();
            }
            const options = [...listbox.querySelectorAll('[role="option"]')];
            if (options.length) {
                if (activeIndex < 0) {
                    activeIndex = event.key === 'ArrowDown' ? 0 : options.length - 1;
                } else {
                    activeIndex = event.key === 'ArrowDown'
                        ? (activeIndex + 1) % options.length
                        : (activeIndex - 1 + options.length) % options.length;
                }
                searchInput.setAttribute('aria-activedescendant', options[activeIndex].id);
                options.forEach((option, optionIndex) => {
                    option.classList.toggle('active', optionIndex === activeIndex);
                });
                options[activeIndex].scrollIntoView({ block: 'nearest' });
            }
        } else if (event.key === 'Enter' && activeIndex >= 0 && !listbox.hidden) {
            event.preventDefault();
            const option = listbox.querySelectorAll('[role="option"]')[activeIndex];
            const selected = [...select.options].find((item) => item.value === option.dataset.equipmentId);
            if (selected) {
                chooseOption(selected);
            }
        } else if (event.key === 'Escape') {
            closeListbox();
        }
    });

    document.addEventListener('click', (event) => {
        if (event.target instanceof Node && !wrapper.contains(event.target)) {
            closeListbox();
        }
    });
});

const syncMaintenanceAssignee = (equipmentSelect, useEquipmentAssignee = false) => {
    const form = equipmentSelect.closest('form');
    const field = form?.querySelector('[data-equipment-assignee-field]');
    const assigneeSelect = field?.querySelector('[data-maintenance-assignee-select]');
    const assignedPersonnel = field?.querySelector('[data-equipment-assigned-personnel]');
    const equipmentOption = equipmentSelect.selectedOptions[0];

    if (!field || !assigneeSelect || !equipmentOption?.value) {
        if (field) {
            field.hidden = true;
        }
        return;
    }

    field.hidden = false;
    assignedPersonnel.textContent = equipmentOption.dataset.assignedPersonnelName
        ? `Equipment currently assigned to ${equipmentOption.dataset.assignedPersonnelName}.`
        : 'No personnel is currently assigned to this equipment.';

    if (useEquipmentAssignee || !assigneeSelect.value) {
        const assignedId = equipmentOption.dataset.assignedPersonnelId;
        assigneeSelect.value = [...assigneeSelect.options].some((option) => option.value === assignedId)
            ? assignedId
            : '';
    }
};

document.querySelectorAll('[data-assignee-equipment-select]').forEach((equipmentSelect) => {
    syncMaintenanceAssignee(equipmentSelect);
    equipmentSelect.addEventListener('change', () => syncMaintenanceAssignee(equipmentSelect, true));
});

const syncEquipmentPersonnelOptions = (form) => {
    const officeInput = form.querySelector('[data-equipment-office-code]');
    const personnelSelect = form.querySelector('[data-equipment-personnel-select]');
    if (!officeInput || !personnelSelect) {
        return;
    }

    const officeCode = officeInput.value.trim().toUpperCase();
    const selectedOption = personnelSelect.selectedOptions[0];
    const shouldFilterByOffice = officeCode !== '';

    [...personnelSelect.options].forEach((option) => {
        if (!option.value) {
            return;
        }

        const matchesOffice = !shouldFilterByOffice
            || option.dataset.officeCode?.trim().toUpperCase() === officeCode;
        option.hidden = !matchesOffice;
        option.disabled = !matchesOffice;
    });

    if (selectedOption?.value && selectedOption.disabled) {
        personnelSelect.value = '';
    }
};

document.querySelectorAll('form').forEach((form) => {
    const officeInput = form.querySelector('[data-equipment-office-code]');
    if (!officeInput || !form.querySelector('[data-equipment-personnel-select]')) {
        return;
    }

    syncEquipmentPersonnelOptions(form);
    officeInput.addEventListener('input', () => syncEquipmentPersonnelOptions(form));
    form.querySelector('[data-equipment-personnel-select]').addEventListener('change', (event) => {
        const selectedOption = event.currentTarget.selectedOptions[0];
        if (!officeInput.readOnly && selectedOption?.dataset.officeCode) {
            officeInput.value = selectedOption.dataset.officeCode;
            syncEquipmentPersonnelOptions(form);
        }
    });
});
