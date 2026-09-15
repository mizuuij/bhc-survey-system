<?php
require_once __DIR__ . '/../config/auth.php';
$resident = require_login(); $initials = ''; foreach (array_slice(preg_split('/\s+/', trim($resident['head_name'])), 0, 2) as $w) { $initials .= mb_strtoupper(mb_substr($w, 0, 1)); }
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
$statusFilter = in_array($statusFilter, ['all', 'submitted', 'pending'], true) ? $statusFilter : 'all';

$where = ['s.is_active = 1', 'CURDATE() >= s.opens_at', 'CURDATE() < s.closes_at'];
$params = [$resident['id']];
if ($search !== '') {
    $where[] = '(s.title LIKE ? OR s.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($statusFilter === 'submitted') {
    $where[] = 'submission.submitted_at IS NOT NULL';
} elseif ($statusFilter === 'pending') {
    $where[] = 'submission.submitted_at IS NULL';
}
$whereSql = implode(' AND ', $where);

$perPage = 10;
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);

$countStatement = database()->prepare(
    "SELECT COUNT(*) FROM surveys s
     LEFT JOIN survey_submissions submission ON submission.survey_id = s.id AND submission.resident_id = ?
     WHERE $whereSql"
);
$countStatement->execute($params);
$totalSurveys = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalSurveys / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$statement = database()->prepare(
    "SELECT s.id, s.title, s.description, s.opens_at, s.closes_at, submission.submitted_at,
            (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_id = s.id) AS question_count
     FROM surveys s
     LEFT JOIN survey_submissions submission ON submission.survey_id = s.id AND submission.resident_id = ?
     WHERE $whereSql
     ORDER BY s.closes_at ASC
     LIMIT $perPage OFFSET $offset"
);
$statement->execute($params);
$surveys = $statement->fetchAll();
$queryString = http_build_query(array_filter(['q' => $search, 'status' => $statusFilter !== 'all' ? $statusFilter : null]));

function render_survey_results(array $surveys, string $search, string $statusFilter, int $page, int $totalPages, string $queryString): string
{
    ob_start();
    ?>
        <div class="survey-grid">
            <?php if (!$surveys): ?>
                <div class="survey-card"><h3>No surveys found</h3><p><?= ($search !== '' || $statusFilter !== 'all') ? 'No surveys match your search or filter.' : 'Please check back when a new survey is opened.' ?></p></div>
            <?php endif; ?>
            <?php foreach ($surveys as $survey): ?>
                <?php $submitted = $survey['submitted_at'] !== null; ?>
                <div class="survey-card">
                    <div class="survey-card-top">
                        <span class="badge badge-blue">Active Survey</span>
                        <span class="badge <?= $submitted ? 'badge-success' : 'badge-warning' ?>"><?= $submitted ? 'Submitted' : 'Not Answered' ?></span>
                    </div>
                    <h3><?= h($survey['title']) ?></h3>
                    <p><?= h($survey['description']) ?></p>
                    <div class="survey-meta">Open <?= h(date('M j', strtotime($survey['opens_at']))) ?> – <?= h(date('M j, Y', strtotime($survey['closes_at']))) ?></div>
                    <div class="survey-card-footer">
                        <?php $questionCount = (int) $survey['question_count']; ?>
                        <span class="survey-meta"><?= $submitted ? 'Answered ' . h(date('M j, Y', strtotime($survey['submitted_at']))) : $questionCount . ' question' . ($questionCount === 1 ? '' : 's') ?></span>
                        <?php if ($submitted): ?>
                            <button class="btn" disabled>Already Submitted</button>
                        <?php else: ?>
                            <a href="surveyform.php?survey_id=<?= (int) $survey['id'] ?>" class="btn btn-primary">Answer Survey</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <nav class="pagination" aria-label="Survey pages">
            <a class="btn btn-outline" data-page="<?= $page - 1 ?>" href="?<?= $queryString ? $queryString . '&' : '' ?>page=<?= $page - 1 ?>" <?= $page === 1 ? 'aria-disabled="true"' : '' ?>>Previous</a>
            <span class="card-desc">Page <?= $page ?> of <?= $totalPages ?></span>
            <a class="btn btn-outline" data-page="<?= $page + 1 ?>" href="?<?= $queryString ? $queryString . '&' : '' ?>page=<?= $page + 1 ?>" <?= $page === $totalPages ? 'aria-disabled="true"' : '' ?>>Next</a>
        </nav>
    <?php
    return (string) ob_get_clean();
}

$resultsHtml = render_survey_results($surveys, $search, $statusFilter, $page, $totalPages, $queryString);

if ((($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'XMLHttpRequest') {
    header('Content-Type: text/html; charset=UTF-8');
    echo $resultsHtml;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Available Surveys — Barangay Health Center Resident Profiling & Survey Management System</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=12">
<link rel="stylesheet" href="../assets/css/logout.css">
<link rel="stylesheet" href="../assets/css/surveys.css?v=3">
<style>
.page-eyebrow { color: var(--muted); }
.page-title { font-size: 1.65rem; }
</style>
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
    <div class="sticky-head">
        <div class="topbar">
            <div>
                <button class="menu-toggle" aria-label="Toggle menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="page-eyebrow">Open for your household</div>
                <h1 class="page-title">Available Surveys</h1>
                <p class="page-sub">Answer any open survey below. Each survey can only be submitted once per household.</p>
            </div>
        </div>

        <form class="survey-filters card" method="get" id="surveyFilterForm">
            <div class="survey-filters-row">
                <label class="survey-filter-field">
                    <span class="survey-filter-label">Search surveys</span>
                    <input type="search" name="q" id="surveySearchInput" value="<?= h($search) ?>" placeholder="Search by title or description" autocomplete="off">
                </label>
                <label class="survey-filter-field survey-filter-field-status">
                    <span class="survey-filter-label">Status</span>
                    <select name="status" id="surveyStatusSelect">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Not Yet Answered</option>
                        <option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                    </select>
                </label>
            </div>
        </form>
    </div>

        <div id="surveyResults"><?= $resultsHtml ?></div>
    </main>
</div>

<script src="../assets/js/surveys.js?v=5"></script>
<script src="../assets/js/logout.js"></script>
</body>
</html>