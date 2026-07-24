<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

app_require_discipline_access();

$leadershipTitle = trim((string)($designation ?? 'Academic Leadership'));
if ($leadershipTitle === '') {
	$leadershipTitle = 'Academic Leadership';
}

$caseId = (int)($_GET['id'] ?? 0);
if ($caseId < 1) {
	header('location:discipline.php');
	exit;
}

$case = null;
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_management_schema($conn);
	$stmt = $conn->prepare("SELECT d.*, st.school_id AS admission_no, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name, c.name AS class_name
		FROM tbl_discipline_cases d
		JOIN tbl_students st ON st.id = d.student_id
		LEFT JOIN tbl_classes c ON c.id = d.class_id
		WHERE d.id = ? LIMIT 1");
	$stmt->execute([$caseId]);
	$case = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($case) {
		$letterBody = 'Student Name: ' . ($case['student_name'] ?? '') . "\n"
			. 'Admission No: ' . ($case['admission_no'] ?? '') . "\n"
			. 'Class: ' . ($case['class_name'] ?? '') . "\n"
			. 'Offense: ' . ($case['incident_type'] ?? '') . "\n"
			. 'Action Taken: ' . ($case['action_taken'] ?: $case['action_recommended']) . "\n";
		$stmt = $conn->prepare("INSERT INTO tbl_discipline_letters (case_id, letter_body, printed_at, created_by, email_status) VALUES (?,?,CURRENT_TIMESTAMP,?,'Printed')");
		$stmt->execute([$caseId, $letterBody, (int)$account_id]);
	}
} catch (Throwable $e) {
	$case = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Parent Call-Up Notice</title>
<style>
body{font-family:Arial,sans-serif;color:#111;margin:40px}
.letter{max-width:820px;margin:0 auto}
.header{text-align:center;margin-bottom:32px}
.meta{margin:18px 0}
.sign{margin-top:60px;display:flex;justify-content:space-between}
.notice-chip{display:inline-block;background:#fff3cd;color:#7a5a00;padding:8px 14px;border-radius:999px;font-weight:700;font-size:12px;margin-top:8px}
.panel{border:1px solid #d9e4ef;border-radius:12px;padding:14px;margin:16px 0;background:#fafcff}
@media print {.no-print{display:none}}
</style>
</head>
<body>
<div class="letter">
	<div class="no-print" style="margin-bottom:20px;">
		<button onclick="window.print()">Print Letter</button>
		<a href="academic/discipline.php">Back</a>
	</div>
	<?php if ($case): ?>
	<div class="header">
		<h2 style="margin:0;"><?php echo htmlspecialchars((string)WBName); ?></h2>
		<div><?php echo htmlspecialchars((string)WBAddress); ?></div>
		<div><?php echo htmlspecialchars((string)WBPhone); ?> <?php echo WBEmail ? ' | ' . htmlspecialchars((string)WBEmail) : ''; ?></div>
	</div>
	<div><?php echo date('Y-m-d'); ?></div>
	<div class="notice-chip">OFFICIAL PARENT CALL-UP NOTICE</div>
	<h3>RE: DISCIPLINE ISSUANCE REPORT</h3>
	<div class="panel">
		<div class="meta"><strong>Student Name:</strong> <?php echo htmlspecialchars((string)$case['student_name']); ?></div>
		<div class="meta"><strong>Admission No:</strong> <?php echo htmlspecialchars((string)($case['admission_no'] ?? '')); ?></div>
		<div class="meta"><strong>Class:</strong> <?php echo htmlspecialchars((string)($case['class_name'] ?? '')); ?></div>
		<div class="meta"><strong>Offense Type:</strong> <?php echo htmlspecialchars((string)($case['incident_type'] ?? '')); ?></div>
		<div class="meta"><strong>Case Status:</strong> <?php echo htmlspecialchars((string)($case['case_status'] ?? 'Reported')); ?></div>
	</div>
	<p>This is to formally notify the parent or guardian that the above-named learner has been involved in the following disciplinary matter:</p>
	<p><?php echo nl2br(htmlspecialchars((string)$case['description'])); ?></p>
	<p><strong>Action Taken / Recommended:</strong> <?php echo htmlspecialchars((string)($case['action_taken'] ?: $case['action_recommended'])); ?></p>
	<p>You are required to bring your parent/guardian to school on or before <strong><?php echo date('Y-m-d', strtotime('+3 days')); ?></strong> for discussion with the <?php echo htmlspecialchars($leadershipTitle); ?>.</p>
	<p>Failure to comply may lead to further disciplinary action.</p>
	<div class="sign">
		<div>Parent/Guardian Signature: ____________________</div>
		<div><?php echo htmlspecialchars($leadershipTitle); ?> Signature: ____________________</div>
	</div>
	<?php else: ?>
	<p>Case not found.</p>
	<?php endif; ?>
</div>
</body>
</html>
