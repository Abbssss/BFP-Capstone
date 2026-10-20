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
