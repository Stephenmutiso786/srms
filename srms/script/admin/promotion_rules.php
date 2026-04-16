<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

if ($res !== '1' || !in_array((int)$level, [0, 9], true)) {
    header('location:../');
    exit;
}
app_require_permission('report.generate', 'admin');

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_promotion_workflow_schema($conn);

$rules = [];
$stmt = $conn->prepare('SELECT * FROM tbl_promotion_rules WHERE school_id IS NULL ORDER BY grade_level ASC');
$stmt->execute();
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ruleMap = [];
foreach ($rules as $rule) {
    $ruleMap[(int)$rule['grade_level']] = $rule;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Promotion Rules</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>Promotion Rules</h1><p>Set the promotion thresholds that the batch workflow uses for simulation and approval.</p></div></div>

<div class="tile">
<form method="POST" action="admin/core/save_promotion_rules">
<div class="table-responsive">
<table class="table table-sm align-middle">
<thead>
<tr><th>Grade</th><th>Min Score</th><th>Fees Clearance</th><th>Report Finalization</th><th>Headteacher Approval</th><th>Auto Cert</th><th>Certificate Type</th></tr>
</thead>
<tbody>
<?php for ($grade = 1; $grade <= 9; $grade++): ?>
<?php $rule = $ruleMap[$grade] ?? app_promotion_rule_for_grade($conn, $grade); ?>
<tr>
<td><strong>Grade <?php echo $grade; ?></strong><input type="hidden" name="grade_level[]" value="<?php echo $grade; ?>"></td>
<td><input class="form-control form-control-sm" type="number" step="0.01" min="0" max="100" name="min_score_for_promotion[]" value="<?php echo htmlspecialchars((string)($rule['min_score_for_promotion'] ?? 40)); ?>"></td>
<td class="text-center"><input type="checkbox" name="require_fees_clearance[<?php echo $grade; ?>]" value="1"<?php echo !empty($rule['require_fees_clearance']) ? ' checked' : ''; ?>></td>
<td class="text-center"><input type="checkbox" name="require_report_finalization[<?php echo $grade; ?>]" value="1"<?php echo !empty($rule['require_report_finalization']) ? ' checked' : ''; ?>></td>
<td class="text-center"><input type="checkbox" name="require_headteacher_approval[<?php echo $grade; ?>]" value="1"<?php echo !empty($rule['require_headteacher_approval']) ? ' checked' : ''; ?>></td>
<td class="text-center"><input type="checkbox" name="auto_generate_certificate[<?php echo $grade; ?>]" value="1"<?php echo !empty($rule['auto_generate_certificate']) ? ' checked' : ''; ?>></td>
<td>
<select class="form-control form-control-sm" name="certificate_type[]">
<option value="general"<?php echo (($rule['certificate_type'] ?? '') === 'general') ? ' selected' : ''; ?>>General</option>
<option value="primary_completion"<?php echo (($rule['certificate_type'] ?? '') === 'primary_completion') ? ' selected' : ''; ?>>Primary Completion</option>
<option value="junior_completion"<?php echo (($rule['certificate_type'] ?? '') === 'junior_completion') ? ' selected' : ''; ?>>Junior Completion</option>
<option value="transfer"<?php echo (($rule['certificate_type'] ?? '') === 'transfer') ? ' selected' : ''; ?>>Transfer</option>
</select>
</td>
</tr>
<?php endfor; ?>
</tbody>
</table>
</div>
<div class="d-grid">
<button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-save me-2"></i>Save Promotion Rules</button>
</div>
</form>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
