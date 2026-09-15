<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function redirect(string $path)
{
    header('Location: ' . $path);
    exit;
}

// Authenticated pages must never be served from the browser's back-forward
// cache — otherwise pressing Back after logout can show a fully interactive
// snapshot of a page like the dashboard, with real data still on screen,
// even though the session behind it is already gone.
function send_no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_resident(): ?array
{
    if (empty($_SESSION['resident_id'])) {
        return null;
    }

    $statement = database()->prepare('SELECT id, resident_number, household_number, head_name, contact_number, address, must_change_password FROM residents WHERE id = ? AND is_active = 1 LIMIT 1');
    $statement->execute([$_SESSION['resident_id']]);
    return $statement->fetch() ?: null;
}

function log_activity(PDO $db, string $action, string $entityType, string $entityName): void
{
    $actorType = 'resident';
    $actorId = (int) ($_SESSION['resident_id'] ?? 0);
    $actorName = '';
    $actorRole = 'Resident';

    if (!empty($_SESSION['admin_id'])) {
        $actorType = 'staff';
        $actorId = (int) $_SESSION['admin_id'];
        $actor = $db->prepare('SELECT full_name, role FROM staff_admin WHERE id = ? LIMIT 1');
        $actor->execute([$actorId]);
        $actorRecord = $actor->fetch();
        $actorName = (string) ($actorRecord['full_name'] ?? 'Unknown user');
        $actorRole = ucfirst((string) ($actorRecord['role'] ?? 'Staff'));
    } elseif ($actorId > 0) {
        $actor = $db->prepare('SELECT head_name FROM residents WHERE id = ? LIMIT 1');
        $actor->execute([$actorId]);
        $actorName = (string) ($actor->fetchColumn() ?: 'Unknown user');
    }

    if ($actorId <= 0) {
        return;
    }

    $insert = $db->prepare(
        'INSERT INTO activity_log (actor_type, actor_id, actor_name, actor_role, action, entity_type, entity_name)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$actorType, $actorId, $actorName, $actorRole, $action, $entityType, $entityName]);
}

function require_login(): array
{
    send_no_cache_headers();
    $resident = current_resident();
    if ($resident === null) {
        redirect(str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/process/') ? '../resident/login.php' : 'login.php');
    }

    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $allowedWhileChangingPassword = ['changepassword.php', 'change_password_process.php', 'logout.php'];
    if ((int) $resident['must_change_password'] === 1 && !in_array($scriptName, $allowedWhileChangingPassword, true)) {
        redirect(str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/process/') ? '../resident/changepassword.php' : 'changepassword.php');
    }

    return $resident;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}
