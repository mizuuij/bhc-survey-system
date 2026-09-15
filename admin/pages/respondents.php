<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/admin_auth.php';

$admin = require_admin();
$surveyId = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT);
if (!$surveyId) {
    redirect('surveys.php');
}

$db = database();
$surveyStatement = $db->prepare('SELECT id, title, opens_at, closes_at, is_active FROM surveys WHERE id = ? LIMIT 1');
$surveyStatement->execute([$surveyId]);
$survey = $surveyStatement->fetch();
if (!$survey) {
    flash('survey_error', 'Survey not found.');
    redirect('surveys.php');
}

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 20;
$countStatement = $db->prepare('SELECT COUNT(*) FROM survey_submissions WHERE survey_id = ?');
$countStatement->execute([$surveyId]);
$respondentCount = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($respondentCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$respondentsStatement = $db->prepare(
    "SELECT r.household_number, r.head_name, r.contact_number, r.address, sub.submitted_at,
            (SELECT COUNT(*) FROM survey_answers a WHERE a.submission_id = sub.id AND a.answer_text IS NOT NULL AND a.answer_text <> '') AS answered_questions
     FROM survey_submissions sub
     INNER JOIN residents r ON r.id = sub.resident_id
     WHERE sub.survey_id = ?
     ORDER BY sub.submitted_at DESC, sub.id DESC
     LIMIT $perPage OFFSET $offset"
);
$respondentsStatement->execute([$surveyId]);
$respondents = $respondentsStatement->fetchAll();

// All active resident households are eligible for every survey. Compare that
// population with actual submissions to expose participation, not just a raw
// response total.
$eligibleStatement = $db->query('SELECT COUNT(*) FROM residents WHERE is_active = 1');
$eligibleHouseholds = (int) $eligibleStatement->fetchColumn();
$participantStatement = $db->prepare(
    'SELECT COUNT(*)
     FROM survey_submissions sub
     INNER JOIN residents r ON r.id = sub.resident_id
     WHERE sub.survey_id = ? AND r.is_active = 1'
);
$participantStatement->execute([$surveyId]);
$participatingHouseholds = (int) $participantStatement->fetchColumn();
$pendingHouseholds = max(0, $eligibleHouseholds - $participatingHouseholds);
$participationRate = $eligibleHouseholds > 0 ? round(($participatingHouseholds / $eligibleHouseholds) * 100) : 0;

$pendingStatement = $db->prepare(
    'SELECT r.household_number, r.head_name, r.contact_number
     FROM residents r
     WHERE r.is_active = 1
       AND NOT EXISTS (
           SELECT 1 FROM survey_submissions sub
           WHERE sub.survey_id = ? AND sub.resident_id = r.id
       )
     ORDER BY r.household_number ASC
     LIMIT 10'
);
$pendingStatement->execute([$surveyId]);
$pendingRespondents = $pendingStatement->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Survey Respondents</title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=13">
<link rel="stylesheet" href="../css/logout.css">
<link rel="stylesheet" href="../../assets/css/admin-management.css">
<style>
.respondent-summary{margin:16px 0}.respondent-table-wrap{overflow-x:auto}.pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px}.pagination .btn[aria-disabled="true"]{pointer-events:none;opacity:.5}
</style>
</head>
<body>
<div class="portal-shell">
<div class="sidebar-backdrop"></div>
<aside class="sidebar">
    <div class="sidebar-brand"><div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div><div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div></div>
    <div class="id-card" data-navigate-href="admin_profile.php"><div class="id-eyebrow"><?= $admin['role'] === 'admin' ? 'Administrator Account' : 'Staff Account' ?></div><div class="id-card-row"><div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div><div class="id-card-name"><?= h($admin['full_name']) ?><small><?= h(ucfirst($admin['role'])) ?></small></div></div><div class="id-card-perf"></div><div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div></div>
    <nav class="nav-group"><span class="nav-label">Management</span><a class="nav-link" href="dashboard.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>Dashboard</a><a class="nav-link" href="surveys.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Management</a><a class="nav-link" href="members.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a><a class="nav-link" href="archive.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a><a class="nav-link" href="results.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="20" x2="4" y2="10"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="13"/><line x1="22" y1="20" x2="22" y2="7"/></svg></span>Results Dashboard</a><div class="nav-group-item">
            <button type="button" class="nav-link nav-parent" aria-expanded="false"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>
                <span>Reports</span>
                <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="nav-submenu">
                <a class="nav-link nav-sublink" href="reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Reports</a>
                <a class="nav-link nav-sublink" href="member_reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Reports</a>
            </div>
        </div><?php if ($admin['role'] === 'admin'): ?><a class="nav-link" href="users.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/><path d="M16 11a4 4 0 0 1 0-8"/><path d="M21 21v-2a6 6 0 0 0-4-5.7"/></svg></span>User Management</a><?php endif; ?></nav>
    <div class="nav-footer"><a class="nav-link" href="../../process/admin_logout.php" onclick="event.preventDefault(); logout();"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>Log Out</a></div>
</aside>
<main class="main">
    <div class="sticky-head">
    <div class="topbar">
        <div>
            <button class="menu-toggle" aria-label="Toggle menu">Menu</button>
            <div class="page-eyebrow">Survey participation</div>
            <h1 class="page-title">Respondents</h1>
            <p class="page-sub"><?= h($survey['title']) ?></p>
        </div>
        <a class="btn btn-outline" href="manage_survey.php?id=<?= (int) $survey['id'] ?>">Back to Survey</a>
    </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card active"><div class="stat-label">Eligible Households</div><div class="stat-value"><?= $eligibleHouseholds ?></div><div class="stat-hint">Active resident accounts</div></div>
        <div class="stat-card done"><div class="stat-label">Participating</div><div class="stat-value"><?= $participatingHouseholds ?></div><div class="stat-hint"><?= $participationRate ?>% participation rate</div></div>
        <div class="stat-card pending"><div class="stat-label">Still Pending</div><div class="stat-value"><?= $pendingHouseholds ?></div><div class="stat-hint">Households yet to respond</div></div>
    </div>

    <section class="card respondent-summary">
        <div class="card-title-row"><div><div class="card-title">Submitted Responses</div><div class="card-desc"><?= $respondentCount ?> household<?= $respondentCount === 1 ? '' : 's' ?> submitted this survey.</div></div><span class="badge <?= $survey['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $survey['is_active'] ? 'Active' : 'Closed' ?></span></div>
        <div class="respondent-table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Household No.</th><th>Household Head</th><th>Contact</th><th>Address</th><th>Answered</th><th>Submitted</th></tr></thead>
                <tbody>
                <?php if (!$respondents): ?><tr><td colspan="6">No households have submitted this survey yet.</td></tr><?php endif; ?>
                <?php foreach ($respondents as $respondent): ?>
                    <tr>
                        <td data-label="Household No."><?= h($respondent['household_number']) ?></td>
                        <td data-label="Household Head"><?= h($respondent['head_name']) ?></td>
                        <td data-label="Contact"><?= h($respondent['contact_number']) ?></td>
                        <td data-label="Address"><?= h($respondent['address']) ?></td>
                        <td data-label="Answered"><?= (int) $respondent['answered_questions'] ?> question<?= (int) $respondent['answered_questions'] === 1 ? '' : 's' ?></td>
                        <td data-label="Submitted"><?= h(date('M j, Y g:i A', strtotime($respondent['submitted_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($respondentCount > $perPage): ?>
            <nav class="pagination" aria-label="Respondent pages">
                <a class="btn btn-outline" href="?survey_id=<?= (int) $survey['id'] ?>&page=<?= $page - 1 ?>" <?= $page === 1 ? 'aria-disabled="true"' : '' ?>>Previous</a>
                <span class="card-desc">Page <?= $page ?> of <?= $totalPages ?></span>
                <a class="btn btn-outline" href="?survey_id=<?= (int) $survey['id'] ?>&page=<?= $page + 1 ?>" <?= $page === $totalPages ? 'aria-disabled="true"' : '' ?>>Next</a>
            </nav>
        <?php endif; ?>
    </section>

    <section class="card respondent-summary">
        <div class="card-title-row"><div><div class="card-title">Households Yet to Respond</div><div class="card-desc">Showing the first <?= min(10, $pendingHouseholds) ?> of <?= $pendingHouseholds ?> eligible household<?= $pendingHouseholds === 1 ? '' : 's' ?> that have not submitted this survey.</div></div></div>
        <div class="respondent-table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Household No.</th><th>Household Head</th><th>Contact</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$pendingRespondents): ?><tr><td colspan="4">Every eligible household has submitted this survey.</td></tr><?php endif; ?>
                <?php foreach ($pendingRespondents as $pending): ?>
                    <tr><td data-label="Household No."><?= h($pending['household_number']) ?></td><td data-label="Household Head"><?= h($pending['head_name']) ?></td><td data-label="Contact"><?= h($pending['contact_number']) ?></td><td data-label="Status"><span class="badge badge-warning">Not submitted</span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
<script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script>
</body>
</html>