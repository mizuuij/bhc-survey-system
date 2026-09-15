function initSidebar() {
    const toggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!toggle || !sidebar || !backdrop || sidebar.dataset.initialized === 'true') return;
    sidebar.dataset.initialized = 'true';

    const setSidebarOpen = (isOpen) => {
        sidebar.classList.toggle('open', isOpen);
        backdrop.classList.toggle('show', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };
    const closeSidebar = () => setSidebarOpen(false);
    toggle.addEventListener('click', () => {
        setSidebarOpen(!sidebar.classList.contains('open'));
    });
    backdrop.addEventListener('click', closeSidebar);
    toggle.setAttribute('aria-expanded', 'false');
    window.addEventListener('resize', () => {
        if (window.innerWidth > 780) closeSidebar();
    });

    const card = sidebar.querySelector('.id-card');
    const profileHref = card?.dataset.navigateHref;
    if (card && profileHref && card.dataset.initialized !== 'true') {
        card.dataset.initialized = 'true';
        card.setAttribute('data-tooltip', 'Profile');
        card.setAttribute('role', 'link');
        card.setAttribute('tabindex', '0');
        const openProfile = () => {
            if (card.dataset.navigating === 'true') return;
            card.dataset.navigating = 'true';
            window.location.href = profileHref;
        };
        card.addEventListener('click', openProfile);
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openProfile();
            }
        });
    }

    sidebar.querySelectorAll('.nav-parent').forEach((button) => {
        if (button.dataset.initialized === 'true') return;
        button.dataset.initialized = 'true';
        button.addEventListener('click', () => {
            const item = button.closest('.nav-group-item');
            if (!item) return;
            const isOpen = item.classList.toggle('open');
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    sidebar.querySelectorAll('.nav-link[href]').forEach((link) => {
        if (link.dataset.sidebarNavigationInitialized === 'true') return;
        link.dataset.sidebarNavigationInitialized = 'true';
        link.addEventListener('click', closeSidebar);
    });

}

document.addEventListener('DOMContentLoaded', initSidebar);

function initActivityFilters() {
    const form = document.querySelector('[data-activity-filter]');
    const results = document.querySelector('[data-activity-results]');
    if (!form || !results || form.dataset.initialized === 'true') return;
    form.dataset.initialized = 'true';

    let controller;

    const loadResults = async (url) => {
        controller?.abort();
        controller = new AbortController();
        results.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                signal: controller.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('Unable to load activities.');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = page.querySelector('[data-activity-results]');
            if (!replacement) throw new Error('Activities were not found.');
            results.innerHTML = replacement.innerHTML;
            history.replaceState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') console.error(error);
        } finally {
            results.removeAttribute('aria-busy');
        }
    };

    const loadFromForm = () => {
        const params = new URLSearchParams(new FormData(form));
        params.delete('activity_page');
        loadResults(`${location.pathname}?${params.toString()}`);
    };

    form.querySelectorAll('select, input[type="date"]').forEach((control) => {
        control.addEventListener('change', loadFromForm);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        loadFromForm();
    });
    results.addEventListener('click', (event) => {
        const link = event.target.closest('.management-pagination a[href]');
        if (!link) return;
        event.preventDefault();
        loadResults(link.getAttribute('href'));
    });
}

document.addEventListener('DOMContentLoaded', initActivityFilters);
