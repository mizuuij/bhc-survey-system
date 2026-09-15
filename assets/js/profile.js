/* Prevent the browser from restoring its own scroll position on this reload.
   The server already opens the correct accordion card via the `open`
   attribute (see resident/profile.php's $activeSection), so there is no
   need to also scroll the page — doing that on top of the browser's own
   restoration is what caused the visible jump after a save. */
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

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

/* ---------- Field validation (all accordion forms) ---------- */
function fieldIsValid(el) {
    const value = el.value.trim();
    if (el.hasAttribute('required') && value === '') return false;
    if (value === '') return true;

    if (el.type === 'number') {
        const num = Number(value);
        const min = el.hasAttribute('min') ? Number(el.getAttribute('min')) : -Infinity;
        const max = el.hasAttribute('max') ? Number(el.getAttribute('max')) : Infinity;
        if (Number.isNaN(num) || num < min || num > max) return false;
    }
    if (el.hasAttribute('pattern')) {
        const re = new RegExp('^(?:' + el.getAttribute('pattern') + ')$');
        if (!re.test(value)) return false;
    }
    return true;
}

function validateForm(form) {
    let valid = true;
    let firstInvalid = null;

    form.querySelectorAll('input, textarea, select').forEach((el) => {
        if (el.type === 'file' || el.type === 'checkbox') return;
        const field = el.closest('.field');
        if (!field) return;

        const ok = fieldIsValid(el);
        field.classList.toggle('has-error', !ok);
        if (!ok && !firstInvalid) firstInvalid = el;
        if (!ok) valid = false;
    });

    if (!valid && firstInvalid) {
        firstInvalid.closest('details')?.setAttribute('open', '');
        firstInvalid.focus();
        firstInvalid.closest('.repeat-row, .field')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return valid;
}

function initFormValidation() {
    document.querySelectorAll('form[data-validate]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });

        form.addEventListener('input', (e) => {
            const field = e.target.closest('.field');
            if (field && field.classList.contains('has-error') && fieldIsValid(e.target)) {
                field.classList.remove('has-error');
            }
        });
    });

    // Spouse card: name is only required once another spouse field has a value.
    const spouseForm = document.querySelector('form[action*="update_spouse.php"]');
    if (spouseForm) {
        spouseForm.addEventListener('submit', (e) => {
            const name = spouseForm.querySelector('#spouse_name');
            const occupation = spouseForm.querySelector('#spouse_occupation');
            const employer = spouseForm.querySelector('#spouse_employer');
            if (name.value.trim() === '' && (occupation.value.trim() !== '' || employer.value.trim() !== '')) {
                name.closest('.field').classList.add('has-error');
                e.preventDefault();
                name.focus();
            }
        });
    }
}

/* ---------- Children: dynamic add / remove rows ---------- */
function createChildRow() {
    const wrap = document.createElement('div');
    wrap.className = 'repeat-row';
    wrap.innerHTML = [
        '<div class="repeat-row-head">',
        '<span class="repeat-row-label">Child</span>',
        '<button type="button" class="repeat-row-remove" aria-label="Remove this child" data-remove-row>',
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        '</button></div>',
        '<div class="form-row child-row">',
        '<div class="field"><label>Child\'s Name</label>',
        '<input type="text" name="child_name[]" maxlength="120" required>',
        '<p class="field-error">Child\'s name is required.</p></div>',
        '<div class="field"><label>Sex</label>',
        '<select name="child_sex[]" required>',
        '<option value="" disabled selected>Select sex</option>',
        '<option value="male">Male</option>',
        '<option value="female">Female</option>',
        '</select>',
        '<p class="field-error">Select a sex.</p></div>',
        '<div class="field"><label>Age</label>',
        '<input type="number" name="child_age[]" min="0" max="120" required>',
        '<p class="field-error">Enter a valid age.</p></div>',
        '</div>',
    ].join('');
    return wrap;
}

function initChildrenRows() {
    const container = document.getElementById('childrenRows');
    const addBtn = document.getElementById('addChildRow');
    const emptyMsg = document.getElementById('childrenEmpty');
    if (!container || !addBtn) return;

    const refreshEmpty = () => {
        if (emptyMsg) emptyMsg.hidden = container.children.length > 0;
    };

    // Don't allow a new (blank) child row while an earlier row is still empty —
    // it just gives the resident more empty rows to fill in, in the wrong order.
    const findFirstIncompleteRow = () => {
        const rows = Array.from(container.querySelectorAll('.repeat-row'));
        return rows.find((row) => {
            const nameEl = row.querySelector('input[name="child_name[]"]');
            const sexEl = row.querySelector('select[name="child_sex[]"]');
            const ageEl = row.querySelector('input[name="child_age[]"]');
            const name = (nameEl?.value ?? '').trim();
            const sex = (sexEl?.value ?? '').trim();
            const age = (ageEl?.value ?? '').trim();
            return name === '' || sex === '' || age === '';
        }) || null;
    };

    addBtn.addEventListener('click', () => {
        const incomplete = findFirstIncompleteRow();
        if (incomplete) {
            incomplete.querySelectorAll('.field').forEach((field) => {
                const el = field.querySelector('input, select');
                if (el && el.value.trim() === '') field.classList.add('has-error');
            });
            incomplete.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const firstEmptyInput = incomplete.querySelector('input[name="child_name[]"], select[name="child_sex[]"], input[name="child_age[]"]');
            firstEmptyInput?.focus();
            return;
        }

        const row = createChildRow();
        container.appendChild(row);
        refreshEmpty();
        row.querySelector('input')?.focus();
    });

    container.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-row]');
        if (!btn) return;
        btn.closest('.repeat-row')?.remove();
        refreshEmpty();
    });

    // Clear the error highlight as soon as the earlier row is filled in.
    container.addEventListener('input', (e) => {
        const field = e.target.closest('.field');
        if (field && field.classList.contains('has-error') && e.target.value.trim() !== '') {
            field.classList.remove('has-error');
        }
    });

    refreshEmpty();
}

/* ---------- Profile photo upload ---------- */
function initPhotoUpload() {
    const input = document.getElementById('photoInput');
    const chooseBtn = document.getElementById('choosePhotoBtn');
    const preview = document.getElementById('photoPreview');
    const fileName = document.getElementById('photoFileName');
    const errorEl = document.getElementById('photoClientError');
    if (!input || !chooseBtn || !preview) return;

    const allowed = ['image/jpeg', 'image/png', 'image/webp'];

    chooseBtn.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        if (errorEl) errorEl.style.display = 'none';
        const file = input.files[0];
        if (!file) return;

        if (!allowed.includes(file.type)) {
            if (errorEl) { errorEl.textContent = 'Please choose a JPG, PNG, or WEBP image.'; errorEl.style.display = 'block'; }
            input.value = '';
            return;
        }
        if (file.size > 3 * 1024 * 1024) {
            if (errorEl) { errorEl.textContent = 'The image is too large. Maximum size is 3MB.'; errorEl.style.display = 'block'; }
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Profile photo preview">';
        };
        reader.readAsDataURL(file);

        if (fileName) {
            fileName.textContent = file.name;
            fileName.classList.add('show');
        }
    });
}

/* ---------- Character reference signature previews ---------- */
function initSignaturePreviews() {
    document.querySelectorAll('[data-choose-signature]').forEach((btn) => {
        const idx = btn.getAttribute('data-choose-signature');
        const input = document.getElementById('signature_' + idx);
        if (input) btn.addEventListener('click', () => input.click());
    });

    document.querySelectorAll('[data-signature-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const idx = input.getAttribute('data-signature-input');
            const preview = document.getElementById('signaturePreview' + idx);
            const fileNameEl = document.getElementById('signatureFileName' + idx);
            const file = input.files[0];
            if (!file || !preview) return;

            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type) || file.size > 2 * 1024 * 1024) {
                input.value = '';
                if (fileNameEl) {
                    fileNameEl.textContent = !allowed.includes(file.type)
                        ? 'Please choose a JPG, PNG, or WEBP image.'
                        : 'The image is too large. Maximum size is 2MB.';
                    fileNameEl.classList.add('show');
                }
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                preview.classList.remove('is-empty');
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Signature preview">';
            };
            reader.readAsDataURL(file);

            // A fresh file replaces the current one, so "remove" no longer applies.
            const row = input.closest('.repeat-row');
            const removeCheckbox = row?.querySelector('input[name="remove_signature_' + idx + '"]');
            if (removeCheckbox) removeCheckbox.checked = false;

            if (fileNameEl) {
                fileNameEl.textContent = file.name;
                fileNameEl.classList.add('show');
            }
        });
    });
}

/* ---------- Accordion: smooth open/close, only one card open at a time ---------- */
function initAccordionExclusive() {
    const cards = document.querySelectorAll('.accordion-card');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const duration = reduceMotion ? 0 : 240;
    const easing = 'cubic-bezier(.4, 0, .2, 1)';

    function animateClose(card) {
        const summary = card.querySelector(':scope > summary');
        if (!summary) { card.open = false; return; }
        card.classList.add('is-animating');
        const startHeight = card.offsetHeight;
        const endHeight = summary.offsetHeight;
        card.style.overflow = 'hidden';
        card.style.height = startHeight + 'px';
        const anim = card.animate(
            { height: [startHeight + 'px', endHeight + 'px'] },
            { duration, easing }
        );
        anim.onfinish = () => {
            card.open = false;
            card.style.height = '';
            card.style.overflow = '';
            card.classList.remove('is-animating');
        };
    }

    function animateOpen(card) {
        const summary = card.querySelector(':scope > summary');
        const body = card.querySelector(':scope > .accordion-body');
        if (!summary || !body) { card.open = true; return; }
        card.classList.add('is-animating');
        const startHeight = card.offsetHeight; // closed height (summary only)
        card.style.overflow = 'hidden';
        card.open = true;
        const endHeight = summary.offsetHeight + body.offsetHeight;
        card.style.height = startHeight + 'px';
        const anim = card.animate(
            { height: [startHeight + 'px', endHeight + 'px'] },
            { duration, easing }
        );
        anim.onfinish = () => {
            card.style.height = '';
            card.style.overflow = '';
            card.classList.remove('is-animating');
        };
    }

    cards.forEach((card) => {
        const summary = card.querySelector(':scope > summary');
        if (!summary) return;

        summary.addEventListener('click', (e) => {
            e.preventDefault();
            if (card.classList.contains('is-animating')) return;

            if (card.open) {
                animateClose(card);
            } else {
                cards.forEach((other) => {
                    if (other !== card && other.open && !other.classList.contains('is-animating')) {
                        animateClose(other);
                    }
                });
                animateOpen(card);
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initFormValidation();
    initChildrenRows();
    initPhotoUpload();
    initSignaturePreviews();
    initAccordionExclusive();
});