<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';

$resident = require_login();
$initials = '';
foreach (array_slice(preg_split('/\s+/', trim($resident['head_name'])), 0, 2) as $word) {
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 10;
$totalStatement = database()->prepare('SELECT COUNT(*) FROM survey_submissions WHERE resident_id = ?');
$totalStatement->execute([$resident['id']]);
$totalSubmissions = (int) $totalStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalSubmissions / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$submissionStatement = database()->prepare(
    "SELECT s.title, s.description, sub.submitted_at
     FROM survey_submissions sub
     INNER JOIN surveys s ON s.id = sub.survey_id
     WHERE sub.resident_id = ?
     ORDER BY sub.submitted_at DESC, sub.id DESC
     LIMIT $perPage OFFSET $offset"
);
$submissionStatement->execute([$resident['id']]);
$submissions = $submissionStatement->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submitted Surveys - Barangay Health Center Resident Profiling & Survey Management System</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=12">
<link rel="stylesheet" href="../assets/css/logout.css">
<style>
.submitted-list{display:grid;gap:12px}.submitted-item{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--border);border-radius:var(--card-radius);padding:18px 20px}.submitted-icon{display:grid;place-items:center;flex:0 0 34px;width:34px;height:34px;border-radius:50%;background:#e6f7ee;color:#1f9d67;font-weight:700}.submitted-main{min-width:0;flex:1}.submitted-title{font-weight:700;color:var(--ink)}.submitted-desc,.submitted-date{font-size:.82rem;color:var(--muted);margin-top:4px}.pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px}.pagination .btn[aria-disabled="true"]{pointer-events:none;opacity:.5}@media(max-width:600px){.submitted-item{align-items:flex-start}.pagination{flex-wrap:wrap}}
</style>
</head>
<body>
<div class="portal-shell">
    <div class="sidebar-backdrop"></div>
    <aside class="sidebar">
        <div class="sidebar-brand"><div class="sidebar-seal"><img src="../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div><div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Resident Portal</small></div></div>
        <div class="id-card"><div class="id-eyebrow">Household ID</div><div class="id-card-row"><div class="id-avatar"><?= h($initials) ?></div><div class="id-card-name"><?= h($resident['head_name']) ?><small>Household Head</small></div></div><div class="id-card-perf"></div><div class="id-card-number"><span>Household No.</span><?= h($resident['household_number']) ?></div></div>
        <nav class="nav-group"><span class="nav-label">Menu</span>
            <a class="nav-link" href="dashboard.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>
                Dashboard
            </a>
            <a class="nav-link" href="surveys.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                Available Surveys
            </a>
            <a class="nav-link" href="profile.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                Profile
            </a>
            <a class="nav-link" href="changepassword.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.85 12.15 19 4"/><path d="M18 5l2 2"/><path d="M15 8l2 2"/></svg></span>
                Change Password
            </a>
        </nav>
        <div class="nav-footer">
            <a class="nav-link" href="../process/logout.php" onclick="event.preventDefault(); logout();">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                Log Out
            </a>
        </div>
    </aside>

    <main class="main">
    <div class="sticky-head">
        <div class="topbar">
            <div>
                <button class="menu-toggle" aria-label="Toggle menu">Menu</button>
                <div class="page-eyebrow">Household records</div>
                <h1 class="page-title">Submitted Surveys</h1>
                <p class="page-sub">All surveys completed by your household, newest first.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
        </div>
    </div>

        <section class="card">
            <div class="card-title-row"><div><div class="card-title">Submission History</div><div class="card-desc"><?= $totalSubmissions ?> completed survey<?= $totalSubmissions === 1 ? '' : 's' ?></div></div></div>
            <div class="submitted-list">
                <?php if (!$submissions): ?><p class="empty">Your household has not submitted any surveys yet.</p><?php endif; ?>
                <?php foreach ($submissions as $submission): ?>
                    <article class="submitted-item">
                        <div class="submitted-icon">✓</div>
                        <div class="submitted-main"><div class="submitted-title"><?= h($submission['title']) ?></div><div class="submitted-desc"><?= h($submission['description']) ?></div><div class="submitted-date">Submitted <?= h(date('F j, Y g:i A', strtotime($submission['submitted_at']))) ?></div></div>
                        <span class="badge badge-success">Submitted</span>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($totalSubmissions > $perPage): ?>
                <nav class="pagination" aria-label="Submitted survey pages">
                    <a class="btn btn-outline" href="?page=<?= $page - 1 ?>" <?= $page === 1 ? 'aria-disabled="true"' : '' ?>>Previous</a>
                    <span class="card-desc">Page <?= $page ?> of <?= $totalPages ?></span>
                    <a class="btn btn-outline" href="?page=<?= $page + 1 ?>" <?= $page === $totalPages ? 'aria-disabled="true"' : '' ?>>Next</a>
                </nav>
            <?php endif; ?>
        </section>
    </main>
</div>
<script src="../assets/js/dashboard.js?v=8"></script>
<script src="../assets/js/logout.js"></script>
</body>
</html>