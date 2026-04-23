<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../");
	exit;
}
app_require_permission('report.generate', '../report');
app_require_unlocked('reports', '../report');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../report");
	exit;
}

@set_time_limit(300);

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$examId = (int)($_POST['exam_id'] ?? 0);

if ($classId < 1 || $termId < 1 || $examId < 1) {
	$_SESSION['reply'] = array (array("danger", "Select class, term, and exam"));
	header("location:../report");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_results_locks') && !app_results_locked($conn, $classId, $termId)) {
		$_SESSION['reply'] = array (array("danger", "Results are not locked yet. Please lock results before generating report cards."));
		header("location:../report");
		exit;
	}

	$examAllowed = false;
	if (app_table_exists($conn, 'tbl_exams')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_exams WHERE id = ? AND class_id = ? AND term_id = ? LIMIT 1");
		$stmt->execute([$examId, $classId, $termId]);
		$examAllowed = ((int)$stmt->fetchColumn() > 0);
	}
	if (!$examAllowed) {
		$_SESSION['reply'] = array (array("danger", "Select a valid exam for the selected class and term."));
		header("location:../report");
		exit;
	}

	$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE class = ?");
	$stmt->execute([$classId]);
	$totalStudents = (int)$stmt->fetchColumn();
	if ($totalStudents < 1) {
		throw new RuntimeException('This class has no registered students yet.');
	}

	$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
	if ($useExamId) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ? AND exam_id = ?");
		$stmt->execute([$classId, $termId, $examId]);
	} else {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ?");
		$stmt->execute([$classId, $termId]);
	}
	$totalResults = (int)$stmt->fetchColumn();

	$totalCbc = 0;
	if (app_table_exists($conn, 'tbl_cbc_assessments')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbc_assessments WHERE class_id = ? AND term_id = ?");
		$stmt->execute([$classId, $termId]);
		$totalCbc = (int)$stmt->fetchColumn();
	}

	if (($totalResults + $totalCbc) < 1) {
		throw new RuntimeException('No saved results were found for the selected class and term (exam results and CBC assessments are both empty).');
	}

	if (!app_table_exists($conn, 'tbl_report_cards')) {
		throw new RuntimeException('Report card support is not installed. Please run migrations.');
	}

	$generatedBy = isset($account_id) ? (int)$account_id : null;
	$meritList = report_class_merit_list($conn, $classId, $termId, $generatedBy);
	if (empty($meritList['rows'])) {
		throw new RuntimeException('No report cards could be generated for the selected class and term.');
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		try {
			$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
			$stmt->execute([$termId]);
			$termName = (string)$stmt->fetchColumn();
			$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
			$stmt->execute([$classId]);
			$className = (string)$stmt->fetchColumn();
			$title = "Results Released";
			$message = "Report cards for " . ($className !== '' ? $className : "the class") . " (" . ($termName !== '' ? $termName : "term") . ") are now ready for student, parent, and teacher access.";

			$columns = ['title', 'message'];
			$values = [$title, $message];

			if (app_column_exists($conn, 'tbl_notifications', 'audience')) {
				$columns[] = 'audience';
				$values[] = 'class';
			}
			if (app_column_exists($conn, 'tbl_notifications', 'class_id')) {
				$columns[] = 'class_id';
				$values[] = $classId;
			}
			if (app_column_exists($conn, 'tbl_notifications', 'term_id')) {
				$columns[] = 'term_id';
				$values[] = $termId;
			}
			if (app_column_exists($conn, 'tbl_notifications', 'link')) {
				$columns[] = 'link';
				$values[] = 'report_card?term=' . $termId;
			}
			if (app_column_exists($conn, 'tbl_notifications', 'created_by')) {
				$columns[] = 'created_by';
				$values[] = $generatedBy;
			}

			$placeholders = implode(',', array_fill(0, count($columns), '?'));
			$sql = "INSERT INTO tbl_notifications (" . implode(',', $columns) . ") VALUES (" . $placeholders . ")";
			$stmt = $conn->prepare($sql);
			$stmt->execute($values);
		} catch (Throwable $notifyError) {
			error_log('['.__FILE__.':'.__LINE__.'] Notification insert skipped: ' . $notifyError->getMessage());
		}
	}

	$_SESSION['reply'] = array (array("success", "Report cards are ready for " . $meritList['total_students'] . " learners. The class merit list has also been recalculated and saved."));
	header("location:../report");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to generate report cards: " . $e->getMessage()));
	header("location:../report");
}
