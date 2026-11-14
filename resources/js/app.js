document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const menu = document.querySelector(`.${button.dataset.menuToggle}`);
        if (!menu) return;

        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!isExpanded));
        menu.hidden = isExpanded;
    });
});

document.querySelectorAll('[data-platform-tab]').forEach((tab) => {
    tab.addEventListener('click', (event) => {
        event.preventDefault();
        const panelId = tab.dataset.platformTab;
        const selectedPanel = document.getElementById(panelId);
        if (!selectedPanel) return;

        document.querySelectorAll('[data-platform-tab]').forEach((otherTab) => {
            const isActive = otherTab === tab;
            otherTab.classList.toggle('active', isActive);
            otherTab.setAttribute('aria-selected', String(isActive));
            otherTab.setAttribute('tabindex', isActive ? '0' : '-1');
        });
        document.querySelectorAll('[data-platform-panel]').forEach((panel) => {
            panel.hidden = panel !== selectedPanel;
        });
    });
});

document.addEventListener('click', (event) => {
    document.querySelectorAll('[data-profile-dropdown][open]').forEach((dropdown) => {
        if (!dropdown.contains(event.target)) dropdown.open = false;
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('[data-profile-dropdown][open]').forEach((dropdown) => {
            dropdown.open = false;
            dropdown.querySelector('summary')?.focus();
        });
    }
});

document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => button.closest('[data-dismissible]')?.remove());
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = button.parentElement?.querySelector('input');
        if (!input) return;

        const isVisible = input.type === 'text';
        input.type = isVisible ? 'password' : 'text';
        button.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        button.setAttribute('aria-pressed', String(!isVisible));
        input.focus();
    });
});

document.querySelectorAll('[data-auth-form]').forEach((form) => {
    const fields = [...form.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"])')];
    const submitButton = form.querySelector('button[type="submit"]');
    const submitLabel = submitButton?.querySelector('[data-submit-label]');

    const updateFieldState = (field, showFeedback = false) => {
        const confirmation = field.name === 'password_confirmation';
        const password = confirmation ? form.querySelector('input[name="password"]') : null;
        field.setCustomValidity(confirmation && field.value && password?.value !== field.value ? 'Passwords do not match.' : '');

        const isInvalid = !field.validity.valid;
        field.toggleAttribute('aria-invalid', showFeedback && isInvalid);
        const errorId = `${field.id}-auth-error`;
        let error = document.getElementById(errorId);

        if (!error && showFeedback && isInvalid) {
            error = document.createElement('p');
            error.className = 'field-error auth-live-error';
            error.id = errorId;
            error.setAttribute('aria-live', 'polite');
            (field.closest('.auth-password-wrap') ?? field).insertAdjacentElement('afterend', error);
        }

        if (error) {
            field.setAttribute('aria-describedby', errorId);
            error.textContent = isInvalid ? (field.validationMessage || 'Check this field.') : '';
            error.hidden = !isInvalid;
            if (!isInvalid) field.removeAttribute('aria-describedby');
        }
        field.classList.toggle('auth-field-invalid', showFeedback && isInvalid);
        field.classList.toggle('auth-field-valid', showFeedback && field.value.length > 0 && !isInvalid);
    };

    fields.forEach((field) => {
        field.addEventListener('input', () => {
            updateFieldState(field, true);
            if (field.name === 'password') {
                const confirmation = form.querySelector('input[name="password_confirmation"]');
                if (confirmation?.value) updateFieldState(confirmation, true);
            }
        });
        field.addEventListener('blur', () => updateFieldState(field, true));
    });

    form.addEventListener('submit', (event) => {
        fields.forEach((field) => updateFieldState(field, true));
        if (!form.checkValidity()) {
            event.preventDefault();
            form.querySelector('[aria-invalid="true"]')?.focus();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            if (submitLabel) submitLabel.textContent = form.dataset.submittingLabel || 'Please wait…';
        }
    });
});

document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.modalOpen);
        const form = modal?.querySelector('[data-record-form]');
        if (!modal) return;
        if (!form) {
            modal.showModal();
            return;
        }

        form.reset();
        resetUnitGroups(form);
        setUnitBulkMode(form, form.querySelector('[data-bulk-mode-field]')?.value || 'single');
        form.action = form.dataset.createUrl;
        form.querySelector('[data-method-field]').value = 'POST';
        modal.querySelector('[data-modal-title]').textContent = `Add ${modal.dataset.recordLabel}`;
        modal.querySelector('[data-submit-label]').textContent = `Save ${modal.dataset.recordLabel}`;

        if (button.dataset.record) {
            const record = JSON.parse(button.dataset.record);
            form.action = button.dataset.updateUrl;
            form.querySelector('[data-method-field]').value = 'PUT';
            modal.querySelector('[data-modal-title]').textContent = `Edit ${modal.dataset.recordLabel}`;
            modal.querySelector('[data-submit-label]').textContent = `Save changes`;
            setUnitBulkMode(form, 'single');

            form.querySelectorAll('[data-value-field]').forEach((field) => { field.value = ''; });
            ['title', 'reference', 'status', 'summary', 'amount', 'recorded_on'].forEach((key) => {
                const field = form.querySelector(`[data-field="${key}"]`);
                if (field) field.value = key === 'recorded_on' ? String(record[key] ?? '').slice(0, 10) : (record[key] ?? '');
            });

            Object.entries(record.values ?? {}).forEach(([key, value]) => {
                const field = form.querySelector(`[data-value-field="${key}"]`);
                if (field) field.value = value ?? '';
            });
        }

        modal.showModal();
    });
});

function setUnitBulkMode(form, mode) {
    const modeSection = form.querySelector('[data-unit-bulk-mode]');
    if (!modeSection) return;
    const isBulk = mode === 'bulk';
    const isMixed = mode === 'mixed';
    const modeField = form.querySelector('[data-bulk-mode-field]');
    if (modeField) modeField.value = mode;

    form.querySelectorAll('[data-single-unit-field]').forEach((field) => { field.hidden = isBulk || isMixed; });
    form.querySelectorAll('[data-bulk-unit-field]').forEach((field) => {
        field.hidden = !isBulk;
        const input = field.querySelector('input, select');
        if (input) input.disabled = !isBulk;
    });
    const preview = form.querySelector('[data-bulk-preview]');
    preview.hidden = !isBulk;
    const mixedGroups = form.querySelector('[data-mixed-groups]');
    mixedGroups.hidden = !isMixed;
    mixedGroups.querySelectorAll('input, select').forEach((field) => { field.disabled = !isMixed; });
    const bulkOccupancy = form.querySelector('[data-bulk-occupancy]');
    if (bulkOccupancy) {
        bulkOccupancy.hidden = !isBulk;
        bulkOccupancy.querySelectorAll('input').forEach((field) => { field.disabled = !isBulk; });
    }
    const singleOccupancy = form.querySelector('[data-single-occupancy]');
    if (singleOccupancy) {
        singleOccupancy.hidden = isBulk || isMixed;
        singleOccupancy.querySelector('select').disabled = isBulk || isMixed;
    }
    updateBulkOccupancyTotal(form);
    modeSection.querySelectorAll('[data-unit-mode]').forEach((button) => button.classList.toggle('is-active', button.dataset.unitMode === mode));
    const title = form.querySelector('[data-field="title"]');
    if (title) title.disabled = isBulk || isMixed;
    const help = modeSection.querySelector('[data-unit-mode-help]');
    if (help) help.textContent = isBulk ? 'We’ll create similarly typed units using one label sequence.' : (isMixed ? 'Add one or more groups with different unit types and label sequences.' : 'Enter one unit label.');
    updateBulkUnitPreview(form);
    updateMixedUnitGroups(form);
}

function updateBulkUnitPreview(form) {
    const preview = form.querySelector('[data-bulk-preview]');
    if (!preview || preview.hidden) return;

    const count = Math.max(2, Number(form.querySelector('[data-bulk-count]')?.value || 2));
    const startField = form.querySelector('[data-bulk-start]');
    const sequence = form.querySelector('[data-bulk-sequence]')?.value || 'numeric';
    let rawStart = startField?.value || (sequence === 'letters' ? 'A' : '1');
    if (sequence === 'letters' && !/^[A-Za-z]+$/.test(rawStart)) rawStart = 'A';
    if (sequence === 'numeric' && !/^\d+$/.test(rawStart)) rawStart = '1';
    if (startField && !startField.value) startField.value = rawStart;
    const numericStart = Math.max(0, Number(rawStart || 0));
    const prefix = form.querySelector('[data-bulk-prefix]')?.value || '';
    const letterStart = letterLabelIndex(rawStart);
    const prefixField = form.querySelector('[data-bulk-prefix]');
    if (prefixField) prefixField.disabled = sequence === 'letters' || form.querySelector('[data-bulk-mode-field]')?.value !== 'bulk';
    const prefixHelp = prefixField?.closest('label')?.querySelector('small');
    if (prefixHelp) prefixHelp.textContent = sequence === 'letters' ? 'Letter sequences use the start letter directly (A, B, C…). Prefixes apply to number sequences.' : 'Labels will use this prefix, for example A1, A2, A3…';
    const labels = Array.from({ length: Math.min(count, 5) }, (_, index) => `${sequence === 'letters' ? '' : prefix}${sequence === 'letters' ? letterLabel(letterStart + index) : numericStart + index}`);
    preview.textContent = `Preview: ${labels.join(', ')}${count > labels.length ? ', …' : ''} · ${count} units`;
    const startLabel = form.querySelector('[data-bulk-start-label]');
    const startHelp = form.querySelector('[data-bulk-start-help]');
    if (startLabel) startLabel.textContent = sequence === 'letters' ? 'Start letter' : 'Start number';
    if (startHelp) startHelp.textContent = sequence === 'letters' ? 'Use A through Z, then AA, AB, and so on.' : 'First generated label will use this number.';
    if (startField) startField.placeholder = sequence === 'letters' ? 'A' : '1';
}

function letterLabelIndex(label) {
    let index = 0;
    for (const letter of String(label).toUpperCase()) index = (index * 26) + letter.charCodeAt(0) - 64;
    return index - 1;
}

function letterLabel(index) {
    let label = '';
    while (index >= 0) {
        label = String.fromCharCode((index % 26) + 65) + label;
        index = Math.floor(index / 26) - 1;
    }
    return label;
}

document.querySelectorAll('[data-unit-bulk-mode]').forEach((section) => {
    const form = section.closest('form');
    section.querySelectorAll('[data-unit-mode]').forEach((button) => button.addEventListener('click', () => setUnitBulkMode(form, button.dataset.unitMode)));
    form.querySelectorAll('[data-bulk-count], [data-bulk-start], [data-bulk-prefix]').forEach((field) => field.addEventListener('input', () => {
        if (field.matches('[data-bulk-count]')) {
            const counts = [...form.querySelectorAll('[data-bulk-occupancy-count]')];
            if (counts.filter((item) => Number(item.value || 0) > 0 && item.dataset.bulkOccupancyCount !== 'vacant').length === 0) counts.find((item) => item.dataset.bulkOccupancyCount === 'vacant').value = field.value;
        }
        updateBulkOccupancyTotal(form);
        updateBulkUnitPreview(form);
    }));
    form.querySelectorAll('[data-bulk-occupancy-count]').forEach((field) => field.addEventListener('input', () => updateBulkOccupancyTotal(form)));
    form.querySelector('[data-bulk-sequence]')?.addEventListener('change', () => updateBulkUnitPreview(form));

    const groupList = form.querySelector('[data-unit-group-list]');
    const groupTemplate = form.querySelector('[data-unit-group-template]');
    const addGroup = () => {
        groupList.append(groupTemplate.content.cloneNode(true));
        reindexUnitGroups(form);
    };
    form.querySelector('[data-add-unit-group]')?.addEventListener('click', addGroup);
    groupList.addEventListener('click', (event) => {
        if (event.target.closest('[data-remove-unit-group]')) {
            if (groupList.querySelectorAll('[data-unit-group]').length > 1) event.target.closest('[data-unit-group]').remove();
            reindexUnitGroups(form);
        }
    });
    groupList.addEventListener('input', () => updateMixedUnitGroups(form));
    groupList.addEventListener('change', (event) => {
        const group = event.target.closest('[data-unit-group]');
        if (group && event.target.dataset.groupField === 'sequence') {
            const start = group.querySelector('[data-group-field="start"]');
            if (start.value === '1' && event.target.value === 'letters') start.value = 'A';
            if (start.value === 'A' && event.target.value === 'numeric') start.value = '1';
            group.querySelector('[data-group-start-label]').textContent = event.target.value === 'letters' ? 'Start letter' : 'Start number';
        }
        updateMixedUnitGroups(form);
    });
    let oldGroups = [];
    try { oldGroups = JSON.parse(form.closest('dialog')?.dataset.oldUnitGroups || '[]'); } catch { oldGroups = []; }
    if (oldGroups.length) {
        groupList.replaceChildren();
        oldGroups.slice(0, 20).forEach((values) => {
            addGroup();
            const group = groupList.lastElementChild;
            Object.entries(values).forEach(([key, value]) => {
                const field = group.querySelector(`[data-group-field="${key}"]`);
                if (field) field.value = value ?? '';
            });
        });
    } else {
        addGroup();
    }
    updateBulkOccupancyTotal(form);
    setUnitBulkMode(form, form.querySelector('[data-bulk-mode-field]')?.value || 'single');
    form.addEventListener('submit', (event) => {
        const mode = form.querySelector('[data-bulk-mode-field]')?.value;
        if (mode === 'bulk' && !updateBulkOccupancyTotal(form)) {
            event.preventDefault();
            form.querySelector('[data-bulk-occupancy-count]')?.focus();
        }
        if (mode === 'mixed') {
            const invalidGroup = [...form.querySelectorAll('[data-unit-group]')].find((group) => !updateGroupOccupancyTotal(group));
            if (invalidGroup) {
                event.preventDefault();
                invalidGroup.querySelector('[data-group-field="count"]')?.focus();
            }
        }
    });
});

function reindexUnitGroups(form) {
    form.querySelectorAll('[data-unit-group]').forEach((group, index) => {
        group.querySelectorAll('[data-group-field]').forEach((field) => { field.name = `unit_groups[${index}][${field.dataset.groupField}]`; });
        group.querySelector('[data-unit-group-heading] strong')?.replaceChildren(`Group ${index + 1}`);
    });
}

function updateBulkOccupancyTotal(form) {
    const count = Number(form.querySelector('[data-bulk-count]')?.value || 0);
    const total = [...form.querySelectorAll('[data-bulk-occupancy-count]')].reduce((sum, field) => sum + Number(field.value || 0), 0);
    const message = form.querySelector('[data-bulk-occupancy-total]');
    if (message) {
        const difference = Math.abs(count - total);
        message.classList.remove('is-balanced', 'is-under', 'is-over');
        if (total === count) {
            message.textContent = `Occupancy counts match: ${total} of ${count} units.`;
            message.classList.add('is-balanced');
        } else if (total < count) {
            message.textContent = `${difference} unit${difference === 1 ? '' : 's'} still need an occupancy status (${total} of ${count} assigned).`;
            message.classList.add('is-under');
        } else {
            message.textContent = `Too many statuses by ${difference}: ${total} entered for ${count} units. Reduce the counts to ${count}.`;
            message.classList.add('is-over');
        }
    }

    return total === count;
}

function updateGroupOccupancyTotal(group) {
    const count = Number(group.querySelector('[data-group-field="count"]')?.value || 0);
    const total = ['vacant', 'occupied', 'reserved', 'maintenance'].reduce((sum, status) => sum + Number(group.querySelector(`[data-group-field="${status}"]`)?.value || 0), 0);
    const message = group.querySelector('[data-group-occupancy-total]');
    if (message) {
        const difference = Math.abs(count - total);
        message.classList.remove('is-balanced', 'is-under', 'is-over');
        if (total === count) {
            message.textContent = `Occupancy counts match: ${total} of ${count} units.`;
            message.classList.add('is-balanced');
        } else if (total < count) {
            message.textContent = `${difference} unit${difference === 1 ? '' : 's'} still need a status (${total} of ${count} assigned).`;
            message.classList.add('is-under');
        } else {
            message.textContent = `Too many statuses by ${difference}: ${total} entered for ${count} units. Reduce the counts to ${count}.`;
            message.classList.add('is-over');
        }
    }

    return total === count;
}

function resetUnitGroups(form) {
    const groupList = form.querySelector('[data-unit-group-list]');
    const groupTemplate = form.querySelector('[data-unit-group-template]');
    if (!groupList || !groupTemplate) return;
    groupList.replaceChildren(groupTemplate.content.cloneNode(true));
    reindexUnitGroups(form);
}

function updateMixedUnitGroups(form) {
    const groups = [...form.querySelectorAll('[data-unit-group]')];
    const labels = [];
    let total = 0;
    groups.forEach((group) => {
        const count = Math.max(1, Number(group.querySelector('[data-group-field="count"]').value || 1));
        const type = group.querySelector('[data-group-field="unit_type"]').value || 'Unit type';
        const prefixField = group.querySelector('[data-group-field="prefix"]');
        const prefix = prefixField.value || '';
        const sequence = group.querySelector('[data-group-field="sequence"]').value;
        prefixField.disabled = sequence === 'letters' || form.querySelector('[data-bulk-mode-field]')?.value !== 'mixed';
        const rawStart = group.querySelector('[data-group-field="start"]').value || (sequence === 'letters' ? 'A' : '1');
        const start = sequence === 'letters' ? letterLabelIndex(/^[A-Za-z]+$/.test(rawStart) ? rawStart : 'A') : Math.max(0, Number(rawStart || 0));
        const first = `${sequence === 'letters' ? '' : prefix}${sequence === 'letters' ? letterLabel(start) : start}`;
        const occupancy = ['vacant', 'occupied', 'reserved', 'maintenance'].reduce((sum, status) => sum + Number(group.querySelector(`[data-group-field="${status}"]`).value || 0), 0);
        updateGroupOccupancyTotal(group);
        labels.push(`${type}: ${first}${count > 1 ? ` + ${count - 1} more` : ''} · occupancy ${occupancy}/${count}`);
        total += count;
    });
    const preview = form.querySelector('[data-mixed-preview]');
    if (preview) preview.textContent = `Preview: ${labels.join(' · ')} · ${total} units total`;
}

document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

document.querySelectorAll('[data-reading-meter-picker]').forEach((meterPicker) => {
    const previousReadingField = meterPicker.form?.querySelector('[data-previous-reading]');
    if (!previousReadingField) return;

    const updatePreviousReading = () => {
        previousReadingField.value = meterPicker.selectedOptions[0]?.dataset.previousReading ?? '';
    };

    meterPicker.addEventListener('change', updatePreviousReading);
    updatePreviousReading();
});

const waterApp = document.querySelector('[data-water-app]');
if (waterApp) {
    const installButton = waterApp.querySelector('[data-install-water-app]');
    let installPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        if (installButton) installButton.hidden = false;
    });
    installButton?.addEventListener('click', async () => {
        if (!installPrompt) return;
        await installPrompt.prompt();
        installPrompt = null;
        installButton.hidden = true;
    });

    const readingRows = [...waterApp.querySelectorAll('[data-water-reading-row]')];
    const enteredCount = waterApp.querySelector('[data-water-entered]');
    const totalUsage = waterApp.querySelector('[data-water-total]');
    const updateWaterSummary = () => {
        let entered = 0;
        let usageTotal = 0;
        readingRows.forEach((row) => {
            const input = row.querySelector('[data-water-current]');
            const output = row.querySelector('[data-water-usage]');
            const current = Number(input?.value);
            const previous = Number(row.dataset.previous);
            const multiplier = Number(row.dataset.multiplier || 1);
            const rollover = Number(row.dataset.rollover);
            if (!input?.value || !Number.isFinite(current)) {
                if (output) output.textContent = '—';
                row.classList.remove('water-reading-invalid');
                return;
            }
            entered += 1;
            let difference = current - previous;
            if (difference < 0 && Number.isFinite(rollover) && rollover > previous) {
                difference = (rollover - previous) + current;
            }
            if (difference < 0) {
                row.classList.add('water-reading-invalid');
                if (output) output.textContent = 'Check value';
                return;
            }
            row.classList.remove('water-reading-invalid');
            const usage = difference * multiplier;
            usageTotal += usage;
            if (output) output.textContent = `${usage.toFixed(4)} m³`;
        });
        const expected = readingRows.length;
        if (enteredCount) enteredCount.textContent = `${entered} / ${expected}`;
        if (totalUsage) totalUsage.textContent = usageTotal.toFixed(4);
    };
    readingRows.forEach((row) => row.querySelector('[data-water-current]')?.addEventListener('input', updateWaterSummary));
    updateWaterSummary();
}

document.querySelectorAll('[data-confirm]').forEach((form) => {
    if (form.matches('form')) {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    } else {
        form.addEventListener('click', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    }
});

document.querySelectorAll('[data-payment-tenant]').forEach((tenantSelect) => {
    const invoiceSelect = tenantSelect.form?.querySelector('[data-payment-invoice]');
    if (!invoiceSelect) return;
    const filterInvoices = () => {
        [...invoiceSelect.options].forEach((option) => {
            if (!option.value) return;
            option.hidden = option.dataset.tenantId !== tenantSelect.value;
            option.disabled = option.hidden;
        });
        if (invoiceSelect.selectedOptions[0]?.disabled) invoiceSelect.value = '';
    };
    tenantSelect.addEventListener('change', filterInvoices);
    filterInvoices();
});

document.querySelectorAll('[data-dynamic-invoice-form]').forEach((form) => {
    const invoice = form.querySelector('[data-invoice-route]');
    const template = form.dataset.routeTemplate;
    const updateAction = () => {
        if (!invoice?.value || !template) return;
        form.action = template.replace(/\/0\/credit-notes$/, `/${invoice.value}/credit-notes`);
    };
    invoice?.addEventListener('change', updateAction);
    updateAction();
});

document.querySelectorAll('[data-template-channel]').forEach((channel) => {
    const form = channel.form;
    const picker = form?.querySelector('[data-template-picker]');
    const message = form?.querySelector('[data-template-message]');
    const syncTemplates = () => {
        if (!picker) return;
        for (const option of picker.options) {
            if (!option.value) continue;
            const matches = option.dataset.channel === channel.value;
            option.hidden = !matches;
            option.disabled = !matches;
            if (!matches && option.selected) picker.value = '';
        }
        if (message) message.required = !picker.value;
    };
    channel.addEventListener('change', syncTemplates);
    picker?.addEventListener('change', syncTemplates);
    syncTemplates();
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js').catch(() => {}));
}

const readingForm = document.querySelector('form[enctype="multipart/form-data"][action*="/workspace/readings"]');
const readingSyncStatus = document.getElementById('offline-reading-status');
const showReadingSyncStatus = (message, variant = 'flash-info') => {
    if (!readingSyncStatus) return;
    readingSyncStatus.hidden = false;
    readingSyncStatus.className = `flash ${variant}`;
    readingSyncStatus.textContent = message;
};
if (readingForm && readingSyncStatus && !navigator.onLine) {
    showReadingSyncStatus('Offline mode: readings are saved on this device and will sync when a connection returns.', 'flash-info');
}
if (readingForm && 'indexedDB' in window) {
    const request = indexedDB.open('utilityflow-offline', 2);
    request.onupgradeneeded = () => {
        if (!request.result.objectStoreNames.contains('reading-queue')) request.result.createObjectStore('reading-queue', { keyPath: 'id', autoIncrement: true });
        if (!request.result.objectStoreNames.contains('payment-queue')) request.result.createObjectStore('payment-queue', { keyPath: 'id', autoIncrement: true });
    };
    request.onsuccess = () => {
        const database = request.result;
        readingForm.addEventListener('submit', async (event) => {
            if (navigator.onLine) return;
            event.preventDefault();
            const file = readingForm.querySelector('input[type="file"]')?.files?.[0];
            const photo = file ? await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = () => resolve({ data: reader.result, name: file.name, type: file.type });
                reader.readAsDataURL(file);
            }) : null;
            const values = Object.fromEntries(new FormData(readingForm).entries());
            const transaction = database.transaction('reading-queue', 'readwrite');
            transaction.objectStore('reading-queue').add({ values, photo, savedAt: new Date().toISOString() });
            transaction.oncomplete = () => {
                readingForm.reset();
                readingForm.querySelectorAll('[name="submission_uuid"], [name$="[submission_uuid]"]').forEach((submissionId) => {
                    if ('randomUUID' in crypto) submissionId.value = crypto.randomUUID();
                });
                window.alert('Reading saved on this device. It will sync when you are back online.');
                showReadingSyncStatus('Reading saved on this device. It is waiting to sync.', 'flash-success');
                window.dispatchEvent(new Event('utilityflow:offline-reading-saved'));
            };
        });

        const syncQueue = () => {
            if (!navigator.onLine) return;
            const transaction = database.transaction('reading-queue', 'readonly');
            const getAll = transaction.objectStore('reading-queue').getAll();
            getAll.onsuccess = async () => {
                for (const queued of getAll.result) {
                    const body = new FormData();
                    Object.entries(queued.values).forEach(([key, value]) => body.append(key, value));
                    if (queued.photo?.data) body.append('photo', await (await fetch(queued.photo.data)).blob(), queued.photo.name);
                    try {
                        const response = await fetch(readingForm.action, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                        if (response.ok) {
                            database.transaction('reading-queue', 'readwrite').objectStore('reading-queue').delete(queued.id);
                            showReadingSyncStatus('Offline reading synchronized and submitted for review.', 'flash-success');
                            window.dispatchEvent(new CustomEvent('utilityflow:offline-reading-synced', { detail: queued.id }));
                        } else {
                            showReadingSyncStatus(response.status === 401 || response.status === 419
                                ? 'Sign in again to sync this saved reading. It remains stored on this device.'
                                : 'This saved reading needs review before it can sync. It remains stored on this device. Check the meter, date and value, then contact your workspace manager if the conflict continues.', 'flash-error');
                            break;
                        }
                    } catch {
                        showReadingSyncStatus('Connection unavailable. The reading remains saved on this device and will retry later.', 'flash-info');
                        break;
                    }
                }
            };
        };
        window.addEventListener('online', syncQueue);
        syncQueue();
    };
}

const paymentForm = document.querySelector('#payment-modal form[action*="/workspace/payments"]');
const paymentSyncStatus = document.getElementById('offline-payment-status');
const showPaymentSyncStatus = (message, variant = 'flash-info') => {
    if (!paymentSyncStatus) return;
    paymentSyncStatus.hidden = false;
    paymentSyncStatus.className = `flash ${variant}`;
    paymentSyncStatus.textContent = message;
};
if (paymentForm && 'indexedDB' in window) {
    const request = indexedDB.open('utilityflow-offline', 2);
    request.onupgradeneeded = () => {
        if (!request.result.objectStoreNames.contains('reading-queue')) request.result.createObjectStore('reading-queue', { keyPath: 'id', autoIncrement: true });
        if (!request.result.objectStoreNames.contains('payment-queue')) request.result.createObjectStore('payment-queue', { keyPath: 'id', autoIncrement: true });
    };
    request.onsuccess = () => {
        const database = request.result;
        paymentForm.addEventListener('submit', (event) => {
            if (navigator.onLine) return;
            event.preventDefault();
            const values = Object.fromEntries(new FormData(paymentForm).entries());
            const transaction = database.transaction('payment-queue', 'readwrite');
            transaction.objectStore('payment-queue').add({ values, savedAt: new Date().toISOString() });
            transaction.oncomplete = () => {
                paymentForm.reset();
                const id = paymentForm.querySelector('[name="submission_uuid"]');
                if (id && 'randomUUID' in crypto) id.value = crypto.randomUUID();
                const submit = paymentForm.querySelector('[type="submit"]');
                if (submit) { submit.disabled = false; submit.removeAttribute('aria-busy'); }
                showPaymentSyncStatus('Payment collection saved on this device. It will sync when you are back online.', 'flash-success');
            };
        });

        const syncPayments = () => {
            if (!navigator.onLine) return;
            const pending = database.transaction('payment-queue', 'readonly').objectStore('payment-queue').getAll();
            pending.onsuccess = async () => {
                for (const queued of pending.result) {
                    try {
                        const body = new FormData();
                        Object.entries(queued.values).forEach(([key, value]) => body.append(key, value));
                        const response = await fetch(paymentForm.action, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                        if (!response.ok) {
                            showPaymentSyncStatus(response.status === 401 || response.status === 419 ? 'Sign in again to sync this saved collection. It remains on this device.' : 'This saved collection needs review before syncing. It remains on this device.', 'flash-error');
                            break;
                        }
                        database.transaction('payment-queue', 'readwrite').objectStore('payment-queue').delete(queued.id);
                        showPaymentSyncStatus('Offline payment collection synchronized to the workspace.', 'flash-success');
                    } catch {
                        showPaymentSyncStatus('Connection unavailable. The collection remains saved and will retry later.', 'flash-info');
                        break;
                    }
                }
            };
        };
        window.addEventListener('online', syncPayments);
        syncPayments();
    };
}

document.querySelectorAll('[data-tenant-invoice-picker]').forEach((picker) => {
    const amount = picker.form?.querySelector('[data-tenant-payment-amount]');
    if (!amount) return;
    const updateMax = () => {
        const balance = picker.selectedOptions[0]?.dataset.balance ?? '';
        amount.max = balance;
        amount.placeholder = balance ? `Up to KES ${balance}` : '';
    };
    picker.addEventListener('change', updateMax);
    updateMax();
});

document.querySelectorAll('dialog[data-open-on-error="true"]').forEach((dialog) => dialog.showModal());

const waterWorkflowTabs = Array.from(document.querySelectorAll('[data-water-tab]'));
if (waterWorkflowTabs.length) {
    waterWorkflowTabs.forEach((tab, index) => {
        const panel = document.getElementById(tab.getAttribute('aria-controls'));
        tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
        if (panel) panel.hidden = tab.getAttribute('aria-selected') !== 'true';

        tab.addEventListener('click', () => {
            waterWorkflowTabs.forEach((item) => {
                const selected = item === tab;
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
                item.tabIndex = selected ? 0 : -1;
                const panel = document.getElementById(item.getAttribute('aria-controls'));
                if (panel) panel.hidden = !selected;
            });
        });

        tab.addEventListener('keydown', (event) => {
            if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const nextIndex = event.key === 'Home'
                ? 0
                : event.key === 'End'
                    ? waterWorkflowTabs.length - 1
                    : (index + (event.key === 'ArrowRight' ? 1 : -1) + waterWorkflowTabs.length) % waterWorkflowTabs.length;
            waterWorkflowTabs[nextIndex].focus();
            waterWorkflowTabs[nextIndex].click();
        });
    });
}
