(function () {

    var overlay = null;
    var lastFocusedEl = null;

    function buildOverlay() {
        var el = document.createElement("div");
        el.className = "logout-overlay";
        el.setAttribute("role", "dialog");
        el.setAttribute("aria-modal", "true");
        el.setAttribute("aria-labelledby", "logoutTitle");
        el.setAttribute("aria-describedby", "logoutDesc");

        el.innerHTML =
            '<div class="logout-card">' +
                '<div class="logout-icon" aria-hidden="true">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
                        'stroke-linecap="round" stroke-linejoin="round">' +
                        '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>' +
                        '<polyline points="16 17 21 12 16 7"></polyline>' +
                        '<line x1="21" y1="12" x2="9" y2="12"></line>' +
                    '</svg>' +
                '</div>' +
                '<h2 id="logoutTitle">Log out?</h2>' +
                '<p id="logoutDesc">You\u2019ll need to sign in again to access the resident portal.</p>' +
                '<div class="logout-actions">' +
                    '<button type="button" class="logout-cancel">Cancel</button>' +
                    '<button type="button" class="logout-confirm">' +
                        '<span class="logout-spinner" aria-hidden="true"></span>' +
                        '<span class="logout-confirm-label">Yes, log out</span>' +
                    '</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(el);
        return el;
    }

    function ensureOverlay() {
        if (!overlay) {
            overlay = buildOverlay();

            overlay.querySelector(".logout-cancel").addEventListener("click", closeModal);
            overlay.addEventListener("click", function (e) {
                if (e.target === overlay) closeModal();
            });
            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape" && overlay.classList.contains("open")) {
                    closeModal();
                }
            });
            overlay.querySelector(".logout-confirm").addEventListener("click", confirmLogout);
        }
        return overlay;
    }

    function openModal() {
        var el = ensureOverlay();
        lastFocusedEl = document.activeElement;
        el.classList.add("open");
        var cancelBtn = el.querySelector(".logout-cancel");
        if (cancelBtn) cancelBtn.focus();
    }

    function closeModal() {
        if (!overlay) return;
        overlay.classList.remove("open");
        if (lastFocusedEl && typeof lastFocusedEl.focus === "function") {
            lastFocusedEl.focus();
        }
    }

    function confirmLogout() {
        var btn = overlay.querySelector(".logout-confirm");
        var label = overlay.querySelector(".logout-confirm-label");

        btn.classList.add("loading");
        btn.disabled = true;
        overlay.querySelector(".logout-cancel").disabled = true;
        if (label) label.textContent = "Logging out\u2026";

        setTimeout(function () {
            document.body.classList.add("logout-page-fade");
            setTimeout(function () {
                window.location.href = "../process/logout.php";
            }, 320);
        }, 450);
    }

    window.logout = function () {
        openModal();
    };

})();