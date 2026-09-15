<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/admin_profile.php');
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$birthday = trim((string) ($_POST['birthday'] ?? ''));

if ($fullName === '' || mb_strlen($fullName) > 120) {
    flash('profile_error', 'Enter a valid full name.');
    redirect('../admin/pages/admin_profile.php');
}
if (mb_strlen($contactNumber) > 30 || mb_strlen($address) > 1000) {
    flash('profile_error', 'Contact number or address is too long.');
    redirect('../admin/pages/admin_profile.php');
}
if ($birthday !== '') {
    $birthDate = DateTime::createFromFormat('!Y-m-d', $birthday);
    $currentYear = (int) date('Y');
    if (!$birthDate || $birthDate->format('Y-m-d') !== $birthday || (int) $birthDate->format('Y') < 1900 || (int) $birthDate->format('Y') > $currentYear || $birthday > date('Y-m-d')) {
        flash('profile_error', 'Birth of Date must be between 1900 and today.');
        redirect('../admin/pages/admin_profile.php');
    }
}

$db = database();
$update = $db->prepare('UPDATE staff_admin SET full_name = ?, contact_number = ?, address = ?, birthday = ? WHERE id = ?');
$update->execute([$fullName, $contactNumber, $address, $birthday !== '' ? $birthday : null, $admin['id']]);

flash('profile_success', 'Personal information updated successfully.');
redirect('../admin/pages/admin_profile.php');
