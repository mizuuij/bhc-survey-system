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

function evaluateCpPasswordStrength() {
    const input = document.getElementById('new_password');
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

function initCpMatchCheck() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const matchHint = document.getElementById('cpMatchHint');
    const matchHintText = document.getElementById('cpMatchHintText');
    const submitBtn = document.getElementById('cpSubmitBtn');
    if (!newPassword || !confirmPassword || !matchHint || !matchHintText || !submitBtn) return;

    const evaluate = () => {
        const rules = evaluateCpPasswordStrength();
        const allRulesMet = Object.values(rules).every(Boolean);

        if (confirmPassword.value.length === 0) {
            matchHint.classList.remove('show', 'match', 'mismatch');
            confirmPassword.classList.remove('field-invalid');
        } else if (newPassword.value === confirmPassword.value) {
            matchHint.classList.add('show', 'match');
            matchHint.classList.remove('mismatch');
            matchHintText.textContent = 'Passwords match.';
            confirmPassword.classList.remove('field-invalid');
        } else {
            matchHint.classList.add('show', 'mismatch');
            matchHint.classList.remove('match');
            matchHintText.textContent = 'Passwords do not match.';
            confirmPassword.classList.add('field-invalid');
        }

        const passwordsMatch = confirmPassword.value.length > 0 && newPassword.value === confirmPassword.value;
        submitBtn.disabled = !(allRulesMet && passwordsMatch);
    };

    newPassword.addEventListener('input', evaluate);
    confirmPassword.addEventListener('input', evaluate);
    evaluate();
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initPasswordToggles();
    initCpMatchCheck();
});

document.addEventListener('input', function(){
 const np=document.getElementById('new_password');
 if(np){
   const v=np.value;
   const ok=v.length>=8 && /[A-Z]/.test(v) && /\d/.test(v) && /[^A-Za-z0-9]/.test(v);
   np.classList.toggle('field-invalid', v.length>0 && !ok);
 }
 const cp=document.getElementById('confirm_password');
 if(np && cp){
   cp.classList.toggle('field-invalid', cp.value.length>0 && cp.value!==np.value);
 }
});
