<?php
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
$db = database();
// Keep is_active truthful across the whole admin portal — a survey past its
// own closing date is closed, whether or not anyone clicked Deactivate.
$db->exec("UPDATE surveys SET is_active = 0 WHERE is_active = 1 AND closes_at <= CURDATE()");
$totalSurveys = (int) $db->query('SELECT COUNT(*) FROM surveys')->fetchColumn();
$activeSurveys = (int) $db->query('SELECT COUNT(*) FROM surveys WHERE is_active = 1 AND CURDATE() >= opens_at AND CURDATE() < closes_at')->fetchColumn();
$totalResponses = (int) $db->query('SELECT COUNT(*) FROM survey_submissions')->fetchColumn();
$totalMembers = (int) $db->query('SELECT COUNT(*) FROM residents WHERE archived_at IS NULL')->fetchColumn();
$activitiesPerPage = 10;
$activityActor = trim((string) ($_GET['activity_actor'] ?? ''));
$activityAction = (string) ($_GET['activity_action'] ?? '');
$activityDate = trim((string) ($_GET['activity_date'] ?? ''));
$activityActions = ['Created', 'Updated', 'Archived', 'Deleted', 'Submitted'];
$activityAction = in_array($activityAction, $activityActions, true) ? $activityAction : '';
$activityDateObject = DateTime::createFromFormat('!Y-m-d', $activityDate);
if (!$activityDateObject || $activityDateObject->format('Y-m-d') !== $activityDate) {
    $activityDate = '';
}
$activityActors = $db->query(
    'SELECT DISTINCT actor_type, actor_id, actor_name, actor_role
     FROM activity_log ORDER BY actor_name ASC, actor_role ASC'
)->fetchAll();
$activityWhere = [];
$activityParams = [];
if ($activityActor !== '') {
    [$actorType, $actorId] = array_pad(explode(':', $activityActor, 2), 2, '');
    if (in_array($actorType, ['resident', 'staff'], true) && ctype_digit($actorId) && (int) $actorId > 0) {
        $activityWhere[] = 'actor_type = ? AND actor_id = ?';
        $activityParams[] = $actorType;
        $activityParams[] = (int) $actorId;
    } else {
        $activityActor = '';
    }
}
if ($activityAction !== '') {
    $activityWhere[] = 'action = ?';
    $activityParams[] = $activityAction;
}
if ($activityDate !== '') {
    $activityWhere[] = 'DATE(created_at) = ?';
    $activityParams[] = $activityDate;
}
$activityWhereSql = $activityWhere ? ' WHERE ' . implode(' AND ', $activityWhere) : '';
$activityCountStatement = $db->prepare('SELECT COUNT(*) FROM activity_log' . $activityWhereSql);
$activityCountStatement->execute($activityParams);
$activityCount = (int) $activityCountStatement->fetchColumn();
$activityPageCount = max(1, (int) ceil($activityCount / $activitiesPerPage));
$activityPage = min(max(1, (int) ($_GET['activity_page'] ?? 1)), $activityPageCount);
$activityOffset = ($activityPage - 1) * $activitiesPerPage;
$recentActivitiesStatement = $db->prepare(
    'SELECT action, entity_type, entity_name, actor_name, actor_role, created_at
     FROM activity_log' . $activityWhereSql . ' ORDER BY created_at DESC, id DESC
     LIMIT ' . $activitiesPerPage . ' OFFSET ' . $activityOffset
);
$recentActivitiesStatement->execute($activityParams);
$recentActivities = $recentActivitiesStatement->fetchAll();
$activityPageUrl = static function (int $page) use ($activityActor, $activityAction, $activityDate): string {
    $params = ['activity_page' => $page];
    if ($activityActor !== '') $params['activity_actor'] = $activityActor;
    if ($activityAction !== '') $params['activity_action'] = $activityAction;
    if ($activityDate !== '') $params['activity_date'] = $activityDate;
    return 'dashboard.php?' . http_build_query($params);
};

// --- Demographics charts: Sex Distribution, Age Distribution, Population per Purok ---
$demoTotalResidents = (int) $db->query('SELECT COUNT(*) FROM residents WHERE archived_at IS NULL')->fetchColumn();

// Sex distribution excludes members without a recorded sex from the graph.
$sexCounts = ['male' => 0, 'female' => 0, 'not_set' => 0];
$sexStatement = $db->query(
    "SELECT COALESCE(rp.sex, 'not_set') AS sex, COUNT(*) AS total
     FROM residents r LEFT JOIN resident_profile rp ON rp.resident_id = r.id
     WHERE r.archived_at IS NULL GROUP BY sex"
);
foreach ($sexStatement->fetchAll() as $row) {
    $key = in_array($row['sex'], ['male', 'female'], true) ? $row['sex'] : 'not_set';
    $sexCounts[$key] += (int) $row['total'];
}
$sexChartLabels = [];
$sexChartData = [];
foreach (['male' => 'Male', 'female' => 'Female'] as $key => $label) {
    if ($sexCounts[$key] > 0) {
        $sexChartLabels[] = $label;
        $sexChartData[] = $sexCounts[$key];
    }
}
$sexChartHasData = $sexChartData !== [];

// Age distribution, expressed as a percentage of all active members.
$ageBrackets = [
    '0-17' => [0, 17], '18-30' => [18, 30], '31-45' => [31, 45],
    '46-60' => [46, 60], '61+' => [61, 200],
];
$ageBucketCounts = array_fill_keys(array_keys($ageBrackets), 0);
$ageNotSetCount = 0;
$birthdaysStatement = $db->query(
    "SELECT rp.birthday FROM residents r LEFT JOIN resident_profile rp ON rp.resident_id = r.id
     WHERE r.archived_at IS NULL"
);
foreach ($birthdaysStatement->fetchAll() as $b) {
    if (empty($b['birthday'])) {
        $ageNotSetCount++;
        continue;
    }
    $age = (int) (new DateTime($b['birthday']))->diff(new DateTime())->y;
    foreach ($ageBrackets as $label => [$min, $max]) {
        if ($age >= $min && $age <= $max) {
            $ageBucketCounts[$label]++;
            break;
        }
    }
}
$ageChartLabels = array_keys($ageBucketCounts);
$ageChartCounts = array_values($ageBucketCounts);
$ageDenominator = $demoTotalResidents > 0 ? $demoTotalResidents : 1;
$ageChartPercentages = array_map(
    static fn(int $count): float => round($count / $ageDenominator * 100, 1),
    $ageChartCounts
);

// Population per purok.
$purokCountsStatement = $db->query(
    "SELECT COALESCE(NULLIF(purok, ''), 'Not set') AS purok, COUNT(*) AS total
     FROM residents WHERE archived_at IS NULL GROUP BY purok ORDER BY purok"
);
$purokChartRows = $purokCountsStatement->fetchAll();
$purokChartLabels = array_column($purokChartRows, 'purok');
$purokChartData = array_map('intval', array_column($purokChartRows, 'total'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administrator Dashboard - Barangay Health Center Resident Profiling & Survey Management System</title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=18">
<link rel="stylesheet" href="../css/logout.css">
<link rel="stylesheet" href="../../assets/css/admin-management.css?v=3">
<link rel="stylesheet" href="../css/results.css?v=2">
</head>
<body>
<div class="portal-shell">
    <div class="sidebar-backdrop"></div>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div>
            <div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div>
        </div>
        <div class="id-card" data-navigate-href="admin_profile.php">
            <div class="id-eyebrow"><?= $admin['role'] === 'admin' ? 'Administrator Account' : 'Staff Account' ?></div>
            <div class="id-card-row">
                <div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div>
                <div class="id-card-name"><?= h($admin['full_name']) ?><small><?= h(ucfirst($admin['role'])) ?></small></div>
            </div>
            <div class="id-card-perf"></div>
            <div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div>
        </div>
        <nav class="nav-group">
            <span class="nav-label">Management</span>
            <a class="nav-link" href="dashboard.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>Dashboard</a>
            <a class="nav-link" href="surveys.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Management</a>
            <a class="nav-link" href="members.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a><a class="nav-link" href="archive.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a>
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
                <button class="menu-toggle" aria-label="Toggle menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="page-eyebrow">Administrator overview</div>
                <h1 class="page-title">Welcome back, <?= h($admin['full_name']) ?></h1>
                <p class="page-sub">Manage surveys, member accounts, and participation from one place.</p>
    </div>
    </div>
        <div class="banner">
            <div class="banner-text"><strong><?= $activeSurveys ?> active survey<?= $activeSurveys === 1 ? '' : 's' ?> running now</strong><span>Create surveys and review participation activity.</span></div>
            <a href="surveys.php" class="btn btn-light">Manage Surveys</a>
        </div>
        </div>
        <div class="card">
            <div class="card-title-row"><div><div class="card-title">Quick Access</div></div></div>
            <div class="quick-access-grid <?= $admin['role'] === 'admin' ? 'is-admin' : 'is-staff' ?>">
                <a href="surveys.php?new=1" class="quick-access-item">
                    <span class="quick-access-icon qa-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span class="qa-plus-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span></span>
                    <span class="quick-access-label">Add Survey</span>
                </a>
                <a href="members.php?add=1" class="quick-access-item">
                    <span class="quick-access-icon qa-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="qa-plus-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span></span>
                    <span class="quick-access-label">Add Member</span>
                </a>
                <a href="reports.php" class="quick-access-item">
                    <span class="quick-access-icon qa-gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg></span>
                    <span class="quick-access-label">Survey Reports</span>
                </a>
                <a href="member_reports.php" class="quick-access-item">
                    <span class="quick-access-icon qa-red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/></svg></span>
                    <span class="quick-access-label">Member Reports</span>
                </a>
                <?php if ($admin['role'] === 'admin'): ?>
                <a href="users.php?add=1" class="quick-access-item">
                    <span class="quick-access-icon qa-purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/></svg><span class="qa-plus-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span></span>
                    <span class="quick-access-label">Add Staff</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-grid">
            <div class="stat-card active"><div class="stat-label">Active Surveys</div><div class="stat-value"><?= $activeSurveys ?></div><div class="stat-hint">Open for resident responses</div></div>
            <div class="stat-card pending"><div class="stat-label">Total Responses</div><div class="stat-value"><?= $totalResponses ?></div><div class="stat-hint">Submitted survey responses</div></div>
            <div class="stat-card done"><div class="stat-label">Registered Members</div><div class="stat-value"><?= $totalMembers ?></div><div class="stat-hint">Resident accounts in the system</div></div>
        </div>
        <div class="card">
            <div class="card-title-row"><div><div class="card-title">Member Demographics</div><div class="card-desc">Sex, age, and purok breakdown of all registered members.</div></div><a href="member_reports.php" class="btn btn-outline">View Member Reports</a></div>
            <div class="dash-charts-grid">
                <div class="chart-panel">
                    <div class="chart-title">Sex Distribution</div>
                    <?php if ($sexChartHasData): ?>
                    <div class="chart-canvas-wrap" style="height:240px">
                        <canvas id="chart-sex" role="img" aria-label="Sex distribution of registered members"></canvas>
                    </div>
                    <?php else: ?>
                    <p class="chart-empty">No members have a recorded sex yet.</p>
                    <?php endif; ?>
                </div>
                <div class="chart-panel">
                    <div class="chart-title">Age Distribution</div>
                    <div class="chart-canvas-wrap" style="height:240px">
                        <canvas id="chart-age" role="img" aria-label="Age distribution of registered members"></canvas>
                    </div>
                </div>
                <div class="chart-panel">
                    <div class="chart-title">Population per Purok</div>
                    <div class="chart-canvas-wrap" style="height:240px">
                        <canvas id="chart-purok" role="img" aria-label="Population per purok"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-title-row"><div><div class="card-title">Recent Activities</div><div class="card-desc">Recent changes made by administrators, staff, and residents.</div></div></div>
            <form class="activity-filters" method="get" action="dashboard.php" data-activity-filter>
                <label>Changed by
                    <select name="activity_actor">
                        <option value="">Everyone</option>
                        <?php foreach ($activityActors as $actor): ?>
                            <?php $actorValue = $actor['actor_type'] . ':' . (int) $actor['actor_id']; ?>
                            <option value="<?= h($actorValue) ?>" <?= $activityActor === $actorValue ? 'selected' : '' ?>><?= h($actor['actor_name']) ?> (<?= h($actor['actor_role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Change type
                    <select name="activity_action">
                        <option value="">All changes</option>
                        <?php foreach ($activityActions as $action): ?><option value="<?= h($action) ?>" <?= $activityAction === $action ? 'selected' : '' ?>><?= h($action) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Date
                    <input type="date" name="activity_date" value="<?= h($activityDate) ?>">
                </label>
                <?php if ($activityActor !== '' || $activityAction !== '' || $activityDate !== ''): ?><a class="btn btn-outline" href="dashboard.php">Clear</a><?php endif; ?>
            </form>
            <div data-activity-results aria-live="polite">
            <div class="activity-list">
                <?php if (!$recentActivities): ?>
                    <div class="activity-row"><div class="activity-main"><div class="activity-title">No activities yet</div><div class="activity-date">CRUD activity will appear here.</div></div></div>
                <?php endif; ?>
                <?php foreach ($recentActivities as $activity): ?>
                    <?php $activityClass = ['Created' => 'activity-created', 'Updated' => 'activity-updated', 'Archived' => 'activity-archived', 'Deleted' => 'activity-deleted', 'Submitted' => 'activity-submitted'][$activity['action']] ?? 'activity-updated'; ?>
                    <div class="activity-row"><div class="activity-main"><div class="activity-title"><?= h($activity['entity_type'] . ' ' . $activity['action'] . ': ' . $activity['entity_name']) ?></div><div class="activity-date"><?= h($activity['action']) ?> at <?= h(date('F j, Y g:i A', strtotime($activity['created_at']))) ?> by <?= h($activity['actor_name']) ?> (<?= h($activity['actor_role']) ?>)</div></div><span class="badge activity-badge <?= h($activityClass) ?>"><?= h($activity['action']) ?></span></div>
                <?php endforeach; ?>
                <?php if (false): ?>
                <?php if (!$recentSurveys): ?><div class="activity-row"><div class="activity-main"><div class="activity-title">No surveys yet</div><div class="activity-date">Create your first survey from Survey Management.</div></div></div><?php endif; ?>
                <?php foreach ($recentSurveys as $survey): ?><div class="activity-row"><div class="activity-icon">✓</div><div class="activity-main"><div class="activity-title"><?= h($survey['title']) ?></div><div class="activity-date">Open <?= h(date('M j, Y', strtotime($survey['opens_at']))) ?> to <?= h(date('M j, Y', strtotime($survey['closes_at']))) ?></div></div><span class="badge <?= $survey['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $survey['is_active'] ? 'Active' : 'Closed' ?></span></div><?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($activityCount > 0): ?>
            <nav class="management-pagination" aria-label="Recent activity pages">
                <?php if ($activityPage > 1): ?><a class="btn btn-outline" href="<?= h($activityPageUrl($activityPage - 1)) ?>">Previous</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Previous</span><?php endif; ?>
                <span>Page <?= $activityPage ?> of <?= $activityPageCount ?></span>
                <?php if ($activityPage < $activityPageCount): ?><a class="btn btn-outline" href="<?= h($activityPageUrl($activityPage + 1)) ?>">Next</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Next</span><?php endif; ?>
            </nav>
            <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="../../assets/js/chart.umd.min.js"></script>
<script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script>
<script>
(function () {
    if (!window.Chart) return;

    var sexData = <?= json_encode($sexChartData, JSON_UNESCAPED_SLASHES) ?>;
    var sexLabels = <?= json_encode($sexChartLabels, JSON_UNESCAPED_SLASHES) ?>;
    var ageLabels = <?= json_encode($ageChartLabels, JSON_UNESCAPED_SLASHES) ?>;
    var agePercentages = <?= json_encode($ageChartPercentages, JSON_UNESCAPED_SLASHES) ?>;
    var ageCounts = <?= json_encode($ageChartCounts, JSON_UNESCAPED_SLASHES) ?>;
    var purokLabels = <?= json_encode($purokChartLabels, JSON_UNESCAPED_SLASHES) ?>;
    var purokData = <?= json_encode($purokChartData, JSON_UNESCAPED_SLASHES) ?>;

    var sexCanvas = document.getElementById('chart-sex');
    if (sexCanvas) {
        new Chart(sexCanvas, {
            type: 'doughnut',
            data: {
                labels: sexLabels,
                datasets: [{
                    data: sexData,
                    backgroundColor: sexLabels.map(function (label) {
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
        new Chart(ageCanvas, {
            type: 'bar',
            data: {
                labels: ageLabels,
                datasets: [{
                    label: 'Members',
                    data: agePercentages,
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
                                return ageCounts[idx] + ' member' + (ageCounts[idx] === 1 ? '' : 's') + ' (' + ctx.parsed.y + '%)';
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
        new Chart(purokCanvas, {
            type: 'bar',
            data: {
                labels: purokLabels,
                datasets: [{
                    label: 'Members',
                    data: purokData,
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
})();
</script>
</body>
</html>