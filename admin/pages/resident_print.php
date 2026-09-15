<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
$db = database();

$residentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$residentId) {
    redirect('members.php');
}

$residentStmt = $db->prepare('SELECT * FROM residents WHERE id = ? LIMIT 1');
$residentStmt->execute([$residentId]);
$resident = $residentStmt->fetch();
if (!$resident) {
    flash('member_error', 'That resident record could not be found.');
    redirect('members.php');
}

$profileStmt = $db->prepare('SELECT * FROM resident_profile WHERE resident_id = ? LIMIT 1');
$profileStmt->execute([$residentId]);
$personal = $profileStmt->fetch() ?: null;

$age = null;
if (!empty($personal['birthday'])) {
    $age = (int) (new DateTime($personal['birthday']))->diff(new DateTime('today'))->y;
}

$spouseStmt = $db->prepare('SELECT * FROM resident_spouse WHERE resident_id = ? LIMIT 1');
$spouseStmt->execute([$residentId]);
$spouse = $spouseStmt->fetch() ?: null;

$parentsStmt = $db->prepare('SELECT * FROM resident_parents WHERE resident_id = ? LIMIT 1');
$parentsStmt->execute([$residentId]);
$parents = $parentsStmt->fetch() ?: null;

$childrenStmt = $db->prepare('SELECT child_name, age FROM resident_children WHERE resident_id = ? ORDER BY id');
$childrenStmt->execute([$residentId]);
$children = $childrenStmt->fetchAll();

$referencesStmt = $db->prepare('SELECT reference_name, signature_path FROM resident_references WHERE resident_id = ? ORDER BY id');
$referencesStmt->execute([$residentId]);
$references = $referencesStmt->fetchAll();

$fullName = trim(($personal['first_name'] ?? '') . ' ' . ($personal['middle_name'] ?? '') . ' ' . ($personal['last_name'] ?? '') . ' ' . ($personal['extension_name'] ?? ''));
$fullName = $fullName !== '' ? preg_replace('/\s+/', ' ', $fullName) : $resident['head_name'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Member Profile Report — <?= h($resident['head_name']) ?></title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=15">
<style>
.print-toolbar{align-items:center;background:#f7f8fe;border-bottom:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;padding:14px 24px}
.report-document{max-width:1040px;margin:22px auto 0;background:#fff;border:1px solid var(--border);box-shadow:0 8px 28px rgba(25,35,70,.08);padding:28px}.document-head{background:#f7f8fe;border:1px solid var(--border);border-top:4px solid #293d9e;border-radius:12px;margin-bottom:22px;padding:22px}.document-kicker{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#526ddc;font-weight:800}.document-title{font-size:1.6rem;line-height:1.25;margin:7px 0;overflow-wrap:anywhere}.document-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:18px}.meta-box{background:#fff;border:1px solid var(--border);border-radius:8px;min-width:0;padding:12px}.meta-label{font-size:.68rem;text-transform:uppercase;color:var(--muted);font-weight:700}.meta-value{font-weight:700;line-height:1.4;margin-top:4px;overflow-wrap:anywhere}.report-section{margin-top:24px}.report-section h2{font-size:1.05rem;margin:0;padding:0}.question-block{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 3px 12px rgba(25,35,70,.04);break-inside:avoid;margin-top:14px;padding:18px}.table-scroll{width:100%;overflow-x:auto}.report-table{table-layout:fixed;width:100%;border-collapse:collapse}.report-table th,.report-table td{border:1px solid #d9deea;line-height:1.45;overflow-wrap:anywhere;word-break:break-word;padding:9px 11px;text-align:left;vertical-align:top;font-size:.82rem}.report-table th{background:#f3f5fa;font-size:.72rem;text-transform:uppercase;white-space:normal}.document-footer{border-top:1px solid var(--border);margin-top:22px;padding-top:12px;color:var(--muted);font-size:.72rem}
.empty-report{text-align:center;color:var(--muted);padding:26px}
@media(max-width:760px){.report-document{padding:16px}.document-head{padding:18px}.document-meta{grid-template-columns:1fr}.question-block{padding:15px}.report-table{min-width:480px}}
@media print{
  .print-toolbar{display:none!important}
  body{background:#fff}
  .report-document{display:block!important;max-width:none;margin:0;border:0;box-shadow:none;padding:0;font-size:11px}
  .document-head{margin-bottom:8px;padding:8px 12px;border-top-width:2px}
  .document-kicker{font-size:.58rem}
  .document-title{font-size:1.05rem;margin:3px 0}
  .document-meta{margin-top:6px;gap:6px}
  .meta-box{padding:5px 8px}
  .meta-label{font-size:.55rem}
  .report-section{margin-top:8px}
  .question-block{margin-top:6px;padding:10px;box-shadow:none}
  .report-table th,.report-table td{padding:5px 7px;font-size:.65rem}
  .document-footer{margin-top:10px;padding-top:6px;font-size:.58rem}
}
</style>
</head>
<body>
<div class="print-toolbar">
    <a class="btn btn-outline btn-sm" href="resident_view.php?id=<?= (int) $resident['id'] ?>">Back to Record</a>
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Print</button>
</div>

<article class="report-document">
    <header class="document-head">
        <div class="document-kicker">Barangay Longos Survey System</div>
        <h1 class="document-title"><?= h($resident['head_name']) ?></h1>
        <div>Member Profile Report</div>
        <div class="document-meta">
            <div class="meta-box"><div class="meta-label">Account No.</div><div class="meta-value"><?= h($resident['resident_number']) ?></div></div>
            <div class="meta-box"><div class="meta-label">Purok</div><div class="meta-value"><?= h($resident['purok'] ?: '') ?></div></div>
            <div class="meta-box"><div class="meta-label">Status</div><div class="meta-value"><?= $resident['archived_at'] !== null ? 'Archived' : ($resident['is_active'] ? 'Active' : 'Deactivated') ?></div></div>
        </div>
    </header>

    <section class="report-section">
        <h2>Household &amp; Contact Information</h2>
        <div class="question-block">
            <div class="table-scroll">
            <table class="report-table">
                <colgroup><col style="width:220px"><col></colgroup>
                <tbody>
                    <tr><td>Household No.</td><td><?= h($resident['household_number']) ?></td></tr>
                    <tr><td>Contact Number</td><td><?= h($resident['contact_number']) ?></td></tr>
                    <tr><td>Address</td><td><?= nl2br(h($resident['address'])) ?></td></tr>
                    <tr><td>Purok</td><td><?= h($resident['purok'] ?: '') ?></td></tr>
                    <tr><td>Registered</td><td><?= h(date('M j, Y', strtotime($resident['created_at']))) ?></td></tr>
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
                    <tr><td>Sex</td><td><?= !empty($personal['sex']) ? h(ucfirst($personal['sex'])) : '' ?></td></tr>
                    <tr><td>Civil Status</td><td><?= !empty($personal['civil_status']) ? h(ucfirst($personal['civil_status'])) : '' ?></td></tr>
                    <tr><td>Birth of Date</td><td><?= !empty($personal['birthday']) ? h(date('M j, Y', strtotime($personal['birthday']))) : '' ?></td></tr>
                    <tr><td>Age</td><td><?= $age !== null ? $age : '' ?></td></tr>
                    <tr><td>Occupation</td><td><?= !empty($personal['occupation']) ? h($personal['occupation']) : '' ?></td></tr>
                    <tr><td>Employer</td><td><?= !empty($personal['employer']) ? h($personal['employer']) : '' ?></td></tr>
                    <tr><td>Employer Address</td><td><?= !empty($personal['employer_address']) ? h($personal['employer_address']) : '' ?></td></tr>
                </tbody>
            </table>
            </div>
            <?php if ($personal === null): ?>
                <p class="empty-report" style="padding-top:10px">This member has not completed their personal profile yet.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="report-section">
        <h2>Family Information</h2>
        <div class="question-block">
            <?php if ($spouse === null && $parents === null && !$children): ?>
                <p class="empty-report">No family information on file.</p>
            <?php else: ?>
            <div class="table-scroll">
            <table class="report-table">
                <colgroup><col style="width:220px"><col></colgroup>
                <tbody>
                    <?php if ($spouse !== null): ?>
                    <tr><td>Spouse</td><td><?= h($spouse['spouse_name']) ?><?= $spouse['occupation'] ? ' — ' . h($spouse['occupation']) : '' ?></td></tr>
                    <?php endif; ?>
                    <?php if ($parents !== null): ?>
                    <tr><td>Father's Name</td><td><?= h($parents['father_name'] ?: '') ?></td></tr>
                    <tr><td>Mother's Name</td><td><?= h($parents['mother_name'] ?: '') ?></td></tr>
                    <?php endif; ?>
                    <?php if ($children): ?>
                    <tr><td>Children</td><td><?= implode(', ', array_map(static fn($c) => h($c['child_name']) . ($c['age'] !== null ? ' (' . (int) $c['age'] . ')' : ''), $children)) ?></td></tr>
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
            <?php if (!$references): ?>
                <p class="empty-report">No references on file.</p>
            <?php else: ?>
            <div class="table-scroll">
            <table class="report-table">
                <colgroup><col></colgroup>
                <thead><tr><th>Reference Name</th></tr></thead>
                <tbody>
                <?php foreach ($references as $ref): ?>
                    <tr><td><?= h($ref['reference_name']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="document-footer">Printed <?= h(date('F j, Y, g:i A')) ?> by <?= h($admin['full_name']) ?> (<?= h($admin['username']) ?>) &middot; Barangay Longos Survey System.</footer>
</article>

</body>
</html>
