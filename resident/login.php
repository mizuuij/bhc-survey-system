<?php
require_once __DIR__ . '/../config/auth.php';
$loginError = flash('login_error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Longos Health Portal — Log In</title>
<link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

<div class="login-card">
    <div class="brand-panel">
        <div class="brand-eyebrow">Barangay Longos<br>Health Portal</div>
        <h2 class="brand-heading">Your record.<br>Your voice.<br>Better care.</h2>

        <ul class="brand-features">
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6a1 1 0 0 1 1 1v1h1a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1V4a1 1 0 0 1 1-1Z"/><line x1="8" y1="11" x2="16" y2="11"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="8" y1="19" x2="13" y2="19"/></svg>
                <span>Keep your resident profile up to date</span>
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="12"/><line x1="12" y1="20" x2="12" y2="7"/><line x1="18" y1="20" x2="18" y2="15"/></svg>
                <span>Answer official community health surveys</span>
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3Z"/></svg>
                <span>Your information stays secure</span>
            </li>
        </ul>

        <div class="preview-card" aria-hidden="true">
            <div class="preview-card-head"><span class="preview-dot"></span> For Residents</div>
            <p class="preview-question">What would you like to do?</p>
            <div class="preview-options">
                <label><span class="radio checked"></span> Review my profile</label>
                <label><span class="radio"></span> Answer a health survey</label>
                <label><span class="radio"></span> View my submissions</label>
            </div>
            <div class="preview-bar-row">
                <span class="preview-bar bar-blue"></span>
                <span class="preview-bar bar-gold"></span>
                <span class="preview-bar bar-blue"></span>
            </div>
        </div>
    </div>

    <div class="form-panel">
        <div class="eyebrow">Welcome</div>
        <h1>Log In</h1>
        <p class="subtitle">Residents can log in to manage their profile and answer health surveys. Staff and administrators can log in here too, using their own account.</p>

        <div class="alert-slot <?= $loginError ? 'show' : '' ?>" id="alertSlot"><?= h($loginError ?? 'Invalid Resident Number/Username or password.') ?></div>

        <form method="post" action="../process/login_process.php">
            <div class="field">
                <label for="identifier">Resident Number or Username</label>
                <input type="text" id="identifier" name="identifier" placeholder="e.g. HH-0001" autocomplete="username" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="toggle-password" data-target="password" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.6 20.6 0 0 1 4.22-5.11M9.9 4.24A9.75 9.75 0 0 1 12 4c7 0 11 7 11 7a20.6 20.6 0 0 1-2.06 2.94M14.12 14.12a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M1 1l22 22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="login-btn">Log In</button>
        </form>
    </div>
</div>

<script src="../assets/js/login.js"></script>
</body>
</html>