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
