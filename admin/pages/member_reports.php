<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
$db = database();
$isPreview = defined('RENDER_MEMBER_REPORT_PREVIEW');
$selectedResidentId = filter_input(INPUT_GET, 'resident_id', FILTER_VALIDATE_INT) ?: 0;
if ($selectedResidentId > 0 && !$isPreview) {
    redirect('member_report_preview.php?resident_id=' . $selectedResidentId);
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
$statusFilter = in_array($statusFilter, ['all', 'active', 'inactive'], true) ? $statusFilter : 'all';
$purokOptions = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4'];
$purokFilter = (string) ($_GET['purok'] ?? 'all');
if ($purokFilter !== 'all' && !in_array($purokFilter, $purokOptions, true)) {
    $purokFilter = 'all';
}
$civilStatusOptions = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated', 'divorced' => 'Divorced'];
$civilStatusFilter = (string) ($_GET['civil_status'] ?? 'all');
if ($civilStatusFilter !== 'all' && !array_key_exists($civilStatusFilter, $civilStatusOptions)) {
    $civilStatusFilter = 'all';
}

$where = ['r.archived_at IS NULL'];
$params = [];
if ($search !== '') {
    $where[] = '(r.resident_number LIKE ? OR r.household_number LIKE ? OR r.head_name LIKE ? OR r.contact_number LIKE ? OR r.address LIKE ?)';
    for ($i = 0; $i < 5; $i++) $params[] = '%' . $search . '%';
}
if ($statusFilter === 'active') {
    $where[] = 'r.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'r.is_active = 0';
}
if ($purokFilter !== 'all') {
    $where[] = 'r.purok = ?';
    $params[] = $purokFilter;
}
if ($civilStatusFilter !== 'all') {
    $where[] = 'rp.civil_status = ?';
    $params[] = $civilStatusFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$isFiltered = $search !== '' || $statusFilter !== 'all' || $purokFilter !== 'all' || $civilStatusFilter !== 'all';

$perPage = 10;
$memberCountStatement = $db->prepare("SELECT COUNT(*) FROM residents r LEFT JOIN resident_profile rp ON rp.resident_id = r.id $whereSql");
$memberCountStatement->execute($params);
$filteredCount = (int) $memberCountStatement->fetchColumn();
$pageCount = max(1, (int) ceil($filteredCount / $perPage));
$page = min(max(1, (int) ($_GET['page'] ?? 1)), $pageCount);
$paginationSql = $isPreview ? '' : ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
$memberReportPageUrl = static function (int $targetPage) use ($search, $statusFilter, $purokFilter, $civilStatusFilter): string {
    $query = ['page' => $targetPage];
    if ($search !== '') $query['q'] = $search;
    if ($statusFilter !== 'all') $query['status'] = $statusFilter;
    if ($purokFilter !== 'all') $query['purok'] = $purokFilter;
    if ($civilStatusFilter !== 'all') $query['civil_status'] = $civilStatusFilter;
    return 'member_reports.php?' . http_build_query($query);
};

$memberStatement = $db->prepare(
    "SELECT r.id, r.resident_number, r.household_number, r.head_name, r.contact_number, r.address,
            r.purok, r.is_active, r.created_at, rp.civil_status, rp.birthday
     FROM residents r
     LEFT JOIN resident_profile rp ON rp.resident_id = r.id
     $whereSql
     ORDER BY CASE WHEN rp.civil_status IS NULL OR rp.civil_status = '' THEN 1 ELSE 0 END,
              rp.civil_status ASC, r.head_name ASC" . $paginationSql
);
$memberStatement->execute($params);
$members = $memberStatement->fetchAll();

// --- Member Demographics: Sex Distribution, Age Distribution, Population per
// Purok — mirrors the Dashboard's charts, but scoped to whatever this page's
// own filters currently select, so "View Member Reports" is a genuine deeper
// dive rather than a duplicate of the Dashboard's fixed, unfiltered view.
$sexChartLabels = [];
$sexChartData = [];
$sexChartHasData = false;
$ageChartLabels = [];
$ageChartPercentages = [];
$ageChartCounts = [];
$purokChartLabels = [];
$purokChartData = [];

if (!$isPreview) {
    $demoTotalStatement = $db->prepare("SELECT COUNT(*) FROM residents r LEFT JOIN resident_profile rp ON rp.resident_id = r.id $whereSql");
    $demoTotalStatement->execute($params);
    $demoTotal = (int) $demoTotalStatement->fetchColumn();

    $sexCounts = ['male' => 0, 'female' => 0, 'not_set' => 0];
    $sexStatement = $db->prepare(
        "SELECT COALESCE(rp.sex, 'not_set') AS sex, COUNT(*) AS total
         FROM residents r LEFT JOIN resident_profile rp ON rp.resident_id = r.id
         $whereSql GROUP BY sex"
    );
    $sexStatement->execute($params);
    foreach ($sexStatement->fetchAll() as $row) {
        $key = in_array($row['sex'], ['male', 'female'], true) ? $row['sex'] : 'not_set';
        $sexCounts[$key] += (int) $row['total'];
    }
    foreach (['male' => 'Male', 'female' => 'Female'] as $key => $label) {
        if ($sexCounts[$key] > 0) {
            $sexChartLabels[] = $label;
            $sexChartData[] = $sexCounts[$key];
        }
    }
    $sexChartHasData = $sexChartData !== [];

    $ageBrackets = [
        '0-17' => [0, 17], '18-30' => [18, 30], '31-45' => [31, 45],
        '46-60' => [46, 60], '61+' => [61, 200],
    ];
    $ageBucketCounts = array_fill_keys(array_keys($ageBrackets), 0);
    $birthdaysStatement = $db->prepare(
        "SELECT rp.birthday FROM residents r LEFT JOIN resident_profile rp ON rp.resident_id = r.id $whereSql"
    );
    $birthdaysStatement->execute($params);
    foreach ($birthdaysStatement->fetchAll() as $b) {
        if (empty($b['birthday'])) continue;
        $bracketAge = (int) (new DateTime($b['birthday']))->diff(new DateTime())->y;
        foreach ($ageBrackets as $bracketLabel => [$min, $max]) {
            if ($bracketAge >= $min && $bracketAge <= $max) {
                $ageBucketCounts[$bracketLabel]++;
                break;
            }
        }
    }
    $ageChartLabels = array_keys($ageBucketCounts);
    $ageChartCounts = array_values($ageBucketCounts);
    $ageDenominator = $demoTotal > 0 ? $demoTotal : 1;
    $ageChartPercentages = array_map(
        static fn(int $count): float => round($count / $ageDenominator * 100, 1),
        $ageChartCounts
    );

    $purokCountsStatement = $db->prepare(
        "SELECT COALESCE(NULLIF(r.purok, ''), 'Not set') AS purok, COUNT(*) AS total
         FROM residents r LEFT JOIN resident_profile rp ON rp.resident_id = r.id
         $whereSql GROUP BY purok ORDER BY purok"
    );
    $purokCountsStatement->execute($params);
    $purokChartRows = $purokCountsStatement->fetchAll();
    $purokChartLabels = array_column($purokChartRows, 'purok');
    $purokChartData = array_map('intval', array_column($purokChartRows, 'total'));
}

$selectedMember = null;
$memberProfile = null;
$memberSpouse = null;
$memberParents = null;
$memberChildren = [];
$memberReferences = [];
$memberAge = null;

if ($selectedResidentId > 0) {
    foreach ($members as $m) {
        if ((int) $m['id'] === $selectedResidentId) {
            $selectedMember = $m;
            break;
        }
    }
}
if ($isPreview && $selectedMember === null) {
    redirect('member_reports.php');
}

if ($selectedMember !== null) {
    $memberAge = $selectedMember['birthday'] ? (int) (new DateTime($selectedMember['birthday']))->diff(new DateTime())->y : null;

    $profileStatement = $db->prepare('SELECT * FROM resident_profile WHERE resident_id = ? LIMIT 1');
    $profileStatement->execute([$selectedResidentId]);
    $memberProfile = $profileStatement->fetch() ?: null;

    $spouseStatement = $db->prepare('SELECT * FROM resident_spouse WHERE resident_id = ? LIMIT 1');
    $spouseStatement->execute([$selectedResidentId]);
    $memberSpouse = $spouseStatement->fetch() ?: null;

    $parentsStatement = $db->prepare('SELECT * FROM resident_parents WHERE resident_id = ? LIMIT 1');
    $parentsStatement->execute([$selectedResidentId]);
    $memberParents = $parentsStatement->fetch() ?: null;

    $childrenStatement = $db->prepare('SELECT child_name, age FROM resident_children WHERE resident_id = ? ORDER BY id');
    $childrenStatement->execute([$selectedResidentId]);
    $memberChildren = $childrenStatement->fetchAll();

    $referencesStatement = $db->prepare('SELECT reference_name, signature_path FROM resident_references WHERE resident_id = ? ORDER BY id');
    $referencesStatement->execute([$selectedResidentId]);
    $memberReferences = $referencesStatement->fetchAll();
}

$success = !$isPreview ? flash('member_success') : null;
$error = !$isPreview ? flash('member_error') : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Member Reports</title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=15">
<link rel="stylesheet" href="../css/logout.css">
<link rel="stylesheet" href="../../assets/css/admin-management.css">
<link rel="stylesheet" href="../css/results.css?v=2">
<link rel="stylesheet" href="../css/surveys.css?v=8">
<link rel="stylesheet" href="../css/confirm-modal.css?v=3">
<style>
.empty-report{text-align:center;color:var(--muted);padding:26px}
/* Report header actions: force a visible white button surface in every interaction state. */
.report-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.report-toolbar .report-action,
.report-toolbar .report-action:hover,
.report-toolbar .report-action:focus,
.report-toolbar .report-action:focus-visible,
.report-toolbar .report-action:active,
.report-toolbar .export-menu > .report-action,
.report-toolbar .export-menu > .report-action:hover,
.report-toolbar .export-menu > .report-action:focus,
.report-toolbar .export-menu > .report-action:focus-visible,
.report-toolbar .export-menu > .report-action:active,
.report-toolbar .export-menu[open] > .report-action{
    background:#fff !important;
    background-color:#fff !important;
    background-image:none !important;
    color:var(--gov-blue-dark) !important;
    border:0 !important;
    box-shadow:none !important;
    opacity:1 !important;
    -webkit-appearance:none;
    appearance:none;
}
.report-toolbar .report-action:hover,
.report-toolbar .export-menu > .report-action:hover,
.report-toolbar .export-menu[open] > .report-action{
    box-shadow:0 0 0 1px #e3e6f4 inset !important;
}
.report-toolbar .report-action:active,
.report-toolbar .export-menu > .report-action:active{
    transform:scale(.98);
}
.export-menu{position:relative}
.export-menu summary{list-style:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer}
.export-menu summary::-webkit-details-marker{display:none}
.export-menu summary::after{content:'▾';margin-left:2px}
.export-options{background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 20px rgba(25,35,70,.14);display:grid;gap:2px;min-width:210px;padding:6px;position:absolute;right:0;top:calc(100% + 6px);z-index:5}
.export-options a{border-radius:6px;color:var(--gov-blue-dark);font-size:.85rem;font-weight:700;padding:9px 10px;text-decoration:none}
.export-options a:hover{background:#eef0fb}
.report-document{max-width:1040px;margin:22px auto 0;background:#fff;border:1px solid var(--border);box-shadow:0 8px 28px rgba(25,35,70,.08);padding:28px}.document-head{background:#f7f8fe;border:1px solid var(--border);border-top:4px solid #293d9e;border-radius:12px;margin-bottom:22px;padding:22px}.document-kicker{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#526ddc;font-weight:800}.document-title{font-size:1.6rem;line-height:1.25;margin:7px 0;overflow-wrap:anywhere}.document-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:18px}.meta-box{background:#fff;border:1px solid var(--border);border-radius:8px;min-width:0;padding:12px}.meta-label{font-size:.68rem;text-transform:uppercase;color:var(--muted);font-weight:700}.meta-value{font-weight:700;line-height:1.4;margin-top:4px;overflow-wrap:anywhere}.report-section{margin-top:24px}.report-section h2{font-size:1.05rem;margin:0;padding:0}.question-block{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 3px 12px rgba(25,35,70,.04);break-inside:avoid;margin-top:14px;padding:18px}.table-scroll{width:100%;overflow-x:auto}.report-table{table-layout:fixed;width:100%;border-collapse:collapse}.report-table th,.report-table td{border:1px solid #d9deea;line-height:1.45;overflow-wrap:anywhere;word-break:break-word;padding:9px 11px;text-align:left;vertical-align:top;font-size:.82rem}.report-table th{background:#f3f5fa;font-size:.72rem;text-transform:uppercase;white-space:normal}.text-right{text-align:right!important}.document-footer{border-top:1px solid var(--border);margin-top:22px;padding-top:12px;color:var(--muted);font-size:.72rem}.action-link{white-space:nowrap}
@media(max-width:760px){.report-document{padding:16px}.document-head{padding:18px}.document-meta{grid-template-columns:1fr}.question-block{padding:15px}.report-table{min-width:480px}}
@media(max-width:600px){.report-pagination{flex-wrap:wrap}}
@media screen and (min-width:1001px){.responsive-table{background:#fff;table-layout:fixed}.responsive-table thead{display:table-header-group}.responsive-table tbody{display:table-row-group}.responsive-table tr{display:table-row}.responsive-table td{display:table-cell;overflow-wrap:anywhere;padding:15px;vertical-align:middle;word-break:break-word}.responsive-table th{display:table-cell;padding:15px;text-align:left;vertical-align:middle}.responsive-table th:nth-child(1),.responsive-table td:nth-child(1){text-align:center;width:5%}.responsive-table th:nth-child(9),.responsive-table td:nth-child(9){text-align:center;width:10%}}
@media screen and (max-width:1000px){.responsive-table{background:transparent;border:0}.responsive-table,.responsive-table tbody,.responsive-table tr,.responsive-table td{display:block;width:100%}.responsive-table thead{display:none}.responsive-table tbody{display:grid;gap:14px}.responsive-table tr{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 3px 10px rgba(25,35,70,.05);padding:12px 8px}.responsive-table td{border:0;overflow-wrap:anywhere;padding:7px 16px}.responsive-table td:before{color:#7c8798;content:attr(data-label);display:block;font-size:.72rem;margin-bottom:3px;text-transform:uppercase}.responsive-table td[data-label="Select"]{padding-bottom:2px}.responsive-table td[data-label="Select"]:before{display:none}.responsive-table td[data-label="Actions"]{border-top:1px solid var(--border);margin-top:8px;padding-top:14px}}
.bulk-export-toolbar{align-items:center;background:#f7f8fe;border:1px solid var(--border);border-radius:10px;display:flex;flex-wrap:wrap;gap:10px;margin:16px 0;padding:12px}.bulk-export-toolbar span{color:var(--muted);font-size:.85rem;font-weight:700;margin-right:auto}.bulk-export-toolbar .btn{padding:9px 14px}.bulk-export-toolbar .btn:disabled{opacity:.55}.select-member{height:18px;width:18px;accent-color:var(--gov-blue-dark);cursor:pointer}.report-pagination{align-items:center;display:flex;gap:12px;justify-content:space-between;margin-top:18px}.report-pagination .btn[aria-disabled="true"]{opacity:.5;pointer-events:none}
.icon-action svg{height:18px;width:18px;fill:none;stroke:currentColor;stroke-width:2}
@media print{
  .sidebar,.sidebar-backdrop,.topbar,.member-filters,.report-index,.menu-toggle{display:none!important}
  .portal-shell{display:block}
  .main{margin:0!important;padding:0!important}
  .report-document{display:block!important;max-width:none;margin:0;border:0;box-shadow:none;padding:0;font-size:11px}
  .document-head{margin-bottom:8px;padding:8px 12px;border-top-width:2px}
  .document-kicker{font-size:.58rem}
  .document-title{font-size:1.05rem;margin:3px 0}
  .document-meta{margin-top:6px;gap:6px}
  .meta-box{padding:5px 8px}
  .meta-label{font-size:.55rem}
  .meta-value{font-size:.74rem;margin-top:2px}
  .report-section{margin-top:8px}
  .report-section h2{font-size:.8rem}
  .question-block{margin-top:4px;padding:6px 8px;box-shadow:none;page-break-inside:avoid}
  .report-table th,.report-table td{padding:3px 6px;font-size:.66rem;line-height:1.2}
  .report-table th{font-size:.58rem}
  .empty-report{padding:8px}
  .document-footer{margin-top:8px;padding-top:5px;font-size:.58rem}
  @page{margin:9mm}
}
</style>
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
        <a class="nav-link" href="members.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a><a class="nav-link" href="archive.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a>
        <a class="nav-link" href="results.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="20" x2="4" y2="10"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="13"/><line x1="22" y1="20" x2="22" y2="7"/></svg></span>Results Dashboard</a>
        <div class="nav-group-item open">
            <button type="button" class="nav-link nav-parent" aria-expanded="true"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>
                <span>Reports</span>
                <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="nav-submenu">
                <a class="nav-link nav-sublink" href="reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Reports</a>
                <a class="nav-link nav-sublink" href="member_reports.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Reports</a>
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
            <button class="menu-toggle" aria-label="Toggle menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            <div class="page-eyebrow"><?= $isPreview ? 'Member report preview' : 'Reports' ?></div>
            <h1 class="page-title"><?= $isPreview ? h($selectedMember['head_name'] ?? 'Report Preview') : 'Member Reports' ?></h1>
            <p class="page-sub"><?= $isPreview ? 'Review this member report, then export it or print it.' : 'Filter registered residents, then select a report to preview.' ?></p>
        </div>
        <?php if ($selectedMember !== null): ?>
        <div class="report-toolbar">
            <a class="btn btn-light report-action" href="member_reports.php">Back to Report List</a>
            <details class="export-menu">
                <summary class="btn btn-light report-action">Export</summary>
                <div class="export-options">
                    <a href="../../process/export_member_report.php?resident_id=<?= $selectedResidentId ?>&amp;format=xlsx">Excel (.xlsx)</a>
                    <a href="../../process/export_member_report.php?resident_id=<?= $selectedResidentId ?>&amp;format=pdf">PDF</a>
                </div>
            </details>
            <a class="btn btn-primary" href="resident_print.php?id=<?= (int) $selectedResidentId ?>" target="_blank" rel="noopener">Print Report</a>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($success): ?><p class="notice notice-success" role="status"><?= h($success) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="notice notice-error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <?php if (!$isPreview): ?>
    <form class="survey-tools member-filters" method="get" role="search" data-live-filter>
        <label class="tool-search" for="member-report-search">Search members
            <input id="member-report-search" type="search" name="q" value="<?= h($search) ?>" placeholder="Search by name, account no., household no., contact, or address" autocomplete="off" data-live-search>
        </label>
        <label class="tool-filter" for="member-report-status">Status
            <select id="member-report-status" name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Deactivated</option>
            </select>
        </label>
        <label class="tool-filter" for="member-report-purok">Purok
            <select id="member-report-purok" name="purok">
                <option value="all" <?= $purokFilter === 'all' ? 'selected' : '' ?>>All puroks</option>
                <?php foreach ($purokOptions as $purokValue): ?>
                <option value="<?= h($purokValue) ?>" <?= $purokFilter === $purokValue ? 'selected' : '' ?>><?= h($purokValue) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="tool-filter" for="member-report-civil-status">Civil Status
            <select id="member-report-civil-status" name="civil_status">
                <option value="all" <?= $civilStatusFilter === 'all' ? 'selected' : '' ?>>All civil statuses</option>
                <?php foreach ($civilStatusOptions as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= $civilStatusFilter === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($isFiltered): ?><a class="btn btn-outline clear-filters" href="member_reports.php">Clear filters</a><?php endif; ?>
    </form>
    <?php endif; ?>
    </div>

    <?php if (!$isPreview): ?>
    <div data-live-results aria-live="polite">
    <section class="card">
        <div class="card-title-row"><div><div class="card-title">Member Demographics</div><div class="card-desc"><?= $isFiltered ? 'Sex, age, and purok breakdown of the ' . $filteredCount . ' matching resident' . ($filteredCount === 1 ? '' : 's') . '.' : 'Sex, age, and purok breakdown of all registered members.' ?></div></div></div>
        <div class="dash-charts-grid">
            <div class="chart-panel">
                <div class="chart-title">Sex Distribution</div>
                <?php if ($sexChartHasData): ?>
                <div class="chart-canvas-wrap" style="height:240px">
                    <canvas id="chart-sex" role="img" aria-label="Sex distribution of matching members"></canvas>
                </div>
                <?php else: ?>
                <p class="chart-empty">No matching members have a recorded sex yet.</p>
                <?php endif; ?>
            </div>
            <div class="chart-panel">
                <div class="chart-title">Age Distribution</div>
                <div class="chart-canvas-wrap" style="height:240px">
                    <canvas id="chart-age" role="img" aria-label="Age distribution of matching members"></canvas>
                </div>
            </div>
            <div class="chart-panel">
                <div class="chart-title">Population per Purok</div>
                <div class="chart-canvas-wrap" style="height:240px">
                    <canvas id="chart-purok" role="img" aria-label="Population per purok"></canvas>
                </div>
            </div>
        </div>
        <script type="application/json" id="demographicsData"><?= json_encode([
            'sexLabels' => $sexChartLabels,
            'sexData' => $sexChartData,
            'ageLabels' => $ageChartLabels,
            'agePercentages' => $ageChartPercentages,
            'ageCounts' => $ageChartCounts,
            'purokLabels' => $purokChartLabels,
            'purokData' => $purokChartData,
        ], JSON_UNESCAPED_SLASHES) ?></script>
    </section>

    <section class="card report-index">
        <div class="card-title">Available Member Reports</div>
        <div class="card-desc">Report records are generated from the registered resident directory.</div>
        <div class="bulk-export-scope" data-bulk-export>
        <div class="bulk-export-toolbar">
            <span data-selection-count>0 members selected</span>
            <button class="btn btn-outline" type="button" data-bulk-export-submit="xlsx" disabled>Export Selected Excel</button>
            <button class="btn btn-primary" type="button" data-bulk-export-submit="pdf" disabled>Export Selected PDF</button>
        </div>
        <table class="responsive-table">
            <thead><tr><th><input class="select-member" type="checkbox" aria-label="Select all members" data-select-all></th><th>Account No.</th><th>Household No.</th><th>Head of Household</th><th>Purok</th><th>Civil Status</th><th>Age</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <?php $age = $m['birthday'] ? (new DateTime($m['birthday']))->diff(new DateTime())->y : null; ?>
                <tr>
                    <td data-label="Select"><input class="select-member" type="checkbox" name="resident_id[]" value="<?= (int) $m['id'] ?>" aria-label="Select <?= h($m['head_name']) ?>" data-member-select></td>
                    <td data-label="Account No."><?= h($m['resident_number']) ?></td>
                    <td data-label="Household No."><?= h($m['household_number']) ?></td>
                        <td data-label="Head of Household"><?= h($m['head_name']) ?></td>
                    <td data-label="Purok"><?= h($m['purok'] ?: '—') ?></td>
                    <td data-label="Civil Status"><?= $m['civil_status'] ? h(ucfirst($m['civil_status'])) : '—' ?></td>
                    <td data-label="Age"><?= $age !== null ? $age : '—' ?></td>
                    <td data-label="Status"><span class="status-pill <?= $m['is_active'] ? 'is-active' : 'is-inactive' ?>"><?= $m['is_active'] ? 'Active' : 'Deactivated' ?></span></td>
                    <td data-label="Actions">
                        <div class="survey-card-actions" style="justify-content:center">
                            <a class="btn btn-outline btn-sm icon-action" href="member_report_preview.php?resident_id=<?= (int) $m['id'] ?>" aria-label="Preview report" data-tooltip="Preview Report"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                            <?php
                                $archiveConfirm = json_encode([
                                    'title' => 'Move this resident record to the archive?',
                                    'description' => $m['head_name'] . '\'s record will be moved to the Archive and their account deactivated. You can restore it or delete it permanently from the Archive.',
                                    'confirmLabel' => 'Yes, move to archive',
                                    'variant' => 'danger',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>
                            <form action="../../process/admin_archive_resident.php" method="post" data-confirm-modal='<?= $archiveConfirm ?>'>
                                <input type="hidden" name="resident_id" value="<?= (int) $m['id'] ?>">
                                <input type="hidden" name="return_to" value="member_reports.php">
                                <button class="btn btn-danger btn-sm icon-action" type="submit" aria-label="Archive resident record" data-tooltip="Move to archive"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v4H3z"/><path d="M5 9v10h14V9"/><path d="M10 13h4"/></svg></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$members): ?><tr><td colspan="9" class="empty-report"><?= $isFiltered ? 'No members match your search or filter.' : 'No residents have been registered yet.' ?></td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php if ($filteredCount > 0): ?>
            <nav class="report-pagination" aria-label="Report pages">
                <?php if ($page > 1): ?><a class="btn btn-outline" href="<?= h($memberReportPageUrl($page - 1)) ?>">Previous</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Previous</span><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pageCount ?></span>
                <?php if ($page < $pageCount): ?><a class="btn btn-outline" href="<?= h($memberReportPageUrl($page + 1)) ?>">Next</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Next</span><?php endif; ?>
            </nav>
        <?php endif; ?>
        </div>
    </section>
    </div>
    <?php endif; ?>

    <?php if ($selectedMember !== null): ?>
    <?php
        $fullName = trim(($memberProfile['first_name'] ?? '') . ' ' . ($memberProfile['middle_name'] ?? '') . ' ' . ($memberProfile['last_name'] ?? '') . ' ' . ($memberProfile['extension_name'] ?? ''));
        $fullName = $fullName !== '' ? preg_replace('/\s+/', ' ', $fullName) : $selectedMember['head_name'];
    ?>
    <article class="report-document">
        <header class="document-head">
            <div class="document-kicker">Barangay Longos Survey System</div>
            <h1 class="document-title"><?= h($selectedMember['head_name']) ?></h1>
            <div>Member Profile Report</div>
            <div class="document-meta">
                <div class="meta-box"><div class="meta-label">Account No.</div><div class="meta-value"><?= h($selectedMember['resident_number']) ?></div></div>
                <div class="meta-box"><div class="meta-label">Purok</div><div class="meta-value"><?= h($selectedMember['purok'] ?: '') ?></div></div>
                <div class="meta-box"><div class="meta-label">Status</div><div class="meta-value"><?= $selectedMember['is_active'] ? 'Active' : 'Deactivated' ?></div></div>
            </div>
        </header>

        <section class="report-section">
            <h2>Household &amp; Contact Information</h2>
            <div class="question-block">
                <div class="table-scroll">
                <table class="report-table">
                    <colgroup><col style="width:220px"><col></colgroup>
                    <tbody>
                        <tr><td>Household No.</td><td><?= h($selectedMember['household_number']) ?></td></tr>
                        <tr><td>Contact Number</td><td><?= h($selectedMember['contact_number']) ?></td></tr>
                        <tr><td>Address</td><td><?= nl2br(h($selectedMember['address'])) ?></td></tr>
                        <tr><td>Purok</td><td><?= h($selectedMember['purok'] ?: '') ?></td></tr>
                        <tr><td>Registered</td><td><?= h(date('M j, Y', strtotime($selectedMember['created_at']))) ?></td></tr>
                    </tbody>
                </table>
                </div>
            </div>
        </section>

        <section class="report-section">
            <h2>Personal Profile</h2>
            <div class="question-block">
                <div class="table-scroll">
                <table class="report-table">
                    <colgroup><col style="width:220px"><col></colgroup>
                    <tbody>
                        <tr><td>Full Name</td><td><?= h($fullName) ?></td></tr>
                        <tr><td>Sex</td><td><?= !empty($memberProfile['sex']) ? h(ucfirst($memberProfile['sex'])) : '' ?></td></tr>
                        <tr><td>Civil Status</td><td><?= !empty($memberProfile['civil_status']) ? h(ucfirst($memberProfile['civil_status'])) : '' ?></td></tr>
                        <tr><td>Birth of Date</td><td><?= !empty($memberProfile['birthday']) ? h(date('M j, Y', strtotime($memberProfile['birthday']))) : '' ?></td></tr>
                        <tr><td>Age</td><td><?= $memberAge !== null ? $memberAge : '' ?></td></tr>
                        <tr><td>Occupation</td><td><?= !empty($memberProfile['occupation']) ? h($memberProfile['occupation']) : '' ?></td></tr>
                        <tr><td>Employer</td><td><?= !empty($memberProfile['employer']) ? h($memberProfile['employer']) : '' ?></td></tr>
                        <tr><td>Employer Address</td><td><?= !empty($memberProfile['employer_address']) ? h($memberProfile['employer_address']) : '' ?></td></tr>
                    </tbody>
                </table>
                </div>
                <?php if ($memberProfile === null): ?>
                    <p class="empty-report" style="padding-top:10px">This member has not completed their personal profile yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="report-section">
            <h2>Family Information</h2>
            <div class="question-block">
                <?php if ($memberSpouse === null && $memberParents === null && !$memberChildren): ?>
                    <p class="empty-report">No family information on file.</p>
                <?php else: ?>
                <div class="table-scroll">
                <table class="report-table">
                    <colgroup><col style="width:220px"><col></colgroup>
                    <tbody>
                        <?php if ($memberSpouse !== null): ?>
                        <tr><td>Spouse</td><td><?= h($memberSpouse['spouse_name']) ?><?= $memberSpouse['occupation'] ? ' — ' . h($memberSpouse['occupation']) : '' ?></td></tr>
                        <?php endif; ?>
                        <?php if ($memberParents !== null): ?>
                        <tr><td>Father's Name</td><td><?= h($memberParents['father_name'] ?: '') ?></td></tr>
                        <tr><td>Mother's Name</td><td><?= h($memberParents['mother_name'] ?: '') ?></td></tr>
                        <?php endif; ?>
                        <?php if ($memberChildren): ?>
                        <tr><td>Children</td><td><?= implode(', ', array_map(static fn($c) => h($c['child_name']) . ($c['age'] !== null ? ' (' . (int) $c['age'] . ')' : ''), $memberChildren)) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="report-section">
            <h2>Character References</h2>
            <div class="question-block">
                <?php if (!$memberReferences): ?>
                    <p class="empty-report">No references on file.</p>
                <?php else: ?>
                <div class="table-scroll">
                <table class="report-table">
                    <colgroup><col></colgroup>
                    <thead><tr><th>Reference Name</th></tr></thead>
                    <tbody>
                    <?php foreach ($memberReferences as $ref): ?>
                        <tr><td><?= h($ref['reference_name']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <footer class="document-footer">Generated <?= h(date('F j, Y, g:i A')) ?> from the barangay resident registry.</footer>
    </article>
    <?php endif; ?>
</main>
</div>
<?php if (!$isPreview): ?><script src="../../assets/js/chart.umd.min.js"></script><script src="../js/live-filter.js?v=2"></script><script src="../js/bulk-export.js?v=3"></script><?php endif; ?>
<script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script>
<script src="../js/confirm-modal.js?v=3"></script>
<?php if (!$isPreview): ?>
<script>
(function () {
    if (!window.Chart) return;
    var charts = {};

    function destroy(key) {
        if (charts[key]) { charts[key].destroy(); charts[key] = null; }
    }

    function renderCharts() {
        var dataEl = document.getElementById('demographicsData');
        if (!dataEl) return;
        var data;
        try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }

        destroy('sex'); destroy('age'); destroy('purok');

        var sexCanvas = document.getElementById('chart-sex');
        if (sexCanvas) {
            charts.sex = new Chart(sexCanvas, {
                type: 'doughnut',
                data: {
                    labels: data.sexLabels,
                    datasets: [{
                        data: data.sexData,
                        backgroundColor: data.sexLabels.map(function (label) {
                            return label === 'Female' ? '#e0729a' : '#3d56d6';
                        }),
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { size: 11 },
                                boxWidth: 12,
                                generateLabels: function (chart) {
                                    var values = chart.data.datasets[0].data;
                                    var total = values.reduce(function (sum, value) { return sum + Number(value || 0); }, 0) || 1;
                                    var meta = chart.getDatasetMeta(0);
                                    return chart.data.labels.map(function (label, index) {
                                        var percentage = Math.round((Number(values[index] || 0) / total) * 1000) / 10;
                                        var style = meta.controller.getStyle(index);
                                        return {
                                            text: label + ' (' + percentage + '%)',
                                            fillStyle: style.backgroundColor,
                                            strokeStyle: style.borderColor,
                                            lineWidth: style.borderWidth,
                                            hidden: !chart.getDataVisibility(index),
                                            index: index
                                        };
                                    });
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0) || 1;
                                    var pct = Math.round((ctx.parsed / total) * 100);
                                    return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        var ageCanvas = document.getElementById('chart-age');
        if (ageCanvas) {
            charts.age = new Chart(ageCanvas, {
                type: 'bar',
                data: {
                    labels: data.ageLabels,
                    datasets: [{
                        label: 'Members',
                        data: data.agePercentages,
                        backgroundColor: '#3d56d6',
                        borderRadius: 5,
                        maxBarThickness: 56
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var idx = ctx.dataIndex;
                                    var count = data.ageCounts[idx];
                                    return count + ' member' + (count === 1 ? '' : 's') + ' (' + ctx.parsed.y + '%)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                font: { size: 11 },
                                callback: function (value) { return value + '%'; }
                            }
                        },
                        x: { ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        var purokCanvas = document.getElementById('chart-purok');
        if (purokCanvas) {
            charts.purok = new Chart(purokCanvas, {
                type: 'bar',
                data: {
                    labels: data.purokLabels,
                    datasets: [{
                        label: 'Members',
                        data: data.purokData,
                        backgroundColor: '#1f9d67',
                        borderRadius: 5,
                        maxBarThickness: 56
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.parsed.y + ' member' + (ctx.parsed.y === 1 ? '' : 's');
                                }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } } },
                        x: { ticks: { font: { size: 11 }, maxRotation: 40 } }
                    }
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', renderCharts);
    var results = document.querySelector('[data-live-results]');
    results?.addEventListener('report-results:updated', renderCharts);
}());
</script>
<?php endif; ?>
</body>
</html>