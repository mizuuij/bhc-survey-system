<?php
require_once __DIR__ . '/../config/auth.php';

$resident = require_login();
$initials = '';
foreach (array_slice(preg_split('/\s+/', trim($resident['head_name'])), 0, 2) as $w) {
    $initials .= mb_strtoupper(mb_substr($w, 0, 1));
}

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 5;
$totalSubStatement = database()->prepare('SELECT COUNT(*) FROM survey_submissions WHERE resident_id = ?');
$totalSubStatement->execute([$resident['id']]);
$totalSubmissionsCount = (int) $totalSubStatement->fetchColumn();
$totalSubPages = max(1, (int) ceil($totalSubmissionsCount / $perPage));
$page = min($page, $totalSubPages);
$offset = ($page - 1) * $perPage;

$submissionStatement = database()->prepare(
    "SELECT s.title, sub.submitted_at
     FROM survey_submissions sub
     INNER JOIN surveys s ON s.id = sub.survey_id
     WHERE sub.resident_id = ?
     ORDER BY sub.submitted_at DESC, sub.id DESC
     LIMIT $perPage OFFSET $offset"
);
$submissionStatement->execute([$resident['id']]);
$recentSubmissions = $submissionStatement->fetchAll();

$activeStatement = database()->query(
    'SELECT COUNT(*) FROM surveys
     WHERE is_active = 1 AND CURDATE() >= opens_at AND CURDATE() < closes_at'
);
$activeSurveyCount = (int) $activeStatement->fetchColumn();

$unansweredStatement = database()->prepare(
    'SELECT COUNT(*)
     FROM surveys s
     WHERE s.is_active = 1
       AND CURDATE() >= s.opens_at AND CURDATE() < s.closes_at
       AND NOT EXISTS (
           SELECT 1 FROM survey_submissions sub
           WHERE sub.survey_id = s.id AND sub.resident_id = ?
       )'
);
$unansweredStatement->execute([$resident['id']]);
$unansweredSurveyCount = (int) $unansweredStatement->fetchColumn();

$completedStatement = database()->prepare('SELECT COUNT(*) FROM survey_submissions WHERE resident_id = ?');
$completedStatement->execute([$resident['id']]);
$completedSurveyCount = (int) $completedStatement->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Barangay Health Center Resident Profiling & Survey Management System</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=12">
<link rel="stylesheet" href="../assets/css/logout.css">
</head>
<body>

<div class="portal-shell">

    <div class="sidebar-backdrop"></div>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-seal"><img src="../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div>
            <div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System
                <small>Resident Portal</small>
            </div>
        </div>

        <div class="id-card">
            <div class="id-eyebrow">Household ID</div>
            <div class="id-card-row">
                <div class="id-avatar"><?= h($initials) ?></div>
                <div class="id-card-name"><?= h($resident['head_name']) ?>
                    <small>Household Head</small>
                </div>
            </div>
            <div class="id-card-perf"></div>
            <div class="id-card-number"><span>Household No.</span><?= h($resident['household_number']) ?></div>
        </div>

        <nav class="nav-group">
            <span class="nav-label">Menu</span>
            <a class="nav-link" href="dashboard.php" aria-current="page">
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
                <button class="menu-toggle" aria-label="Toggle menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="page-eyebrow">Welcome back</div>
                <h1 class="page-title">Magandang araw, <?= h($resident['head_name']) ?></h1>
                <p class="page-sub">Here's a quick overview of your household's surveys and submission history.</p>
            </div>
        </div>

        <div class="banner">
            <div class="banner-text">
                <strong><?= $unansweredSurveyCount ?> survey<?= $unansweredSurveyCount === 1 ? '' : 's' ?> <?= $unansweredSurveyCount === 1 ? 'is' : 'are' ?> waiting for your household</strong>
                <span><?= $unansweredSurveyCount > 0 ? 'It only takes a few minutes to answer each one.' : 'Your household has answered every survey that is currently open.' ?></span>
            </div>
            <a href="surveys.php" class="btn btn-light">View Available Surveys</a>
        </div>
    </div>

        <div class="stat-grid">
            <div class="stat-card active">
                <div class="stat-label">Active Surveys</div>
                <div class="stat-value"><?= $activeSurveyCount ?></div>
                <div class="stat-hint">Currently open barangay-wide</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label">Not Yet Answered</div>
                <div class="stat-value"><?= $unansweredSurveyCount ?></div>
                <div class="stat-hint">Waiting for your response</div>
            </div>
            <div class="stat-card done">
                <div class="stat-label">Submitted</div>
                <div class="stat-value"><?= $completedSurveyCount ?></div>
                <div class="stat-hint">Total surveys completed</div>
            </div>
        </div>

        <div class="card">
            <div class="card-title-row">
                <div>
                    <div class="card-title">Submission History</div>
                    <div class="card-desc">Surveys your household has already answered.</div>
                </div>
                <a href="submissions.php" class="btn btn-outline">View All Submitted Surveys</a>
            </div>

            <div class="activity-list">
                <?php if (!$recentSubmissions): ?>
                    <div class="activity-row">
                        <div class="activity-main">
                            <div class="activity-title">No submissions yet</div>
                            <div class="activity-date">Completed surveys will appear here.</div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php foreach ($recentSubmissions as $submission): ?>
                    <div class="activity-row">
                        <div class="activity-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        </div>
                        <div class="activity-main">
                            <div class="activity-title"><?= h($submission['title']) ?></div>
                            <div class="activity-date">Submitted <?= h(date('F j, Y', strtotime($submission['submitted_at']))) ?></div>
                        </div>
                        <span class="badge badge-success">Submitted</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <nav class="pagination" aria-label="Submission history pages">
                <a class="btn btn-outline" href="?page=<?= $page - 1 ?>" <?= $page === 1 ? 'aria-disabled="true"' : '' ?>>Previous</a>
                <span class="card-desc">Page <?= $page ?> of <?= $totalSubPages ?></span>
                <a class="btn btn-outline" href="?page=<?= $page + 1 ?>" <?= $page === $totalSubPages ? 'aria-disabled="true"' : '' ?>>Next</a>
            </nav>
        </div>
    </main>
</div>

<script src="../assets/js/dashboard.js?v=9"></script>
<script src="../assets/js/logout.js"></script>
</body>
</html>