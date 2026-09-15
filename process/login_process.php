<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/login.php');
}

$identifier = trim($_POST['identifier'] ?? $_POST['resident_number'] ?? $_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($identifier === '' || $password === '') {
    flash('login_error', 'Invalid Resident Number/Username or password.');
    redirect('../resident/login.php');
}

// Try resident accounts first.
$statement = database()->prepare('SELECT id, password_hash, must_change_password, is_active FROM residents WHERE resident_number = ? LIMIT 1');
$statement->execute([$identifier]);
$resident = $statement->fetch();

if ($resident && password_verify($password, $resident['password_hash'])) {
    if ((int) $resident['is_active'] === 0) {
        flash('login_error', 'This account has been deactivated. Please contact the Barangay Health Center.');
        redirect('../resident/login.php');
    }

    session_regenerate_id(true);
    $_SESSION['resident_id'] = (int) $resident['id'];
    if ((int) $resident['must_change_password'] === 1) {
        redirect('../resident/changepassword.php');
    }
    redirect('../resident/dashboard.php');
}

// Fall back to staff/admin accounts.
$statement = database()->prepare('SELECT id, password_hash FROM staff_admin WHERE username = ? AND is_active = 1 LIMIT 1');
$statement->execute([$identifier]);
$admin = $statement->fetch();

if ($admin && password_verify($password, $admin['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    redirect('../admin/pages/dashboard.php');
}

flash('login_error', 'Invalid Resident Number/Username or password.');
redirect('../resident/login.php');