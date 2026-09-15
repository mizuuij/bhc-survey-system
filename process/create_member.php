<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/../config/upload.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/members.php');
}

$headName = trim((string) ($_POST['head_name'] ?? ''));
$contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$householdNumber = trim((string) ($_POST['household_number'] ?? ''));
$purok = trim((string) ($_POST['purok'] ?? ''));

$allowedPuroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4'];

// Personal Information — all optional at creation time; the admin can also
// fill these in later from the resident's full record.
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$middleName = trim((string) ($_POST['middle_name'] ?? ''));
$extensionName = trim((string) ($_POST['extension_name'] ?? ''));
$sex = trim((string) ($_POST['sex'] ?? ''));
$civilStatus = trim((string) ($_POST['civil_status'] ?? ''));
$birthday = trim((string) ($_POST['birthday'] ?? ''));
$occupation = trim((string) ($_POST['occupation'] ?? ''));
$employer = trim((string) ($_POST['employer'] ?? ''));
$employerAddress = trim((string) ($_POST['employer_address'] ?? ''));
$allowedCivilStatus = ['single', 'married', 'widowed', 'separated', 'divorced'];
$spouseName = trim((string) ($_POST['spouse_name'] ?? ''));
$spouseOccupation = trim((string) ($_POST['spouse_occupation'] ?? ''));
$spouseEmployer = trim((string) ($_POST['spouse_employer'] ?? ''));
$fatherName = trim((string) ($_POST['father_name'] ?? ''));
$motherName = trim((string) ($_POST['mother_name'] ?? ''));
$referenceNames = [1 => trim((string) ($_POST['reference_name_1'] ?? '')), 2 => trim((string) ($_POST['reference_name_2'] ?? ''))];
$childNames = is_array($_POST['child_name'] ?? null) ? $_POST['child_name'] : [];
$childSexes = is_array($_POST['child_sex'] ?? null) ? $_POST['child_sex'] : [];
$childAges = is_array($_POST['child_age'] ?? null) ? $_POST['child_age'] : [];

if ($headName === '' || $contactNumber === '' || $address === '') {
    flash('member_error', 'Household head name, contact number, and address are required.');
    redirect('../admin/pages/members.php');
}

if (!preg_match('/^\d+$/', $contactNumber) || strlen($contactNumber) <= 10 || strlen($contactNumber) > 11) {
    flash('member_error', 'Enter a valid 11-digit contact number using numbers only.');
    redirect('../admin/pages/members.php');
}

if ($purok !== '' && !in_array($purok, $allowedPuroks, true)) {
    flash('member_error', 'Please select a valid purok.');
    redirect('../admin/pages/members.php');
}
$purok = $purok !== '' ? $purok : null;

// Personal info is only saved if at least a name was given. Last and first
// name must be provided together since both are required by the profile.
$hasPersonalInfo = $lastName !== '' || $firstName !== '' || $middleName !== '' || $extensionName !== '' || $sex !== '' || $civilStatus !== '' || $birthday !== '' || $occupation !== '' || $employer !== '' || $employerAddress !== '';
if ($hasPersonalInfo && ($lastName === '' || $firstName === '')) {
    flash('member_error', 'Provide both last name and first name, or leave both blank to add them later.');
    redirect('../admin/pages/members.php');
}
if ($hasPersonalInfo) {
    if ($civilStatus !== '' && !in_array($civilStatus, $allowedCivilStatus, true)) {
        flash('member_error', 'Please select a valid civil status.');
        redirect('../admin/pages/members.php');
    }
    if ($sex !== '' && !in_array($sex, ['male', 'female'], true)) {
        flash('member_error', 'Please select a valid sex.');
        redirect('../admin/pages/members.php');
    }
    if ($birthday !== '') {
        $birthDate = DateTime::createFromFormat('!Y-m-d', $birthday);
        if (!$birthDate || $birthDate->format('Y-m-d') !== $birthday || (int) $birthDate->format('Y') < 1900 || (int) $birthDate->format('Y') > (int) date('Y') || $birthday > date('Y-m-d')) {
            flash('member_error', 'Please provide a valid Birth of Date.');
            redirect('../admin/pages/members.php');
        }
    }
    if (mb_strlen($lastName) > 80 || mb_strlen($firstName) > 80 || mb_strlen($middleName) > 80) {
        flash('member_error', 'Please keep names under 80 characters.');
        redirect('../admin/pages/members.php');
    }
    if (mb_strlen($extensionName) > 20) {
        flash('member_error', 'Extension name must be under 20 characters.');
        redirect('../admin/pages/members.php');
    }
    if (mb_strlen($occupation) > 120 || mb_strlen($employer) > 120) {
        flash('member_error', 'Please keep occupation and employer under 120 characters.');
        redirect('../admin/pages/members.php');
    }
}

if ($spouseName === '' && ($spouseOccupation !== '' || $spouseEmployer !== '')) {
    flash('member_error', 'Provide a spouse name when adding spouse details.');
    redirect('../admin/pages/members.php');
}
if (mb_strlen($spouseName) > 120 || mb_strlen($spouseOccupation) > 120 || mb_strlen($spouseEmployer) > 120) {
    flash('member_error', 'Please keep spouse fields under 120 characters.');
    redirect('../admin/pages/members.php');
}
if (($fatherName !== '' && $motherName === '') || ($fatherName === '' && $motherName !== '')) {
    flash('member_error', "Provide both parents' names, or leave both blank.");
    redirect('../admin/pages/members.php');
}
if (mb_strlen($fatherName) > 120 || mb_strlen($motherName) > 120) {
    flash('member_error', 'Please keep parents names under 120 characters.');
    redirect('../admin/pages/members.php');
}

$children = [];
$childCount = max(count($childNames), count($childSexes), count($childAges));
for ($i = 0; $i < $childCount; $i++) {
    $childName = trim((string) ($childNames[$i] ?? ''));
    $childSex = trim((string) ($childSexes[$i] ?? ''));
    $childAge = trim((string) ($childAges[$i] ?? ''));
    if ($childName === '' && $childSex === '' && $childAge === '') continue;
    if ($childName === '' || !in_array($childSex, ['male', 'female'], true) || !preg_match('/^\d+$/', $childAge) || (int) $childAge > 120) {
        flash('member_error', 'Each child needs a name, sex, and a valid age from 0 to 120.');
        redirect('../admin/pages/members.php');
    }
    if (mb_strlen($childName) > 120) {
        flash('member_error', 'Child names must be under 120 characters.');
        redirect('../admin/pages/members.php');
    }
    $children[] = [$childName, $childSex, (int) $childAge];
}

$hasReferences = $referenceNames[1] !== '' || $referenceNames[2] !== '';
if ($hasReferences && ($referenceNames[1] === '' || $referenceNames[2] === '')) {
    flash('member_error', 'Provide both character references, or leave both blank.');
    redirect('../admin/pages/members.php');
}
foreach ($referenceNames as $referenceName) {
    if (mb_strlen($referenceName) > 120) {
        flash('member_error', 'Reference names must be under 120 characters.');
        redirect('../admin/pages/members.php');
    }
}

$db = database();

try {
    $photoPath = store_uploaded_image('photo', 'photos', 3 * 1024 * 1024);
    $signaturePaths = [1 => null, 2 => null];
    if ($hasReferences) {
        for ($i = 1; $i <= 2; $i++) {
            $signaturePaths[$i] = store_uploaded_image('signature_' . $i, 'signatures', 2 * 1024 * 1024);
        }
    }
    $db->beginTransaction();

    // Determine the next resident number (HH-0001, HH-0002, ...).
    $lastNumber = (int) $db->query(
        "SELECT MAX(CAST(SUBSTRING(resident_number, 4) AS UNSIGNED)) FROM residents WHERE resident_number REGEXP '^HH-[0-9]+$'"
    )->fetchColumn();
    $nextNumber = $lastNumber + 1;
    $residentNumber = 'HH-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

    if ($householdNumber === '') {
        $householdNumber = $residentNumber;
    }

    $temporaryPassword = $residentNumber;
    $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

    $insert = $db->prepare('INSERT INTO residents (resident_number, household_number, head_name, contact_number, address, purok, password_hash, must_change_password, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)');
    $insert->execute([$residentNumber, $householdNumber, $headName, $contactNumber, $address, $purok, $passwordHash]);
    $residentId = (int) $db->lastInsertId();

    if ($hasPersonalInfo || $photoPath !== null) {
        $profileInsert = $db->prepare(
            'INSERT INTO resident_profile (resident_id, last_name, first_name, middle_name, extension_name, sex, civil_status, birthday, occupation, employer, employer_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $profileInsert->execute([
            $residentId,
            $lastName,
            $firstName,
            $middleName !== '' ? $middleName : null,
            $extensionName !== '' ? $extensionName : null,
            $sex !== '' ? $sex : null,
            $civilStatus !== '' ? $civilStatus : null,
            $birthday !== '' ? $birthday : null,
            $occupation !== '' ? $occupation : null,
            $employer !== '' ? $employer : null,
            $employerAddress !== '' ? $employerAddress : null,
        ]);
        if ($photoPath !== null) {
            $db->prepare('UPDATE resident_profile SET photo_path = ? WHERE resident_id = ?')->execute([$photoPath, $residentId]);
        }
    }

    if ($spouseName !== '') {
        $db->prepare('INSERT INTO resident_spouse (resident_id, spouse_name, occupation, employer) VALUES (?, ?, ?, ?)')->execute([$residentId, $spouseName, $spouseOccupation !== '' ? $spouseOccupation : null, $spouseEmployer !== '' ? $spouseEmployer : null]);
    }
    if ($fatherName !== '') {
        $db->prepare('INSERT INTO resident_parents (resident_id, father_name, mother_name) VALUES (?, ?, ?)')->execute([$residentId, $fatherName, $motherName]);
    }
    if ($children) {
        $childInsert = $db->prepare('INSERT INTO resident_children (resident_id, child_name, sex, age) VALUES (?, ?, ?, ?)');
        foreach ($children as [$childName, $childSex, $childAge]) $childInsert->execute([$residentId, $childName, $childSex, $childAge]);
    }
    if ($hasReferences) {
        $referenceInsert = $db->prepare('INSERT INTO resident_references (resident_id, reference_name, signature_path) VALUES (?, ?, ?)');
        for ($i = 1; $i <= 2; $i++) $referenceInsert->execute([$residentId, $referenceNames[$i], $signaturePaths[$i]]);
    }

    $db->commit();
    log_activity($db, 'Created', 'Member', $headName);

    flash('member_success', "Member added. Account No. {$residentNumber}, default password: {$temporaryPassword} (the resident will be asked to change it on first login).");
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $message = str_contains($exception->getMessage(), 'household_number')
        ? 'That household number is already in use.'
        : 'The member could not be added.';
    flash('member_error', $message);
}

redirect('../admin/pages/members.php');