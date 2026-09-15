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
function initOptionHighlight() {
    document.querySelectorAll('.option-item').forEach((item) => {
        const input = item.querySelector('input[type="radio"]');
        if (!input) return;
        const sync = () => {
            const name = input.name;
            document.querySelectorAll(`input[name="${name}"]`).forEach((sibling) => {
                sibling.closest('.option-item')?.classList.toggle('checked', sibling.checked);
            });
        };
        input.addEventListener('change', sync);
        sync();
    });
}
function initRatingWidgets() {
    // Each rating question renders its own hidden input immediately before
    // its .rating-row, so widgets are matched by DOM position rather than a
    // single shared #service_rating id — this lets a survey have more than
    // one rating-type question.
    document.querySelectorAll('.rating-row').forEach((row) => {
        const stars = Array.from(row.querySelectorAll('.rating-star'));
        const caption = row.parentElement.querySelector('.rating-caption');
        const ratingInput = row.previousElementSibling;
        const labels = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
        const paint = (value) => {
            stars.forEach((star, i) => star.classList.toggle('active', i < value));
            if (caption && value > 0) {
                caption.textContent = `${value} / 5 — ${labels[value - 1]}`;
            }
        };
        stars.forEach((star, i) => {
            star.addEventListener('click', () => {
                row.dataset.value = i + 1;
                if (ratingInput) ratingInput.value = i + 1;
                paint(i + 1);
            });
            star.addEventListener('mouseenter', () => paint(i + 1));
        });
        row.addEventListener('mouseleave', () => paint(Number(row.dataset.value) || 0));
    });
}
function initRatingRequiredCheck() {
    const form = document.getElementById('surveyForm');
    if (!form) return;
    form.addEventListener('submit', (e) => {
        const missing = Array.from(form.querySelectorAll('[data-rating-input]'))
            .filter((input) => input.hasAttribute('data-required') && !input.value);
        if (missing.length > 0) {
            e.preventDefault();
            alert('Please rate every required question before submitting.');
        }
    });
}
document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initOptionHighlight();
    initRatingWidgets();
    initRatingRequiredCheck();
});