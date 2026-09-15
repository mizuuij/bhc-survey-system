function openAddUserModal() {
    document.getElementById("addUserOverlay").classList.add("open");
    document.getElementById("userFullName").focus();
}

function closeAddUserModal() {
    document.getElementById("addUserOverlay").classList.remove("open");
}

function openEditUserModal(staff) {
    document.getElementById("editStaffId").value = staff.id;
    document.getElementById("editUserFullName").value = staff.full_name;
    document.getElementById("editRole").value = staff.role;
    document.getElementById("editUserOverlay").classList.add("open");
    document.getElementById("editUserFullName").focus();
}

function closeEditUserModal() {
    document.getElementById("editUserOverlay").classList.remove("open");
}

function openChangePasswordModal(staff, preserveValues = false) {
    document.getElementById("cpStaffId").value = staff.id;
    if (!preserveValues) {
        document.getElementById("cpCurrentPassword").value = "";
        document.getElementById("cpNewPassword").value = "";
        document.getElementById("cpConfirmPassword").value = "";
    }
    document.getElementById("cpCurrentPasswordGroup").hidden = !staff.isOwn;
    document.getElementById("cpCurrentPassword").required = staff.isOwn;
    document.getElementById("changePasswordDesc").textContent = staff.isOwn
        ? "Update your own administrator password."
        : "Set a new password for " + staff.username + ".";
    document.getElementById("changePasswordOverlay").classList.add("open");

    const current = document.getElementById("cpCurrentPassword");
    const newPassword = document.getElementById("cpNewPassword");
    const confirmPassword = document.getElementById("cpConfirmPassword");
    const submitBtn = document.getElementById("cpSubmitBtn");
    if (submitBtn) submitBtn.disabled = true;

    evaluateCpPasswordStrength();
    evaluateCpPasswordForm();
    (staff.isOwn ? current : newPassword).focus();
}

function evaluateCpPasswordStrength() {
    const input = document.getElementById('cpNewPassword');
    const bar = document.getElementById('cpStrengthBar');
    if (!input || !bar) return { length: false, upper: false, number: false, special: false };

    const value = input.value;
    const rules = {
        length: value.length >= 8,
        upper: /[A-Z]/.test(value),
        number: /\d/.test(value),
        special: /[^A-Za-z0-9]/.test(value),
    };
    const score = Object.values(rules).filter(Boolean).length;

    bar.className = 'cp-strength-bar' + (score > 0 ? ' s' + score : '');

    document.querySelectorAll('#cpRequirements li').forEach((li) => {
        const rule = li.getAttribute('data-rule');
        li.classList.toggle('met', !!rules[rule]);
    });

    return rules;
}

function evaluateCpPasswordForm() {
    const newPassword = document.getElementById('cpNewPassword');
    const confirmPassword = document.getElementById('cpConfirmPassword');
    const currentPassword = document.getElementById('cpCurrentPassword');
    const currentGroup = document.getElementById('cpCurrentPasswordGroup');
    const matchHint = document.getElementById('cpMatchHint');
    const matchHintText = document.getElementById('cpMatchHintText');
    const submitBtn = document.getElementById('cpSubmitBtn');
    if (!newPassword || !confirmPassword || !submitBtn) return;

    const rules = evaluateCpPasswordStrength();
    const allRulesMet = Object.values(rules).every(Boolean);
    const passwordsMatch = confirmPassword.value.length > 0 && newPassword.value === confirmPassword.value;
    const currentOk = !currentGroup || currentGroup.hidden || (currentPassword && currentPassword.value.length > 0);

    if (confirmPassword.value.length === 0) {
        matchHint?.classList.remove('show', 'match', 'mismatch');
        confirmPassword.classList.remove('field-invalid');
    } else if (passwordsMatch) {
        matchHint?.classList.add('show', 'match');
        matchHint?.classList.remove('mismatch');
        if (matchHintText) matchHintText.textContent = 'Passwords match.';
        confirmPassword.classList.remove('field-invalid');
    } else {
        matchHint?.classList.add('show', 'mismatch');
        matchHint?.classList.remove('match');
        if (matchHintText) matchHintText.textContent = 'Passwords do not match.';
        confirmPassword.classList.add('field-invalid');
    }

    submitBtn.disabled = !(allRulesMet && passwordsMatch && currentOk);
}

function closeChangePasswordModal() {
    document.getElementById("changePasswordOverlay").classList.remove("open");
}

document.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal-overlay")) {
        e.target.classList.remove("open");
    }
});

document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        document.querySelectorAll(".modal-overlay.open").forEach((overlay) => overlay.classList.remove("open"));
    }
});

function initPasswordToggles() {
    document.querySelectorAll('.toggle-password').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            const eyeIcon = btn.querySelector('.icon-eye');
            const eyeOffIcon = btn.querySelector('.icon-eye-off');
            if (eyeIcon && eyeOffIcon) {
                eyeIcon.style.display = isHidden ? 'none' : '';
                eyeOffIcon.style.display = isHidden ? '' : 'none';
            }

            btn.setAttribute('aria-pressed', String(isHidden));
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
}

document.addEventListener("DOMContentLoaded", () => {
    initPasswordToggles();
    ['cpCurrentPassword', 'cpNewPassword', 'cpConfirmPassword'].forEach((id) => {
        const input = document.getElementById(id);
        if (input) input.addEventListener('input', evaluateCpPasswordForm);
    });
    evaluateCpPasswordForm();
    if (new URLSearchParams(window.location.search).get('add') === '1') openAddUserModal();
});

/* ---------- Real-time staff account search & filter ---------- */
function initLiveUserSearch() {
    const form = document.getElementById("userFilterForm");
    const searchInput = document.getElementById("userSearchInput");
    const statusSelect = document.getElementById("userStatusSelect");
    const results = document.getElementById("userResults");
    if (!form || !searchInput || !statusSelect || !results) return;

    let debounceTimer = null;
    let activeRequest = null;

    function fetchResults(page) {
        const params = new URLSearchParams();
        const q = searchInput.value.trim();
        const status = statusSelect.value;
        if (q !== "") params.set("q", q);
        if (status !== "all") params.set("status", status);
        params.set("page", String(page || 1));

        if (activeRequest) activeRequest.abort();
        const controller = new AbortController();
        activeRequest = controller;

        results.classList.add("is-loading");

        fetch("users.php?" + params.toString(), {
            headers: { "X-Requested-With": "XMLHttpRequest" },
            signal: controller.signal,
        })
            .then((response) => response.text())
            .then((html) => {
                results.innerHTML = html;
                results.classList.remove("is-loading");
                const newUrl = window.location.pathname + (params.toString() ? "?" + params.toString() : "");
                window.history.replaceState({}, "", newUrl);
            })
            .catch((err) => {
                if (err.name !== "AbortError") results.classList.remove("is-loading");
            });
    }

    searchInput.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchResults(1), 350);
    });

    statusSelect.addEventListener("change", () => {
        fetchResults(1);
    });

    form.addEventListener("submit", (e) => {
        e.preventDefault();
        clearTimeout(debounceTimer);
        fetchResults(1);
    });

    results.addEventListener("click", (e) => {
        const link = e.target.closest(".management-pagination a[data-page]");
        if (!link) return;
        e.preventDefault();
        fetchResults(parseInt(link.getAttribute("data-page"), 10) || 1);
    });
}

document.addEventListener("DOMContentLoaded", () => {
    initLiveUserSearch();
});