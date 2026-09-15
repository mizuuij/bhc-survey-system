<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    $statement = database()->prepare('SELECT id, username, full_name, role, contact_number, address, birthday FROM staff_admin WHERE id = ? AND is_active = 1 LIMIT 1');
    $statement->execute([$_SESSION['admin_id']]);
    return $statement->fetch() ?: null;
}

function require_admin(): array
{
    send_no_cache_headers();
    $admin = current_admin();
    if ($admin === null) {
        redirect(str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/process/') ? '../resident/login.php' : '../../resident/login.php');
    }

    return $admin;
}