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

document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
});