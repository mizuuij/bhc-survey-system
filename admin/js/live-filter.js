document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-live-filter]');
    const results = document.querySelector('[data-live-results]');
    const search = form?.querySelector('[data-live-search]');
    if (!form || !results) return;

    let timer;
    let controller;

    const loadResults = async (url) => {
        controller?.abort();
        controller = new AbortController();
        results.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, { signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Unable to load results.');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = page.querySelector('[data-live-results]');
            if (!replacement) throw new Error('Results were not found.');
            results.innerHTML = replacement.innerHTML;
            history.replaceState({}, '', url);
            results.dispatchEvent(new CustomEvent('report-results:updated', { bubbles: true }));
        } catch (error) {
            if (error.name !== 'AbortError') console.error(error);
        } finally {
            results.removeAttribute('aria-busy');
        }
    };

    const loadFromForm = () => {
        const params = new URLSearchParams(new FormData(form));
        params.delete('page');
        loadResults(`${location.pathname}?${params.toString()}`);
    };

    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(loadFromForm, 250);
        });
    }
    form.querySelectorAll('select').forEach((select) => select.addEventListener('change', loadFromForm));
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(timer);
        loadFromForm();
    });

    // Pagination links (Previous/Next) also go through AJAX now, so the
    // bulk-export selection below never gets wiped out by a full page reload.
    results.addEventListener('click', (event) => {
        const link = event.target.closest('.report-pagination a[href]');
        if (!link) return;
        event.preventDefault();
        clearTimeout(timer);
        loadResults(link.getAttribute('href'));
    });
});