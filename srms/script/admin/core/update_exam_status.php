<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('exams.manage', '../exams');
app_require_unlocked('exams', '../exams');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../exams");
	exit;
}

$examId = (int)($_POST['exam_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowedStatuses = ['draft', 'active', 'reviewed', 'finalized', 'published'];

if ($examId < 1 || !in_array($status, $allowedStatuses, true)) {
	$_SESSION['reply'] = array (array("danger", "Invalid request."));
	header("location:../exams");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_exams')) {
		$_SESSION['reply'] = array (array("danger", "Exams table missing. Run migration 007."));
		header("location:../exams");
		exit;
	}

	$stmt = $conn->prepare("SELECT * FROM tbl_exams WHERE id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$exam = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$exam) {
		throw new RuntimeException("Exam not found.");
	}

	$currentStatus = (string)($exam['status'] ?? 'draft');
	$transitionMap = [
		'draft' => ['active'],
		'active' => ['draft', 'reviewed'],
		'reviewed' => ['draft', 'finalized'],
		'finalized' => ['published'],
		'published' => ['finalized'],
	];
	if (!in_array($status, $transitionMap[$currentStatus] ?? [], true)) {
		throw new RuntimeException("That exam move is not allowed from the current stage.");
	}

	if ($status === 'reviewed') {
		if (!app_table_exists($conn, 'tbl_exam_mark_submissions')) {
			throw new RuntimeException("Marks submission workflow is not installed.");
		}
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE exam_id = ? AND status = 'submitted'");
		$stmt->execute([$examId]);
		if ((int)$stmt->fetchColumn() > 0) {
			throw new RuntimeException("Some mark sheets are still awaiting review.");
		}
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE exam_id = ? AND status = 'reviewed'");
		$stmt->execute([$examId]);
		if ((int)$stmt->fetchColumn() < 1) {
			throw new RuntimeException("Review at least one submitted mark sheet before marking the exam as reviewed.");
		}
	}

	if ($status === 'finalized') {
		if (!app_table_exists($conn, 'tbl_exam_mark_submissions')) {
			throw new RuntimeException("Marks submission workflow is not installed.");
		}
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE exam_id = ? AND status IN ('draft','submitted','rejected')");
		$stmt->execute([$examId]);
		if ((int)$stmt->fetchColumn() > 0) {
			throw new RuntimeException("Finalize only after all mark sheets are reviewed.");
		}
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE exam_id = ?");
		$stmt->execute([$examId]);
		if ((int)$stmt->fetchColumn() < 1) {
			throw new RuntimeException("No mark sheets have been submitted for this exam yet.");
		}
		$stmt = $conn->prepare("UPDATE tbl_exam_mark_submissions SET status = 'finalized', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE exam_id = ? AND status = 'reviewed'");
		$stmt->execute([(int)$account_id, $examId]);
	}

	if ($status === 'draft' && app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		$stmt = $conn->prepare("UPDATE tbl_exam_mark_submissions SET status = 'draft', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE exam_id = ? AND status IN ('submitted','reviewed')");
		$stmt->execute([(int)$account_id, $examId]);
	}

	$stmt = $conn->prepare("UPDATE tbl_exams SET status = ? WHERE id = ?");
	$stmt->execute([$status, $examId]);
	app_audit_log($conn, 'staff', (string)$account_id, 'exam.status', 'exam', (string)$examId, ['from' => $currentStatus, 'to' => $status]);

	$_SESSION['reply'] = array (array("success", "Exam status updated."));
	header("location:../exams");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to update status: " . $e->getMessage()));
	header("location:../exams");
}
