<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';

$admin = require_admin();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$returnTo = (string) ($_POST['return_to'] ?? 'users.php');
$returnTo = in_array($returnTo, ['users.php', 'admin_profile.php'], true) ? $returnTo : 'users.php';
$redirectTarget = '../admin/pages/' . $returnTo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($redirectTarget);
}

$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirmation = $_POST['confirm_password'] ?? '';
$staffId = (int) ($_POST['staff_id'] ?? $admin['id']);
$isOwn = $staffId === (int) $admin['id'];

$rememberFailure = function () use ($new, $confirmation, $staffId): void {
    $_SESSION['admin_old_new_password'] = $new;
    $_SESSION['admin_old_confirm_password'] = $confirmation;
    $_SESSION['admin_open_password_modal'] = true;
    $_SESSION['admin_open_password_modal_staff_id'] = $staffId;
};

if ($isOwn) {
    // Self-service: the account has to prove it knows its current password.
    $statement = database()->prepare('SELECT password_hash FROM staff_admin WHERE id = ? AND is_active = 1 LIMIT 1');
    $statement->execute([$admin['id']]);
    $hash = (string) $statement->fetchColumn();

    if ($hash === '' || !password_verify($current, $hash)) {
        $rememberFailure();
        flash('password_error', 'Your current password is incorrect.');
        redirect($redirectTarget);
    }
} else {
    // An administrator setting a new password for one of their staff members
    // — only a full "admin" account can do this, and only for "staff"
    // accounts (User Management never lists other admins, and neither can
    // this endpoint touch one).
    if ($admin['role'] !== 'admin') {
        flash('user_error', 'You do not have permission to change another account\'s password.');
        redirect($redirectTarget);
    }
    $targetStatement = database()->prepare("SELECT id, username FROM staff_admin WHERE id = ? AND role = 'staff' AND is_active = 1 LIMIT 1");
    $targetStatement->execute([$staffId]);
    $target = $targetStatement->fetch();
    if (!$target) {
        flash('user_error', 'That staff account could not be found.');
        redirect($redirectTarget);
    }
}

if (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/\d/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) {
    $rememberFailure();
    flash('password_error', 'Password must be at least 8 characters and include an uppercase letter, a number, and a special character.');
    redirect($redirectTarget);
}
if ($new !== $confirmation) {
    $rememberFailure();
    flash('password_error', 'New password and confirmation do not match.');
    redirect($redirectTarget);
}

$update = database()->prepare('UPDATE staff_admin SET password_hash = ? WHERE id = ?');
$update->execute([password_hash($new, PASSWORD_DEFAULT), $staffId]);
unset($_SESSION['admin_old_new_password'], $_SESSION['admin_old_confirm_password'], $_SESSION['admin_open_password_modal'], $_SESSION['admin_open_password_modal_staff_id']);
flash('user_success', $isOwn ? 'Password changed successfully.' : "Password for {$target['username']} updated successfully.");

redirect($redirectTarget);
