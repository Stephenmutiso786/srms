<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');

if ($res !== "1" || $level !== "2") { header("location:../"); }

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$classId = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$rows = [];
$subjects = [];
$termName = '';
$className = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if ($termId < 1 || $classId < 1 || !report_teacher_has_class_access($conn, (int)$account_id, $classId, $termId)) {
		header("location:manage_results");
		exit;
	}

	$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
	$stmt->execute([$termId]);
	$termName = (string)$stmt->fetchColumn();

	$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
	$stmt->execute([$classId]);
	$className = (string)$stmt->fetchColumn();

	$stmt = $conn->prepare("SELECT rc.id, rc.student_id, rc.mean, rc.grade,
		st.fname, st.mname, st.lname, st.school_id
		FROM tbl_report_cards rc
		JOIN tbl_students st ON st.id = rc.student_id
		WHERE rc.class_id = ? AND rc.term_id = ?
		ORDER BY st.fname, st.lname");
	$stmt->execute([$classId, $termId]);
	$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (!$cards) {
		$rankData = report_rank_students($conn, $classId, $termId);
		$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
		$stmt->execute([$classId]);
		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
			$report = report_compute_for_student($conn, (string)$studentId, $classId, $termId);
			report_store_card($conn, (string)$studentId, $classId, $termId, $report, $rankData['positions'], (int)$rankData['total_students'], (int)$account_id);
		}
		$stmt = $conn->prepare("SELECT rc.id, rc.student_id, rc.mean, rc.grade,
			st.fname, st.mname, st.lname, st.school_id
			FROM tbl_report_cards rc
			JOIN tbl_students st ON st.id = rc.student_id
			WHERE rc.class_id = ? AND rc.term_id = ?
			ORDER BY st.fname, st.lname");
		$stmt->execute([$classId, $termId]);
		$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	foreach ($cards as $card) {
		$cardRow = report_load_card($conn, (int)$card['id']);
		if (!$cardRow) {
			continue;
		}
		$rowSubjects = [];
		foreach ($cardRow['subjects'] as $subject) {
			$subjectName = (string)$subject['subject_name'];
			if (!in_array($subjectName, $subjects, true)) {
				$subjects[] = $subjectName;
			}
			$rowSubjects[$subjectName] = $subject['score'];
		}
		$rows[] = [
			'student_id' => (string)$card['student_id'],
			'school_id' => (string)($card['school_id'] ?? ''),
			'name' => trim(($card['fname'] ?? '') . ' ' . ($card['mname'] ?? '') . ' ' . ($card['lname'] ?? '')),
			'mean' => $card['mean'],
			'grade' => $card['grade'],
			'subjects' => $rowSubjects,
		];
	}
} catch (Throwable $e) {
	$rows = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Class Results</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<style>
@media print{
	.app-header,.app-sidebar,.app-title,.toolbar{display:none!important}
	.app-content{margin-left:0;padding:0}
	.tile{box-shadow:none;border:0}
}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a></header>
<main class="app-content" style="margin-left:0;">
<div class="app-title"><div><h1>Class Results Summary</h1><p class="mb-0 text-muted"><?php echo htmlspecialchars($className . ' - ' . $termName); ?></p></div></div>
<div class="tile">
<div class="tile-body">
<div class="toolbar d-flex justify-content-between gap-2 flex-wrap mb-3">
<a class="btn btn-outline-secondary btn-sm" href="teacher/manage_results"><i class="bi bi-arrow-left me-2"></i>Back</a>
<button class="btn btn-primary btn-sm" onclick="window.print();"><i class="bi bi-printer me-2"></i>Print Whole Class</button>
</div>
<div class="table-responsive">
<table class="table table-bordered table-striped table-sm">
<thead>
<tr>
<th>School ID</th>
<th>Student Name</th>
<?php foreach ($subjects as $subjectName): ?>
<th><?php echo htmlspecialchars($subjectName); ?></th>
<?php endforeach; ?>
<th>Average</th>
<th>Grade</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['school_id'] ?: $row['student_id']); ?></td>
<td><?php echo htmlspecialchars($row['name']); ?></td>
<?php foreach ($subjects as $subjectName): ?>
<td><?php echo isset($row['subjects'][$subjectName]) ? htmlspecialchars((string)$row['subjects'][$subjectName]) : '-'; ?></td>
<?php endforeach; ?>
<td><?php echo htmlspecialchars((string)$row['mean']); ?>%</td>
<td><?php echo htmlspecialchars($row['grade']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</main>
</body>
</html>
