<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
$currentAdmin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/users.php');
}

$staffId = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
if (!$staffId) {
    redirect('../admin/pages/users.php');
}

$db = database();
$exists = $db->prepare('SELECT id, full_name, role, is_active FROM staff_admin WHERE id = ?');
$exists->execute([$staffId]);
$staff = $exists->fetch();
if (!$staff) {
    flash('user_error', 'Staff account not found.');
    redirect('../admin/pages/users.php');
}

if ($staff['role'] !== 'staff') {
    flash('user_error', 'Administrator accounts are managed from Profile Information.');
    redirect('../admin/pages/users.php');
}

$currentUserRole = $currentAdmin['role'] ?? 'unknown';
$targetUserRole = $staff['role'];


if ($currentUserRole === 'staff') {
    
    if($targetUserRole  === 'admin'){
        flash('user_error', 'Staff accounts do not have permission to modify Admin accounts.');
        redirect('../admin/pages/users.php');
        exit;
    }
        
}


$action = (string) ($_POST['action'] ?? 'save');

if ($action === 'toggle') {
    if ($staffId === (int) $currentAdmin['id']) {
        flash('user_error', 'You cannot deactivate your own account.');
        redirect('../admin/pages/users.php');
    }

    $newStatus = ((int) $staff['is_active']) === 1 ? 0 : 1;

    if ($newStatus === 0 && $staff['role'] === 'admin') {
        $activeAdmins = (int) $db->query("SELECT COUNT(*) FROM staff_admin WHERE role = 'admin' AND is_active = 1")->fetchColumn();
        if ($activeAdmins <= 1) {
            flash('user_error', 'At least one active administrator account must remain.');
            redirect('../admin/pages/users.php');
        }
    }

    $db->prepare('UPDATE staff_admin SET is_active = ? WHERE id = ?')->execute([$newStatus, $staffId]);
    log_activity($db, 'Updated', 'Staff account', $staff['full_name']);
    flash('user_success', $newStatus ? 'Staff account activated.' : 'Staff account deactivated.');
    redirect('../admin/pages/users.php');
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$role = 'staff';

if ($fullName === '') {
    flash('user_error', 'Full name is required.');
    redirect('../admin/pages/users.php');
}

if ($role !== 'admin' && $staff['role'] === 'admin') {
    $activeAdmins = (int) $db->query("SELECT COUNT(*) FROM staff_admin WHERE role = 'admin' AND is_active = 1")->fetchColumn();
    if ($activeAdmins <= 1) {
        flash('user_error', 'At least one active administrator account must remain.');
        redirect('../admin/pages/users.php');
    }
}

$db->prepare('UPDATE staff_admin SET full_name = ?, role = ? WHERE id = ?')->execute([$fullName, $role, $staffId]);
log_activity($db, 'Updated', 'Staff account', $fullName);
flash('user_success', 'Staff account updated.');
redirect('../admin/pages/users.php');
