<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('results.enter', '../sba_entry');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../sba_entry.php");
	exit;
}

$grade = (int)($_POST['grade'] ?? 0);
$subjectId = (int)($_POST['subject_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$scores = $_POST['scores'] ?? [];

if ($grade < 7 || $grade > 8 || $subjectId < 1 || $termId < 1 || !is_array($scores)) {
	$_SESSION['reply'] = array (array("danger", "Invalid input. Please select grade, subject, and term."));
	header("location:../sba_entry.php");
	exit;
}

$scores = array_filter(array_map(function($studentId, $score) {
	$studentId = (int)$studentId;
	$score = trim((string)$score);
	if ($score === '' || $score === '0') {
		return null;
	}
	$scoreFloat = floatval($score);
	if ($scoreFloat < 0 || $scoreFloat > 100) {
		return null;
	}
	return [$studentId, $scoreFloat];
}, array_keys($scores), $scores));

if (empty($scores)) {
	$_SESSION['reply'] = array (array("warning", "No valid scores to save."));
	header("location:../sba_entry.php?grade=$grade&subject_id=$subjectId&term_id=$termId");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_sba_scores_table($conn);

	$insertStmt = $conn->prepare("
		INSERT INTO tbl_sba_scores (student_id, grade, subject_id, score, term_id)
		VALUES (?, ?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE score = VALUES(score)
	");

	$insertedCount = 0;
	foreach ($scores as [$studentId, $scoreValue]) {
		$insertStmt->execute([$studentId, $grade, $subjectId, $scoreValue, $termId]);
		$insertedCount++;
	}

	$_SESSION['reply'] = array (array("success", "SBA scores saved successfully for $insertedCount student(s)."));
	app_audit_log($conn, $_SESSION['account_id'] ?? null, 'sba_scores.save', "Grade $grade - Subject $subjectId - Term $termId - $insertedCount scores", 'admin');

	header("location:../sba_entry.php?grade=$grade&subject_id=$subjectId&term_id=$termId");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Error saving SBA scores: " . $e->getMessage()));
	header("location:../sba_entry.php?grade=$grade&subject_id=$subjectId&term_id=$termId");
}
