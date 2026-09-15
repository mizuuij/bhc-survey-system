<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$resident = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/profile.php');
}

$lastName = trim($_POST['last_name'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$extensionName = trim($_POST['extension_name'] ?? '');
$civilStatus = trim($_POST['civil_status'] ?? '');
$birthday = trim($_POST['birthday'] ?? '');
$occupation = trim($_POST['occupation'] ?? '');
$employer = trim($_POST['employer'] ?? '');
$employerAddress = trim($_POST['employer_address'] ?? '');

$allowedCivilStatus = ['single', 'married', 'widowed', 'separated', 'divorced'];

if ($lastName === '' || $firstName === '') {
    flash('personal_error', 'Last name and first name are required.');
    redirect('../resident/profile.php');
}
if (!in_array($civilStatus, $allowedCivilStatus, true)) {
    flash('personal_error', 'Please select a valid civil status.');
    redirect('../resident/profile.php');
}
if ($birthday === '' || !DateTime::createFromFormat('!Y-m-d', $birthday) || (int) substr($birthday, 0, 4) < 1900 || (int) substr($birthday, 0, 4) > (int) date('Y')) {
    flash('personal_error', 'Please provide a valid Birth of Date.');
    redirect('../resident/profile.php');
}
if ($birthday > date('Y-m-d')) {
    flash('personal_error', 'Birth of Date cannot be in the future.');
    redirect('../resident/profile.php');
}
if (mb_strlen($lastName) > 80 || mb_strlen($firstName) > 80 || mb_strlen($middleName) > 80) {
    flash('personal_error', 'Please keep names under 80 characters.');
    redirect('../resident/profile.php');
}
if (mb_strlen($extensionName) > 20) {
    flash('personal_error', 'Extension name must be under 20 characters.');
    redirect('../resident/profile.php');
}
if (mb_strlen($occupation) > 120 || mb_strlen($employer) > 120) {
    flash('personal_error', 'Please keep occupation and employer under 120 characters.');
    redirect('../resident/profile.php');
}

$db = database();
$existingStmt = $db->prepare('SELECT last_name, first_name, middle_name, extension_name, civil_status, birthday, occupation, employer, employer_address FROM resident_profile WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$resident['id']]);
$existing = $existingStmt->fetch();

$new = [
    'last_name' => $lastName,
    'first_name' => $firstName,
    'middle_name' => $middleName !== '' ? $middleName : null,
    'extension_name' => $extensionName !== '' ? $extensionName : null,
    'civil_status' => $civilStatus,
    'birthday' => $birthday,
    'occupation' => $occupation !== '' ? $occupation : null,
    'employer' => $employer !== '' ? $employer : null,
    'employer_address' => $employerAddress !== '' ? $employerAddress : null,
];

if ($existing) {
    $noChanges = true;
    foreach ($new as $key => $value) {
        if ((string) ($existing[$key] ?? '') !== (string) ($value ?? '')) {
            $noChanges = false;
            break;
        }
    }
    if ($noChanges) {
        flash('personal_success', 'No changes to save.');
        redirect('../resident/profile.php');
    }
}

$statement = $db->prepare(
    'INSERT INTO resident_profile (resident_id, last_name, first_name, middle_name, extension_name, civil_status, birthday, occupation, employer, employer_address)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        last_name = VALUES(last_name),
        first_name = VALUES(first_name),
        middle_name = VALUES(middle_name),
        extension_name = VALUES(extension_name),
        civil_status = VALUES(civil_status),
        birthday = VALUES(birthday),
        occupation = VALUES(occupation),
        employer = VALUES(employer),
        employer_address = VALUES(employer_address)'
);
$statement->execute([
    $resident['id'],
    $new['last_name'],
    $new['first_name'],
    $new['middle_name'],
    $new['extension_name'],
    $new['civil_status'],
    $new['birthday'],
    $new['occupation'],
    $new['employer'],
    $new['employer_address'],
]);

flash('personal_success', 'Personal information saved.');
redirect('../resident/profile.php');
