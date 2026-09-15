// ---------- Mobile sidebar toggle ----------
function initSidebarToggle() {
    const toggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!toggle || !sidebar || !backdrop || sidebar.dataset.initialized === 'true') return;
    sidebar.dataset.initialized = 'true';

    const closeSidebar = () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
    };

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('show');
    });
    backdrop.addEventListener('click', closeSidebar);
}

// ---------- Real-time survey search & filter ----------
function initLiveSurveySearch() {
    const form = document.getElementById('surveyFilterForm');
    const searchInput = document.getElementById('surveySearchInput');
    const statusSelect = document.getElementById('surveyStatusSelect');
    const results = document.getElementById('surveyResults');
    if (!form || !searchInput || !statusSelect || !results) return;

    let debounceTimer = null;
    let activeRequest = null;

    function fetchResults(page) {
        const params = new URLSearchParams();
        const q = searchInput.value.trim();
        const status = statusSelect.value;
        if (q !== '') params.set('q', q);
        if (status !== 'all') params.set('status', status);
        params.set('page', String(page || 1));

        if (activeRequest) activeRequest.abort();
        const controller = new AbortController();
        activeRequest = controller;

        results.classList.add('is-loading');

        fetch('surveys.php?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        })
            .then((response) => response.text())
            .then((html) => {
                results.innerHTML = html;
                results.classList.remove('is-loading');
                const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.replaceState({}, '', newUrl);
            })
            .catch((err) => {
                if (err.name !== 'AbortError') results.classList.remove('is-loading');
            });
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchResults(1), 350);
    });

    statusSelect.addEventListener('change', () => {
        fetchResults(1);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        clearTimeout(debounceTimer);
        fetchResults(1);
    });

    results.addEventListener('click', (e) => {
        const link = e.target.closest('.pagination a[data-page]');
        if (!link || link.getAttribute('aria-disabled') === 'true') return;
        e.preventDefault();
        fetchResults(parseInt(link.getAttribute('data-page'), 10) || 1);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initLiveSurveySearch();
});