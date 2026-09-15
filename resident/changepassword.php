<?php require_once __DIR__ . '/../config/auth.php'; $resident = require_login(); $initials = ''; foreach (array_slice(preg_split('/\s+/', trim($resident['head_name'])), 0, 2) as $w) { $initials .= mb_strtoupper(mb_substr($w, 0, 1)); } $passwordSuccess = flash('password_success'); $passwordError = flash('password_error'); $oldNewPassword = $_SESSION['old_new_password'] ?? ''; $oldConfirmPassword = $_SESSION['old_confirm_password'] ?? ''; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password — Barangay Health Center Resident Profiling & Survey Management System</title>
<link rel="stylesheet" href="../assets/css/changepassword.css?v=8">
<link rel="stylesheet" href="../assets/css/logout.css">
</head>
<body>

<div class="portal-shell">

    <div class="sidebar-backdrop"></div>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-seal"><img src="../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div>
            <div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System
                <small>Resident Portal</small>
            </div>
        </div>

        <div class="id-card">
            <div class="id-eyebrow">Household ID</div>
            <div class="id-card-row">
                <div class="id-avatar"><?= h($initials) ?></div>
                <div class="id-card-name"><?= h($resident['head_name']) ?>
                    <small>Household Head</small>
                </div>
            </div>
            <div class="id-card-perf"></div>
            <div class="id-card-number"><span>Household No.</span><?= h($resident['household_number']) ?></div>
        </div>

        <nav class="nav-group">
            <span class="nav-label">Menu</span>
            <a class="nav-link" href="dashboard.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>
                Dashboard
            </a>
            <a class="nav-link" href="surveys.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                Available Surveys
            </a>
            <a class="nav-link" href="profile.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                Profile
            </a>
            <a class="nav-link" href="changepassword.php" aria-current="page">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.85 12.15 19 4"/><path d="M18 5l2 2"/><path d="M15 8l2 2"/></svg></span>
                Change Password
            </a>
        </nav>

        <div class="nav-footer">
            <a class="nav-link" href="../process/logout.php" onclick="event.preventDefault(); logout();">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                Log Out
            </a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar" style="justify-content: flex-start;">
            <div>
                <button class="menu-toggle" aria-label="Toggle menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
            </div>
        </div>

        <div class="cp-center">
        <div class="card" style="max-width: 480px; width: 100%; margin-left: auto; margin-right: auto;">
            <div class="cp-head">
                <h2>Change Password</h2>
                <p>Update your own resident portal password.</p>
            </div>
            <div class="toast <?= $passwordSuccess ? 'show' : '' ?>" id="passwordToast">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                <?= h($passwordSuccess ?? 'Password changed successfully.') ?>
            </div>
            <?php if ($passwordError): ?>
            <div class="toast toast-error show" id="passwordErrorToast" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= h($passwordError) ?>
            </div>
            <?php endif; ?>

            <form id="passwordForm" method="post" action="../process/change_password_process.php">
                <div class="field cp-field">
                    <label for="current_password">Current Password</label>
                    <div class="password-field">
                    <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required>
                    <button type="button" class="toggle-password" data-target="current_password" aria-label="Show password" aria-pressed="false">
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
                <div class="field cp-field">
                    <label for="new_password">New Password</label>
                    <div class="password-field">
                    <input type="password" id="new_password" name="new_password" value="<?= h($oldNewPassword) ?>" placeholder="Enter new password" required>
                    <button type="button" class="toggle-password" data-target="new_password" aria-label="Show password" aria-pressed="false">
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
                    <div class="cp-strength-bar" id="cpStrengthBar"><span></span><span></span><span></span><span></span></div>
                    <ul class="cp-requirements" id="cpRequirements">
                        <li data-rule="length"><span class="cp-req-dot"></span>At least 8 characters</li>
                        <li data-rule="upper"><span class="cp-req-dot"></span>At least 1 uppercase letter</li>
                        <li data-rule="number"><span class="cp-req-dot"></span>At least 1 number</li>
                        <li data-rule="special"><span class="cp-req-dot"></span>At least 1 special character</li>
                    </ul>
                </div>
                <div class="field cp-field">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-field">
                    <input type="password" id="confirm_password" name="confirm_password" value="<?= h($oldConfirmPassword) ?>" placeholder="Re-enter new password" required>
                    <button type="button" class="toggle-password" data-target="confirm_password" aria-label="Show password" aria-pressed="false">
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
                    <div class="cp-match-hint" id="cpMatchHint">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        <span id="cpMatchHintText">Passwords match.</span>
                    </div>
                </div>

                <div class="modal-actions">
                    <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-dark" id="cpSubmitBtn">Update Password</button>
                </div>
            </form>
        </div>
        </div>
    </main>
</div>

<script src="../assets/js/changepassword.js?v=3"></script>
<script src="../assets/js/logout.js"></script>
</body>
</html>