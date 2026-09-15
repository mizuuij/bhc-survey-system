/* Add / Edit Member modals — wired to the real backend (create_member.php / update_member.php) */

function openAddMemberModal() {
    document.getElementById("addMemberOverlay").classList.add("open");
    document.getElementById("lastName").focus();
}

function closeAddMemberModal() {
    document.getElementById("addMemberOverlay").classList.remove("open");
}

function initAddMemberForm() {
    const form = document.getElementById('addMemberForm');
    const birthday = document.getElementById('birthday');
    const age = document.getElementById('addAgeDisplay');
    const addChild = document.getElementById('addMemberChildRow');
    const rows = document.getElementById('addChildrenRows');
    const empty = document.getElementById('addChildrenEmpty');
    if (!form) return;

    const updateAge = () => {
        if (!birthday || !age || !birthday.value) { if (age) age.value = '—'; return; }
        const birthDate = new Date(birthday.value + 'T00:00:00');
        const today = new Date();
        let years = today.getFullYear() - birthDate.getFullYear();
        if (today.getMonth() < birthDate.getMonth() || (today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) years--;
        age.value = years >= 0 ? years : '—';
    };
    birthday?.addEventListener('change', updateAge);

    // Head of Household is just the name being registered — derive it instead
    // of asking the admin to type it twice.
    const headName = document.getElementById('headName');
    const lastName = document.getElementById('lastName');
    const firstName = document.getElementById('firstName');
    const middleName = document.getElementById('middleName');
    const extensionName = document.getElementById('extensionName');
    const updateHeadName = () => {
        if (!headName) return;
        const parts = [firstName?.value.trim(), middleName?.value.trim(), lastName?.value.trim()].filter(Boolean);
        let derived = parts.join(' ');
        if (extensionName?.value.trim()) derived += (derived ? ' ' : '') + extensionName.value.trim();
        headName.value = derived;
    };
    [lastName, firstName, middleName, extensionName].forEach((el) => el?.addEventListener('input', updateHeadName));

    const refreshChildren = () => { if (empty) empty.hidden = rows.children.length > 0; };
    addChild?.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'repeat-row';
        row.innerHTML = '<div class="repeat-row-head"><span class="repeat-row-label">Child</span><button type="button" class="repeat-row-remove" aria-label="Remove this child">&times;</button></div><div class="form-row-3"><div class="form-group"><label>Child\'s Name</label><input name="child_name[]" maxlength="120" required></div><div class="form-group"><label>Sex</label><select name="child_sex[]" required><option value="" disabled selected>Select sex</option><option value="male">Male</option><option value="female">Female</option></select></div><div class="form-group"><label>Age</label><input name="child_age[]" type="number" min="0" max="120" required></div></div>';
        rows.appendChild(row);
        refreshChildren();
        row.querySelector('input')?.focus();
    });
    rows?.addEventListener('click', (event) => {
        const remove = event.target.closest('.repeat-row-remove');
        if (remove) { remove.closest('.repeat-row').remove(); refreshChildren(); }
    });
    refreshChildren();
}

function openEditMemberModal(member) {
    document.getElementById("editResidentId").value = member.id;
    document.getElementById("editHouseholdNo").value = member.household_number;
    document.getElementById("editHeadName").value = member.head_name;
    document.getElementById("editContactNo").value = member.contact_number;
    document.getElementById("editAddress").value = member.address;
    document.getElementById("editMemberOverlay").classList.add("open");
    document.getElementById("editHeadName").focus();
}

function closeEditMemberModal() {
    document.getElementById("editMemberOverlay").classList.remove("open");
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

/* ---------- Real-time member search & filter ---------- */
function initLiveMemberSearch() {
    const form = document.getElementById("memberFilterForm");
    const searchInput = document.getElementById("memberSearchInput");
    const statusSelect = document.getElementById("memberStatusSelect");
    const results = document.getElementById("memberResults");
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

        fetch("members.php?" + params.toString(), {
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
        const link = e.target.closest(".member-pagination a[data-page]");
        if (!link) return;
        e.preventDefault();
        fetchResults(parseInt(link.getAttribute("data-page"), 10) || 1);
    });
}

document.addEventListener("DOMContentLoaded", () => {
    initAddMemberForm();
    initLiveMemberSearch();
    if (new URLSearchParams(window.location.search).get('add') === '1') openAddMemberModal();
});