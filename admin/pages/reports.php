<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
$db = database();
$isPreview = defined('RENDER_REPORT_PREVIEW');
$selectedSurveyId = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT) ?: 0;
if ($selectedSurveyId > 0 && !$isPreview) {
    redirect('report_preview.php?survey_id=' . $selectedSurveyId);
}
$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'active', 'closed'], true)) $status = 'all';
$where = ['s.archived_at IS NULL'];
$params = [];
if ($search !== '') {
    $where[] = 's.title LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($status !== 'all') {
    $where[] = 's.is_active = ?';
    $params[] = $status === 'active' ? 1 : 0;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);
$perPage = 10;
$reportCountStatement = $db->prepare('SELECT COUNT(*) FROM surveys s' . $whereSql);
$reportCountStatement->execute($params);
$reportCount = (int) $reportCountStatement->fetchColumn();
$pageCount = max(1, (int) ceil($reportCount / $perPage));
$page = min(max(1, (int) ($_GET['page'] ?? 1)), $pageCount);
$paginationSql = $isPreview ? '' : ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
$reportPageUrl = static function (int $targetPage) use ($search, $status): string {
    $query = ['page' => $targetPage];
    if ($search !== '') $query['q'] = $search;
    if ($status !== 'all') $query['status'] = $status;
    return 'reports.php?' . http_build_query($query);
};

$surveyStatement = $db->prepare(
    "SELECT s.id, s.title, s.opens_at, s.closes_at, s.is_active,
            COUNT(DISTINCT sub.id) AS responses,
            MAX(sub.submitted_at) AS last_submission
     FROM surveys s
     LEFT JOIN survey_submissions sub ON sub.survey_id = s.id
     " . $whereSql . " GROUP BY s.id, s.title, s.opens_at, s.closes_at, s.is_active
     ORDER BY s.id DESC" . $paginationSql
);
$surveyStatement->execute($params);
$surveys = $surveyStatement->fetchAll();

$selectedSurvey = null;
$questions = [];

if ($selectedSurveyId > 0) {
    foreach ($surveys as $survey) {
        if ((int) $survey['id'] === $selectedSurveyId) {
            $selectedSurvey = $survey;
            break;
        }
    }
}
if ($isPreview && $selectedSurvey === null) {
    redirect('reports.php');
}

if ($selectedSurvey !== null) {
    $statement = $db->prepare(
        "SELECT q.id, q.question_text, q.question_type, q.choices_text,
                COUNT(CASE WHEN a.answer_text IS NOT NULL AND a.answer_text <> '' THEN 1 END) AS answer_count
         FROM survey_questions q
         LEFT JOIN survey_answers a ON a.question_id = q.id
         WHERE q.survey_id = ?
         GROUP BY q.id, q.question_text, q.question_type, q.choices_text
         ORDER BY q.id"
    );
    $statement->execute([$selectedSurveyId]);
    $questions = $statement->fetchAll();

    foreach ($questions as &$question) {
        $question['rows'] = [];
        $question['average'] = null;

        if (in_array($question['question_type'], ['multiple_choice', 'yes_no', 'rating'], true)) {
            if ($question['question_type'] === 'yes_no') {
                $labels = ['yes', 'no'];
            } elseif ($question['question_type'] === 'rating') {
                $labels = ['1', '2', '3', '4', '5'];
            } else {
                $labels = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $question['choices_text']))));
            }

            $counts = array_fill_keys($labels, 0);
            $answerStatement = $db->prepare(
                "SELECT answer_text, COUNT(*) AS total
                 FROM survey_answers
                 WHERE question_id = ? AND answer_text IS NOT NULL AND answer_text <> ''
                 GROUP BY answer_text"
            );
            $answerStatement->execute([(int) $question['id']]);
            foreach ($answerStatement->fetchAll() as $answer) {
                $counts[(string) $answer['answer_text']] = (int) $answer['total'];
            }

            $total = array_sum($counts);
            foreach ($counts as $label => $count) {
                $question['rows'][] = [
                    'label' => (string) $label,
                    'count' => (int) $count,
                    'percent' => $total > 0 ? round(((int) $count / $total) * 100, 1) : 0,
                ];
            }

            if ($question['question_type'] === 'rating') {
                $averageStatement = $db->prepare(
                    "SELECT ROUND(AVG(CAST(answer_text AS DECIMAL(3,1))), 1)
                     FROM survey_answers
                     WHERE question_id = ? AND answer_text IS NOT NULL AND answer_text <> ''"
                );
                $averageStatement->execute([(int) $question['id']]);
                $question['average'] = $averageStatement->fetchColumn() ?: null;
            }
        } else {
            $writtenStatement = $db->prepare(
                "SELECT a.answer_text, sub.submitted_at
                 FROM survey_answers a
                 JOIN survey_submissions sub ON sub.id = a.submission_id
                 WHERE a.question_id = ? AND a.answer_text IS NOT NULL AND a.answer_text <> ''
                 ORDER BY sub.submitted_at DESC, a.id DESC"
            );
            $writtenStatement->execute([(int) $question['id']]);
            $question['rows'] = $writtenStatement->fetchAll();
        }
    }
    unset($question);
}

$typeLabels = [
    'multiple_choice' => 'Multiple Choice',
    'yes_no' => 'Yes / No',
    'rating' => 'Rating Scale',
    'short_answer' => 'Written Answer',
];
$success = !$isPreview ? flash('survey_success') : null;
$error = !$isPreview ? flash('survey_error') : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reports</title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=15">
<link rel="stylesheet" href="../css/logout.css">
<link rel="stylesheet" href="../../assets/css/admin-management.css">
<link rel="stylesheet" href="../css/surveys.css?v=7">
<link rel="stylesheet" href="../css/confirm-modal.css?v=3">
<style>
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
.report-document{max-width:1040px;margin:22px auto 0;background:#fff;border:1px solid var(--border);box-shadow:0 8px 28px rgba(25,35,70,.08);padding:28px}.document-head{background:#f7f8fe;border:1px solid var(--border);border-top:4px solid #293d9e;border-radius:12px;margin-bottom:22px;padding:22px}.document-kicker{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#526ddc;font-weight:800}.document-title{font-size:1.6rem;line-height:1.25;margin:7px 0;overflow-wrap:anywhere}.document-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:18px}.meta-box{background:#fff;border:1px solid var(--border);border-radius:8px;min-width:0;padding:12px}.meta-label{font-size:.68rem;text-transform:uppercase;color:var(--muted);font-weight:700}.meta-value{font-weight:700;line-height:1.4;margin-top:4px;overflow-wrap:anywhere}.report-section{margin-top:24px}.report-section h2{font-size:1.05rem;margin:0;padding:0}.question-block{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 3px 12px rgba(25,35,70,.04);break-inside:avoid;margin-top:14px;padding:18px}.question-block h3{font-size:.98rem;line-height:1.45;margin:0 0 8px;overflow-wrap:anywhere}.question-note{background:#f3f5fa;border-radius:6px;color:var(--muted);display:inline-block;font-size:.78rem;line-height:1.4;margin:0 0 14px;padding:6px 9px}.table-scroll{width:100%;overflow-x:auto}.report-table{table-layout:fixed;width:100%;border-collapse:collapse}.report-table col.col-response{width:56%}.report-table col.col-count{width:20%}.report-table col.col-percent{width:24%}.report-table th,.report-table td{border:1px solid #d9deea;line-height:1.45;overflow-wrap:anywhere;word-break:break-word;padding:9px 11px;text-align:left;vertical-align:top;font-size:.82rem}.report-table th{background:#f3f5fa;font-size:.72rem;text-transform:uppercase;white-space:normal}.text-right{text-align:right!important}.empty-report{text-align:center;color:var(--muted);padding:26px}.document-footer{border-top:1px solid var(--border);margin-top:22px;padding-top:12px;color:var(--muted);font-size:.72rem}.action-link{white-space:nowrap}
@media(max-width:760px){.report-document{padding:16px}.document-head{padding:18px}.document-meta{grid-template-columns:1fr}.question-block{padding:15px}.report-table{min-width:480px}}
@media(max-width:600px){.report-pagination{flex-wrap:wrap}}
@media screen and (min-width:1001px){.responsive-table{background:#fff;table-layout:fixed}.responsive-table thead{display:table-header-group}.responsive-table tbody{display:table-row-group}.responsive-table tr{display:table-row}.responsive-table td{display:table-cell;overflow-wrap:anywhere;padding:15px;vertical-align:middle;word-break:break-word}.responsive-table th{display:table-cell;padding:15px;text-align:left;vertical-align:middle}.responsive-table th:nth-child(1),.responsive-table td:nth-child(1){text-align:center;width:5%}.responsive-table th:nth-child(2),.responsive-table td:nth-child(2){width:23%}.responsive-table th:nth-child(3),.responsive-table td:nth-child(3){width:12%}.responsive-table th:nth-child(4),.responsive-table td:nth-child(4){width:19%}.responsive-table th:nth-child(5),.responsive-table td:nth-child(5){width:9%}.responsive-table th:nth-child(6),.responsive-table td:nth-child(6){width:16%}.responsive-table th:nth-child(7),.responsive-table td:nth-child(7){text-align:center;width:16%}.responsive-table td[data-label="Actions"] .action-link{display:inline-flex;min-width:150px;width:auto}}
@media screen and (max-width:1000px){.responsive-table{background:transparent;border:0}.responsive-table,.responsive-table tbody,.responsive-table tr,.responsive-table td{display:block;width:100%}.responsive-table thead{display:none}.responsive-table tbody{display:grid;gap:14px}.responsive-table tr{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 3px 10px rgba(25,35,70,.05);padding:12px 8px}.responsive-table td{border:0;overflow-wrap:anywhere;padding:7px 16px}.responsive-table td:before{color:#7c8798;content:attr(data-label);display:block;font-size:.72rem;margin-bottom:3px;text-transform:uppercase}.responsive-table td[data-label="Select"]{padding-bottom:2px}.responsive-table td[data-label="Select"]:before{display:none}.responsive-table td[data-label="Actions"]{border-top:1px solid var(--border);margin-top:8px;padding-top:14px}.responsive-table td[data-label="Actions"] .action-link{width:100%}}
.bulk-export-toolbar{align-items:center;background:#f7f8fe;border:1px solid var(--border);border-radius:10px;display:flex;flex-wrap:wrap;gap:10px;margin:16px 0;padding:12px}.bulk-export-toolbar span{color:var(--muted);font-size:.85rem;font-weight:700;margin-right:auto}.bulk-export-toolbar .btn{padding:9px 14px}.bulk-export-toolbar .btn:disabled{opacity:.55}.select-survey{height:18px;width:18px;accent-color:var(--gov-blue-dark);cursor:pointer}.report-pagination{align-items:center;display:flex;gap:12px;justify-content:space-between;margin-top:18px}.report-pagination .btn[aria-disabled="true"]{opacity:.5;pointer-events:none}
@media print{@page{margin:15mm}.sidebar,.sidebar-backdrop,.topbar,.report-index,.menu-toggle{display:none!important}.portal-shell{display:block}.main{margin:0!important;padding:0!important}.report-document{display:block!important;max-width:none;margin:0;border:0;box-shadow:none;padding:0}.question-block{page-break-inside:avoid}.report-table th{background:#eee!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
</head>
<body>
<div class="portal-shell">
<div class="sidebar-backdrop"></div>
<aside class="sidebar">
    <div class="sidebar-brand"><div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div><div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div></div>
    <div class="id-card" data-navigate-href="admin_profile.php"><div class="id-eyebrow"><?= $admin['role'] === 'admin' ? 'Administrator Account' : 'Staff Account' ?></div><div class="id-card-row"><div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div><div class="id-card-name"><?= h($admin['full_name']) ?><small><?= h(ucfirst($admin['role'])) ?></small></div></div><div class="id-card-perf"></div><div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div></div>
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
                <a class="nav-link nav-sublink" href="reports.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Reports</a>
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
            <button class="menu-toggle" aria-label="Toggle menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            <div class="page-eyebrow"><?= $isPreview ? 'Survey report preview' : 'Export Report Summary' ?></div>
            <h1 class="page-title"><?= $isPreview ? h($selectedSurvey['title'] ?? 'Report Preview') : 'Reports' ?></h1>
            <p class="page-sub"><?= $isPreview ? 'Review this survey report, then export it or print it.' : 'Search or filter surveys, then select a report to preview.' ?></p>
        </div>
        <?php if ($selectedSurvey !== null): ?>
        <div class="report-toolbar">
            <a class="btn btn-light report-action" href="reports.php">Back to Report List</a>
            <details class="export-menu">
                <summary class="btn btn-light report-action">Export</summary>
                <div class="export-options">
                    <a href="../../process/export_report.php?survey_id=<?= $selectedSurveyId ?>&amp;format=xlsx">Excel (.xlsx)</a>
                    <a href="../../process/export_report.php?survey_id=<?= $selectedSurveyId ?>&amp;format=pdf">PDF</a>
                </div>
            </details>
            <a class="btn btn-primary" href="survey_report_print.php?survey_id=<?= $selectedSurveyId ?>" target="_blank" rel="noopener">Print Report</a>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($success): ?><p class="notice notice-success" role="status"><?= h($success) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="notice notice-error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <?php if (!$isPreview): ?>
        <form class="survey-tools" method="get" role="search" data-live-filter>
            <label class="tool-search" for="report-search">Search survey
                <input id="report-search" type="search" name="q" value="<?= h($search) ?>" placeholder="Search by survey title" autocomplete="off" data-live-search>
            </label>
            <label class="tool-filter" for="report-status">Status
                <select id="report-status" name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </label>
            <?php if ($search !== '' || $status !== 'all'): ?><a class="btn btn-outline clear-filters" href="reports.php">Clear filters</a><?php endif; ?>
        </form>
    <?php endif; ?>
    </div>

    <?php if (!$isPreview): ?>
    <section class="card report-index">
        <div class="card-title">Available Survey Reports</div>
        <div class="card-desc">Report records are generated from submitted survey responses.</div>
        <div data-live-results aria-live="polite">
        <div class="bulk-export-scope" data-bulk-export>
        <div class="bulk-export-toolbar">
            <span data-selection-count>0 surveys selected</span>
            <button class="btn btn-outline" type="button" data-bulk-export-submit="xlsx" disabled>Export Selected Excel</button>
            <button class="btn btn-primary" type="button" data-bulk-export-submit="pdf" disabled>Export Selected PDF</button>
        </div>
        <table class="responsive-table">
            <thead><tr><th><input class="select-survey" type="checkbox" aria-label="Select all surveys" data-select-all></th><th>Survey</th><th>Status</th><th>Response Period</th><th>Submissions</th><th>Last Submission</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($surveys as $survey): ?>
                <tr>
                    <td data-label="Select"><input class="select-survey" type="checkbox" name="survey_id[]" value="<?= (int) $survey['id'] ?>" aria-label="Select <?= h($survey['title']) ?>" data-survey-select></td>
                    <td data-label="Survey"><?= h($survey['title']) ?></td>
                    <td data-label="Status"><span class="badge <?= $survey['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $survey['is_active'] ? 'Active' : 'Closed' ?></span></td>
                    <td data-label="Response Period"><?= h(date('M j, Y', strtotime($survey['opens_at']))) ?> to <?= h(date('M j, Y', strtotime($survey['closes_at']))) ?></td>
                    <td data-label="Submissions"><?= (int) $survey['responses'] ?></td>
                    <td data-label="Last Submission"><?= $survey['last_submission'] ? h(date('M j, Y g:i A', strtotime($survey['last_submission']))) : '-' ?></td>
                    <td data-label="Actions">
                        <div class="survey-card-actions" style="justify-content:center">
                            <a class="btn btn-outline btn-sm icon-action" href="report_preview.php?survey_id=<?= (int) $survey['id'] ?>" aria-label="Preview report" data-tooltip="Preview Report"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                            <?php
                                $archiveSurveyConfirm = json_encode([
                                    'title' => 'Archive this survey?',
                                    'description' => '"' . $survey['title'] . '" will be closed and moved to the Archive, and removed from Survey Reports. You can restore it from there at any time.',
                                    'confirmLabel' => 'Yes, archive',
                                    'variant' => 'danger',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>
                            <form action="../../process/admin_archive_survey.php" method="post" data-confirm-modal='<?=$archiveSurveyConfirm?>'>
                                <input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>">
                                <input type="hidden" name="return_to" value="reports.php">
                                <button class="btn btn-danger btn-sm icon-action" type="submit" aria-label="Archive survey" data-tooltip="Archive"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18v4H3z"/><path d="M5 8v11h14V8"/><path d="M10 12h4"/></svg></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$surveys): ?><tr><td colspan="7" class="empty-report">No surveys are available.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
        <?php if ($reportCount > 0): ?>
            <nav class="report-pagination" aria-label="Report pages">
                <?php if ($page > 1): ?><a class="btn btn-outline" href="<?= h($reportPageUrl($page - 1)) ?>">Previous</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Previous</span><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pageCount ?></span>
                <?php if ($page < $pageCount): ?><a class="btn btn-outline" href="<?= h($reportPageUrl($page + 1)) ?>">Next</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Next</span><?php endif; ?>
            </nav>
        <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($selectedSurvey !== null): ?>
    <?php $isOpen = (int) $selectedSurvey['is_active'] === 1 && date('Y-m-d') >= $selectedSurvey['opens_at'] && date('Y-m-d') < $selectedSurvey['closes_at']; ?>
    <article class="report-document">
        <header class="document-head">
            <div class="document-kicker">Health Center Survey System</div>
            <h1 class="document-title"><?= h($selectedSurvey['title']) ?></h1>
            <div>Survey Results Report</div>
            <div class="document-meta">
                <div class="meta-box"><div class="meta-label">Response Period</div><div class="meta-value"><?= h(date('M j, Y', strtotime($selectedSurvey['opens_at']))) ?> to <?= h(date('M j, Y', strtotime($selectedSurvey['closes_at']))) ?></div></div>
                <div class="meta-box"><div class="meta-label">Total Submissions</div><div class="meta-value"><?= (int) $selectedSurvey['responses'] ?></div></div>
                <div class="meta-box"><div class="meta-label">Survey Status</div><div class="meta-value"><?= $isOpen ? 'Open' : 'Closed' ?></div></div>
            </div>
        </header>

        <section class="report-section">
            <h2>Question-by-Question Results</h2>
            <?php if (!$questions): ?><p class="empty-report">This survey has no questions.</p><?php endif; ?>
            <?php foreach ($questions as $index => $question): ?>
            <div class="question-block">
                <h3><?= $index + 1 ?>. <?= h($question['question_text']) ?></h3>
                <div class="question-note"><?= h($typeLabels[$question['question_type']] ?? $question['question_type']) ?> | <?= (int) $question['answer_count'] ?> recorded answer<?= (int) $question['answer_count'] === 1 ? '' : 's' ?><?php if ($question['average'] !== null): ?> | Average: <?= h((string) $question['average']) ?> / 5<?php endif; ?></div>

                <?php if (in_array($question['question_type'], ['multiple_choice', 'yes_no', 'rating'], true)): ?>
                <div class="table-scroll">
                <table class="report-table">
                    <colgroup><col class="col-response"><col class="col-count"><col class="col-percent"></colgroup>
                    <thead><tr><th>Response</th><th class="text-right">Count</th><th class="text-right">Percentage</th></tr></thead>
                    <tbody>
                    <?php foreach ($question['rows'] as $row): ?>
                        <tr><td><?= h($row['label']) ?></td><td class="text-right"><?= (int) $row['count'] ?></td><td class="text-right"><?= h(number_format((float) $row['percent'], 1)) ?>%</td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php else: ?>
                <div class="table-scroll">
                <table class="report-table">
                    <colgroup><col style="width:180px"><col></colgroup>
                    <thead><tr><th>Submitted</th><th>Written Response</th></tr></thead>
                    <tbody>
                    <?php foreach ($question['rows'] as $row): ?><tr><td><?= h(date('M j, Y g:i A', strtotime($row['submitted_at']))) ?></td><td><?= nl2br(h($row['answer_text'])) ?></td></tr><?php endforeach; ?>
                    <?php if (!$question['rows']): ?><tr><td colspan="2">No written responses.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </section>
        <footer class="document-footer">Generated <?= h(date('F j, Y, g:i A')) ?> from recorded survey submissions.</footer>
    </article>
    <?php endif; ?>
</main>
</div>
<?php if (!$isPreview): ?><script src="../js/live-filter.js?v=2"></script><script src="../js/bulk-export.js?v=3"></script><?php endif; ?>
<script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script>
<?php if (!$isPreview): ?><script src="../js/confirm-modal.js?v=3"></script><?php endif; ?>
</body>
</html>