<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
$adminRole = $admin['role'] ?? 'unknown';

$db = database();
$perPage = 10;

$search = trim((string) ($_GET['q'] ?? ''));
$type = (string) ($_GET['type'] ?? 'all');
if (!in_array($type, ['all', 'resident', 'staff', 'survey'], true)) {
    $type = 'all';
}

$rows = [];

if ($type === 'all' || $type === 'resident') {
    $where = ['archived_at IS NOT NULL'];
    $params = [];
    if ($search !== '') {
        $where[] = '(resident_number LIKE ? OR household_number LIKE ? OR head_name LIKE ? OR contact_number LIKE ? OR address LIKE ?)';
        for ($i = 0; $i < 5; $i++) $params[] = '%' . $search . '%';
    }
    $statement = $db->prepare(
        'SELECT id, resident_number, household_number, head_name, contact_number, address, archived_at
         FROM residents WHERE ' . implode(' AND ', $where)
    );
    $statement->execute($params);
    foreach ($statement->fetchAll() as $r) {
        $rows[] = [
            'type' => 'resident',
            'id' => (int) $r['id'],
            'title' => $r['head_name'],
            'subtitle' => 'Account No. ' . $r['resident_number'] . ' · Household No. ' . $r['household_number'],
            'meta' => $r['contact_number'] . ' · ' . $r['address'],
            'archived_at' => $r['archived_at'],
            'view_url' => 'resident_view.php?id=' . (int) $r['id'],
            'restore_action' => '../../process/admin_restore_resident.php',
            'delete_action' => '../../process/admin_delete_resident.php',
            'id_field' => 'resident_id',
            'restore_title' => 'Restore this resident record?',
            'restore_desc' => $r['head_name'] . '\'s record will reappear in Member Management. The account stays deactivated until you activate it again.',
            'delete_title' => 'Permanently delete this resident record?',
            'delete_desc' => 'This deletes ' . $r['head_name'] . '\'s account, household record, and all saved personal, spouse, children, parents, and reference information, plus their photo and signatures. This cannot be undone.',
        ];
    }
}

if ($type === 'all' || $type === 'staff') {
    $where = ['archived_at IS NOT NULL'];
    $params = [];
    if ($search !== '') {
        $where[] = '(username LIKE ? OR full_name LIKE ? OR role LIKE ?)';
        for ($i = 0; $i < 3; $i++) $params[] = '%' . $search . '%';
    }
    $statement = $db->prepare(
        'SELECT id, username, full_name, role, archived_at
         FROM staff_admin WHERE ' . implode(' AND ', $where)
    );
    $statement->execute($params);
    foreach ($statement->fetchAll() as $s) {
        // Staff accounts can't manage Admin accounts, so those rows show up
        // in the list for visibility but without Restore/Delete actions.
        $canManage = !($adminRole === 'staff' && $s['role'] === 'admin');
        $rows[] = [
            'type' => 'staff',
            'id' => (int) $s['id'],
            'title' => $s['full_name'],
            'subtitle' => 'Username ' . $s['username'] . ' · ' . ucfirst($s['role']),
            'meta' => '',
            'archived_at' => $s['archived_at'],
            'view_url' => null,
            'restore_action' => $canManage ? '../../process/admin_restore_staff.php' : null,
            'delete_action' => $canManage ? '../../process/admin_delete_staff.php' : null,
            'id_field' => 'staff_id',
            'restore_title' => 'Restore this staff account?',
            'restore_desc' => $s['full_name'] . '\'s account will reappear in User Management. It stays deactivated until you activate it again.',
            'delete_title' => 'Permanently delete this staff account?',
            'delete_desc' => 'This permanently deletes ' . $s['full_name'] . '\'s staff account. This cannot be undone.',
        ];
    }
}

if ($type === 'all' || $type === 'survey') {
    $where = ['archived_at IS NOT NULL'];
    $params = [];
    if ($search !== '') {
        $where[] = '(title LIKE ? OR description LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $statement = $db->prepare(
        'SELECT id, title, description, opens_at, closes_at, archived_at
         FROM surveys WHERE ' . implode(' AND ', $where)
    );
    $statement->execute($params);
    foreach ($statement->fetchAll() as $s) {
        $rows[] = [
            'type' => 'survey',
            'id' => (int) $s['id'],
            'title' => $s['title'],
            'subtitle' => date('M j, Y', strtotime($s['opens_at'])) . ' - ' . date('M j, Y', strtotime($s['closes_at'])),
            'meta' => $s['description'],
            'archived_at' => $s['archived_at'],
            'view_url' => 'manage_survey.php?id=' . (int) $s['id'],
            'restore_action' => '../../process/admin_restore_survey.php',
            'delete_action' => '../../process/admin_delete_survey.php',
            'id_field' => 'survey_id',
            'restore_title' => 'Restore this survey?',
            'restore_desc' => '"' . $s['title'] . '" will reappear in Survey Management and Survey Reports. It stays closed until you reactivate it.',
            'delete_title' => 'Permanently delete this survey?',
            'delete_desc' => 'This permanently deletes "' . $s['title'] . '" along with its questions, answers, and submissions. This cannot be undone.',
        ];
    }
}

usort($rows, static fn(array $a, array $b): int => strcmp($b['archived_at'], $a['archived_at']));

$archivedCount = count($rows);
$pageCount = max(1, (int) ceil($archivedCount / $perPage));
$page = min(max(1, (int) ($_GET['page'] ?? 1)), $pageCount);
$offset = ($page - 1) * $perPage;
$pageRows = array_slice($rows, $offset, $perPage);

$pageUrl = static function (int $targetPage) use ($search, $type): string {
    $query = ['page' => $targetPage];
    if ($search !== '') $query['q'] = $search;
    if ($type !== 'all') $query['type'] = $type;
    return 'archive.php?' . http_build_query($query);
};

$typeLabels = ['resident' => 'Resident', 'staff' => 'Staff', 'survey' => 'Survey'];
$typeBadge = ['resident' => 'badge-blue', 'staff' => 'badge-warning', 'survey' => 'badge-success'];

$success = flash('archive_success');
$error = flash('archive_error');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Archive</title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=13">
<link rel="stylesheet" href="../css/logout.css">
<link rel="stylesheet" href="../../assets/css/admin-management.css">
<link rel="stylesheet" href="../css/members.css?v=12">
<link rel="stylesheet" href="../css/confirm-modal.css?v=3">
</head>
<body>
<div class="portal-shell">
<div class="sidebar-backdrop"></div>
<aside class="sidebar">
    <div class="sidebar-brand"><div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div><div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div></div>
    <div class="id-card" data-navigate-href="admin_profile.php"><div class="id-eyebrow"><?= $admin['role'] === 'admin' ? 'Administrator Account' : 'Staff Account' ?></div><div class="id-card-row"><div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div><div class="id-card-name"><?=h($admin['full_name'])?><small><?=h(ucfirst($admin['role']))?></small></div></div><div class="id-card-perf"></div><div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div></div>
    <nav class="nav-group"><span class="nav-label">Management</span>
        <a class="nav-link" href="dashboard.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>Dashboard</a>
        <a class="nav-link" href="surveys.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Management</a>
        <a class="nav-link" href="members.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a>
        <a class="nav-link" href="archive.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a>
        <a class="nav-link" href="results.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="20" x2="4" y2="10"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="13"/><line x1="22" y1="20" x2="22" y2="7"/></svg></span>Results Dashboard</a>
        <div class="nav-group-item">
            <button type="button" class="nav-link nav-parent" aria-expanded="false"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>
                <span>Reports</span>
                <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="nav-submenu">
                <a class="nav-link nav-sublink" href="reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Reports</a>
                <a class="nav-link nav-sublink" href="member_reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Reports</a>
            </div>
        </div>
        <?php if ($admin['role'] === 'admin'): ?><a class="nav-link" href="users.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/><path d="M16 11a4 4 0 0 1 0-8"/><path d="M21 21v-2a6 6 0 0 0-4-5.7"/></svg></span>User Management</a><?php endif; ?>
    </nav>
    <div class="nav-footer"><a class="nav-link" href="../../process/admin_logout.php" onclick="event.preventDefault(); logout();"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>Log Out</a></div>
</aside>
<main class="main">
    <div class="sticky-head">
    <div class="topbar">
        <div>
            <button class="menu-toggle">☰</button>
            <div class="page-eyebrow">Account administration</div>
            <h1 class="page-title">Archive</h1>
            <p class="page-sub">Residents, Staff, and Surveys moved out of their management pages (archiving a Survey also removes it from Survey Reports). Restore records or delete them permanently.</p>
        </div>
    </div>
    <?php if ($success): ?><p class="notice notice-success" role="status"><?=h($success)?></p><?php endif; ?>
    <?php if ($error): ?><p class="notice notice-error" role="alert"><?=h($error)?></p><?php endif; ?>

    <form class="member-filters card" method="get" id="archiveFilterForm">
        <div class="member-filters-row">
            <label class="member-filter-field">
                <span class="member-filter-label">Search archive</span>
                <input type="search" name="q" id="archiveSearchInput" value="<?= h($search) ?>" placeholder="Search by name, account no., title, or username" autocomplete="off">
            </label>
            <label class="member-filter-field member-filter-field-status">
                <span class="member-filter-label">Type</span>
                <select name="type" id="archiveTypeSelect">
                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All types</option>
                    <option value="resident" <?= $type === 'resident' ? 'selected' : '' ?>>Resident</option>
                    <option value="staff" <?= $type === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="survey" <?= $type === 'survey' ? 'selected' : '' ?>>Survey</option>
                </select>
            </label>
        </div>
    </form>
    </div>

    <div class="card members-list-card">
        <div class="card-title-row">
            <div>
                <div class="card-title">Archived Records</div>
                <div class="card-desc"><?=$archivedCount?> record<?=$archivedCount===1?'':'s'?> in the archive<?= $archivedCount > $perPage ? ' · Page ' . $page . ' of ' . $pageCount : '' ?><?= ($search !== '' || $type !== 'all') ? ' · Filtered' : '' ?>.</div>
            </div>
        </div>
        <table class="responsive-table member-table archive-table">
            <thead><tr><th>Type</th><th>Record</th><th>Archived On</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$pageRows): ?>
                <tr><td colspan="4" data-label=""><?= ($search !== '' || $type !== 'all') ? 'No archived records match your search or filter.' : 'The archive is empty.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($pageRows as $row): ?>
                <tr>
                    <td data-label="Type"><span class="badge <?=h($typeBadge[$row['type']])?>"><?=h($typeLabels[$row['type']])?></span></td>
                    <td data-label="Record">
                        <div class="archive-record-title"><?=h($row['title'])?></div>
                        <div class="archive-record-subtitle"><?=h($row['subtitle'])?></div>
                        <?php if ($row['meta'] !== ''): ?><div class="archive-record-meta" title="<?=h($row['meta'])?>"><?=h($row['meta'])?></div><?php endif; ?>
                    </td>
                    <td data-label="Archived On"><?=h((new DateTime($row['archived_at']))->format('M j, Y g:i A'))?></td>
                    <td data-label="Actions">
                        <div class="row-actions">
                            <?php if ($row['restore_action']): ?>
                            <?php
                                $restoreConfirm = json_encode([
                                    'title' => $row['restore_title'],
                                    'description' => $row['restore_desc'],
                                    'confirmLabel' => 'Yes, restore',
                                    'variant' => 'info',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>
                            <form action="<?=h($row['restore_action'])?>" method="post" data-confirm-modal='<?=$restoreConfirm?>'>
                                <input type="hidden" name="<?=h($row['id_field'])?>" value="<?=$row['id']?>">
                                <button class="btn btn-primary btn-sm icon-action" type="submit" aria-label="Restore" data-tooltip="Restore"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($row['delete_action']): ?>
                            <?php
                                $deleteConfirm = json_encode([
                                    'title' => $row['delete_title'],
                                    'description' => $row['delete_desc'],
                                    'confirmLabel' => 'Yes, delete permanently',
                                    'variant' => 'danger',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>
                            <form action="<?=h($row['delete_action'])?>" method="post" data-confirm-modal='<?=$deleteConfirm?>'>
                                <input type="hidden" name="<?=h($row['id_field'])?>" value="<?=$row['id']?>">
                                <button class="btn btn-danger btn-sm icon-action" type="submit" aria-label="Delete permanently" data-tooltip="Delete permanently"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/></svg></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($archivedCount > 0): ?>
            <nav class="member-pagination" aria-label="Archive pages">
                <?php if ($page > 1): ?><a class="btn btn-outline" href="<?=h($pageUrl($page - 1))?>">Previous</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Previous</span><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pageCount ?></span>
                <?php if ($page < $pageCount): ?><a class="btn btn-outline" href="<?=h($pageUrl($page + 1))?>">Next</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Next</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</main>
</div>

<script src="../../assets/js/dashboard.js?v=11"></script>
<script src="../js/logout.js"></script>
<script src="../js/confirm-modal.js?v=3"></script>
<script>
document.getElementById('archiveTypeSelect').addEventListener('change', function () {
    document.getElementById('archiveFilterForm').submit();
});
</script>
</body>
</html>