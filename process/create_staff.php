<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/users.php');
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$role = 'staff';

if ($fullName === '' || $username === '') {
    flash('user_error', 'Full name and username are required.');
    redirect('../admin/pages/users.php');
}

if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    flash('user_error', 'Password must be at least 8 characters and include letters and numbers.');
    redirect('../admin/pages/users.php');
}

if ($password !== $confirmPassword) {
    flash('user_error', 'Passwords do not match.');
    redirect('../admin/pages/users.php');
}

$db = database();
try {
    $insert = $db->prepare('INSERT INTO staff_admin (username, full_name, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
    $insert->execute([$username, $fullName, password_hash($password, PASSWORD_DEFAULT), $role]);
    log_activity($db, 'Created', 'Staff account', $fullName);
    flash('user_success', 'Staff account created successfully.');
} catch (Throwable $exception) {
    $message = str_contains($exception->getMessage(), 'username')
        ? 'That username is already taken.'
        : 'The staff account could not be created.';
    flash('user_error', $message);
}

redirect('../admin/pages/users.php');
