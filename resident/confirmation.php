<?php
require_once __DIR__ . '/../config/auth.php';
$resident = require_login();
$initials = '';
foreach (array_slice(preg_split('/\s+/', trim($resident['head_name'])), 0, 2) as $w) { $initials .= mb_strtoupper(mb_substr($w, 0, 1)); }
$confirmation = $_SESSION['submission_confirmation'] ?? null;
unset($_SESSION['submission_confirmation']);
$surveyTitle = 'Your selected survey';
$submittedAt = null;
if (is_array($confirmation) && !empty($confirmation['survey_id'])) {
    $statement = database()->prepare('SELECT title FROM surveys WHERE id = ? LIMIT 1');
    $statement->execute([(int) $confirmation['survey_id']]);
    $surveyTitle = $statement->fetchColumn() ?: $surveyTitle;
    $submittedAt = !empty($confirmation['submitted_at']) ? strtotime((string) $confirmation['submitted_at']) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submitted — Barangay Health Center Resident Profiling & Survey Management System</title>
<link rel="stylesheet" href="../assets/css/confirmation.css?v=5">
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
            <a class="nav-link" href="dashboard.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>
                Dashboard
            </a>
            <a class="nav-link" href="surveys.php" aria-current="page">
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
        <div class="confirm-wrap">
            <div class="confirm-card">
                <div class="confirm-check">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </div>
                <h1>Salamat po! Your survey was submitted.</h1>
                <p>Your response for <strong><?= h($surveyTitle) ?></strong> has been recorded. You will not be able to submit this survey again.</p>

                <div class="confirm-meta">
                    <div><span>Survey</span><span><?= h($surveyTitle) ?></span></div>
                    <div><span>Household No.</span><span><?= h($resident['household_number']) ?></span></div>
                    <div><span>Submitted</span><span><?= $submittedAt ? h(date('M j, Y, g:i A', $submittedAt)) : 'Successfully recorded' ?></span></div>
                </div>

                <div class="confirm-actions">
                    <a href="surveys.php" class="btn btn-outline">More Surveys</a>
                    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="../assets/js/confirmation.js?v=2"></script>
<script src="../assets/js/logout.js"></script>
</body>
</html>