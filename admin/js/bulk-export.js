document.addEventListener('DOMContentLoaded', () => {
    const getConfig = (scope) => scope.querySelector('[data-survey-select]')
        ? { key: 'surveyReportBulkExportSelection', selector: '[data-survey-select]', parameter: 'survey_id[]', endpoint: '../../process/export_report.php', noun: 'survey' }
        : { key: 'memberReportBulkExportSelection', selector: '[data-member-select]', parameter: 'resident_id[]', endpoint: '../../process/export_member_report.php', noun: 'member' };

    const loadSelection = (key) => {
        try {
            const raw = sessionStorage.getItem(key);
            return raw ? new Set(JSON.parse(raw)) : new Set();
        } catch (error) {
            return new Set();
        }
    };
    const saveSelection = (key, selection) => {
        try {
            sessionStorage.setItem(key, JSON.stringify([...selection]));
        } catch (error) {
            // Selection still works for the current page when storage is unavailable.
        }
    };

    const scopes = new Map();
    const getState = (scope) => {
        let state = scopes.get(scope);
        if (!state) {
            const config = getConfig(scope);
            state = { config, selection: loadSelection(config.key) };
            scopes.set(scope, state);
        }
        return state;
    };

    const syncForm = (scope) => {
        const state = getState(scope);

        scope.querySelectorAll(state.config.selector).forEach((checkbox) => {
            checkbox.checked = state.selection.has(checkbox.value);
        });

        const count = state.selection.size;
        scope.querySelectorAll('[data-bulk-export-submit]').forEach((button) => { button.disabled = count === 0; });
        const countLabel = scope.querySelector('[data-selection-count]');
        if (countLabel) countLabel.textContent = `${count} ${state.config.noun}${count === 1 ? '' : 's'} selected`;
    };

    document.addEventListener('change', (event) => {
        const scope = event.target.closest('[data-bulk-export]');
        if (!scope) return;
        const state = getState(scope);

        if (event.target.matches('[data-select-all]')) {
            scope.querySelectorAll(state.config.selector).forEach((checkbox) => {
                checkbox.checked = event.target.checked;
                if (checkbox.checked) state.selection.add(checkbox.value); else state.selection.delete(checkbox.value);
            });
        } else if (event.target.matches(state.config.selector)) {
            if (event.target.checked) state.selection.add(event.target.value); else state.selection.delete(event.target.value);
        } else {
            return;
        }

        saveSelection(state.config.key, state.selection);
        syncForm(scope);
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-bulk-export-submit]');
        if (!button) return;
        const scope = button.closest('[data-bulk-export]');
        if (!scope) return;
        const state = getState(scope);
        syncForm(scope);
        if (button.disabled || state.selection.size === 0) return;

        const params = new URLSearchParams();
        params.set('format', button.getAttribute('data-bulk-export-submit'));
        state.selection.forEach((id) => params.append(state.config.parameter, id));
        window.location.href = state.config.endpoint + '?' + params.toString();
    });

    // Re-apply the persisted selection and refresh counts/labels whenever
    // live-filter.js swaps in a new page of results (search, filter, or
    // pagination) — this is what actually keeps the checkboxes "remembered".
    document.addEventListener('report-results:updated', () => {
        document.querySelectorAll('[data-bulk-export]').forEach(syncForm);
    });

    document.querySelectorAll('[data-bulk-export]').forEach(syncForm);
});