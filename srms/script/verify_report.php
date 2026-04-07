<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/report_engine.php');

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$status = 'Invalid';
$card = null;
$studentName = '';
$className = '';
$termName = '';

if ($code !== '') {
	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$stmt = $conn->prepare("SELECT * FROM tbl_report_cards WHERE verification_code = ? LIMIT 1");
		$stmt->execute([$code]);
		$card = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($card) {
			$payload = [
				'student_id' => $card['student_id'],
				'class_id' => (int)$card['class_id'],
				'term_id' => (int)$card['term_id'],
				'total' => (float)$card['total'],
				'mean' => (float)$card['mean'],
				'grade' => $card['grade'],
				'position' => (int)$card['position']
			];
			$hash = report_generate_hash($payload);
			if ($hash === $card['report_hash']) {
				$status = 'Verified';
			}

			$stmt = $conn->prepare("SELECT fname, lname, class FROM tbl_students WHERE id = ? LIMIT 1");
			$stmt->execute([$card['student_id']]);
			$student = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($student) {
				$studentName = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
				$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
				$stmt->execute([(int)$student['class']]);
				$className = (string)$stmt->fetchColumn();
			}
			$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
			$stmt->execute([(int)$card['term_id']]);
			$termName = (string)$stmt->fetchColumn();
		}
	} catch (Throwable $e) {
		$status = 'Invalid';
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Verify Report</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body style="background:#f4f7f6;">
<div class="container py-5">
<div class="report-card">
<div class="report-header">
<div>
<h2><?php echo APP_NAME; ?> Verification</h2>
<div class="report-meta">
<span>Verification Code: <?php echo htmlspecialchars($code); ?></span>
</div>
</div>
<div class="text-end">
<span class="report-badge"><i class="bi bi-shield-check"></i> <?php echo $status; ?></span>
</div>
</div>

<?php if ($status === 'Verified'): ?>
<div class="report-grid">
<div class="report-stat"><div class="label">Student</div><div class="value"><?php echo $studentName; ?></div></div>
<div class="report-stat"><div class="label">Class</div><div class="value"><?php echo $className; ?></div></div>
<div class="report-stat"><div class="label">Term</div><div class="value"><?php echo $termName; ?></div></div>
<div class="report-stat"><div class="label">Mean</div><div class="value"><?php echo $card['mean']; ?>%</div></div>
</div>
<p class="text-muted">This report card matches the official record stored in Elimu Hub.</p>
<?php else: ?>
<p class="text-danger">We could not verify this report card. Please contact the school.</p>
<?php endif; ?>
</div>
</div>
</body>
</html>
