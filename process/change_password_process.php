<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$resident = require_login();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/changepassword.php');
}

$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirmation = $_POST['confirm_password'] ?? '';
$statement = database()->prepare('SELECT password_hash FROM residents WHERE id = ?');
$statement->execute([$resident['id']]);
$hash = (string) $statement->fetchColumn();

if (!password_verify($current, $hash)) {
    $_SESSION['old_new_password'] = $new;
    $_SESSION['old_confirm_password'] = $confirmation;
    flash('password_error', 'Your current password is incorrect.');
} elseif (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/\d/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) {
    $_SESSION['old_new_password'] = $new;
    $_SESSION['old_confirm_password'] = $confirmation;
    flash('password_error', 'Password must be at least 8 characters and include an uppercase letter, a number, and a special character.');
} elseif ($new !== $confirmation) {
    $_SESSION['old_new_password'] = $new;
    $_SESSION['old_confirm_password'] = $confirmation;
    flash('password_error', 'New password and confirmation do not match.');
} else {
    $update = database()->prepare('UPDATE residents SET password_hash = ?, must_change_password = 0 WHERE id = ?');
    $update->execute([password_hash($new, PASSWORD_DEFAULT), $resident['id']]);
    unset($_SESSION['old_new_password'], $_SESSION['old_confirm_password']);
    flash('password_success', 'Password changed successfully.');
}

redirect('../resident/changepassword.php');
