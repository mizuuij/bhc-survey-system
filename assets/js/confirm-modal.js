/* ===================================================================
   Generic confirmation modal
   Any <form data-confirm-modal='{"title":"...","description":"...",
   "confirmLabel":"...","variant":"danger|info"}'> is intercepted on
   submit and asks for confirmation via a styled modal (instead of the
   native confirm() dialog) before actually submitting.
   =================================================================== */

(function () {
    var overlay = null;
    var lastFocusedEl = null;
    var pendingForm = null;

    function buildOverlay() {
        var el = document.createElement("div");
        el.className = "confirm-overlay";
        el.setAttribute("role", "dialog");
        el.setAttribute("aria-modal", "true");
        el.setAttribute("aria-labelledby", "confirmModalTitle");
        el.setAttribute("aria-describedby", "confirmModalDesc");

        el.innerHTML =
            '<div class="confirm-card">' +
                '<div class="confirm-icon" aria-hidden="true">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
                        'stroke-linecap="round" stroke-linejoin="round">' +
                        '<circle cx="12" cy="12" r="10"></circle>' +
                        '<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"></path>' +
                        '<line x1="12" y1="17" x2="12.01" y2="17"></line>' +
                    '</svg>' +
                '</div>' +
                '<h2 id="confirmModalTitle" class="confirm-title"></h2>' +
                '<p id="confirmModalDesc" class="confirm-desc"></p>' +
                '<div class="confirm-actions">' +
                    '<button type="button" class="confirm-cancel">Cancel</button>' +
                    '<button type="button" class="confirm-confirm">' +
                        '<span class="confirm-spinner" aria-hidden="true"></span>' +
                        '<span class="confirm-confirm-label"></span>' +
                    '</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(el);
        return el;
    }

    function ensureOverlay() {
        if (!overlay) {
            overlay = buildOverlay();

            overlay.querySelector(".confirm-cancel").addEventListener("click", closeModal);
            overlay.addEventListener("click", function (e) {
                if (e.target === overlay) closeModal();
            });
            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape" && overlay.classList.contains("open")) {
                    closeModal();
                }
            });
            overlay.querySelector(".confirm-confirm").addEventListener("click", confirmSubmit);
        }
        return overlay;
    }

    function openModal(form, config) {
        var el = ensureOverlay();
        var icon = el.querySelector(".confirm-icon");
        var confirmBtn = el.querySelector(".confirm-confirm");
        var isInfo = config.variant === "info";

        el.querySelector(".confirm-title").textContent = config.title || "Are you sure?";
        el.querySelector(".confirm-desc").textContent = config.description || "";
        el.querySelector(".confirm-confirm-label").textContent = config.confirmLabel || "Confirm";

        icon.classList.toggle("confirm-icon-info", isInfo);
        confirmBtn.classList.toggle("confirm-confirm-info", isInfo);
        confirmBtn.classList.remove("loading");
        confirmBtn.disabled = false;
        el.querySelector(".confirm-cancel").disabled = false;

        pendingForm = form;
        lastFocusedEl = document.activeElement;
        el.classList.add("open");
        el.querySelector(".confirm-cancel").focus();
    }

    function closeModal() {
        if (!overlay) return;
        overlay.classList.remove("open");
        pendingForm = null;
        if (lastFocusedEl && typeof lastFocusedEl.focus === "function") {
            lastFocusedEl.focus();
        }
    }

    function confirmSubmit() {
        if (!pendingForm) return;
        var btn = overlay.querySelector(".confirm-confirm");
        var label = overlay.querySelector(".confirm-confirm-label");
        var originalLabel = label.textContent;

        btn.classList.add("loading");
        btn.disabled = true;
        overlay.querySelector(".confirm-cancel").disabled = true;
        label.textContent = originalLabel;

        var form = pendingForm;
        form.dataset.confirmed = "1";
        form.submit();
    }

    document.addEventListener("submit", function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.hasAttribute("data-confirm-modal")) return;
        if (form.dataset.confirmed === "1") return;

        var config;
        try {
            config = JSON.parse(form.getAttribute("data-confirm-modal"));
        } catch (e2) {
            return;
        }

        e.preventDefault();
        openModal(form, config);
    });
})();