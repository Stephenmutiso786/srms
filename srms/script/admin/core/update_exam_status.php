<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/results_notifications.php');
require_once('const/system_notifications.php');

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
	app_ensure_exam_results_locks_table($conn);

	$stmt = $conn->prepare("SELECT * FROM tbl_exams WHERE id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$exam = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$exam) {
		throw new RuntimeException("Exam not found.");
	}

	$currentStatus = strtolower(trim((string)($exam['status'] ?? 'draft')));
	if ($currentStatus === 'open') {
		$currentStatus = 'active';
		$stmt = $conn->prepare("UPDATE tbl_exams SET status = 'active' WHERE id = ?");
		$stmt->execute([$examId]);
	}
	$assessmentMode = (string)($exam['assessment_mode'] ?? 'normal');
	if ($assessmentMode === 'consolidated') {
		app_ensure_exam_components_table($conn);
	}
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
		if ($assessmentMode === 'consolidated') {
			if (!app_table_exists($conn, 'tbl_exam_components')) {
				throw new RuntimeException("Consolidated exam components table is not installed.");
			}
			$stmt = $conn->prepare("SELECT component_exam_id FROM tbl_exam_components WHERE exam_id = ?");
			$stmt->execute([$examId]);
			$componentExamIds = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
			if (count($componentExamIds) < 2) {
				throw new RuntimeException("This consolidated exam must include at least two source exams.");
			}
			$placeholders = implode(',', array_fill(0, count($componentExamIds), '?'));
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exams WHERE id IN ($placeholders) AND class_id = ? AND term_id = ? AND status IN ('finalized', 'published')");
			$params = array_merge($componentExamIds, [(int)$exam['class_id'], (int)$exam['term_id']]);
			$stmt->execute($params);
			$readyCount = (int)$stmt->fetchColumn();
			if ($readyCount < count($componentExamIds)) {
				throw new RuntimeException("All selected source exams must be finalized or published before reviewing the consolidated exam.");
			}
		} elseif ($assessmentMode === 'cbc') {
			if (!app_table_exists($conn, 'tbl_cbc_mark_submissions')) {
				throw new RuntimeException("CBC marks submission workflow is not installed.");
			}
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbc_mark_submissions WHERE class_id = ? AND term_id = ? AND status = 'submitted'");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id']]);
			if ((int)$stmt->fetchColumn() > 0) {
				throw new RuntimeException("Some CBC mark sheets are still awaiting review.");
			}
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbc_mark_submissions WHERE class_id = ? AND term_id = ? AND status IN ('approved','finalized')");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id']]);
			if ((int)$stmt->fetchColumn() < 1) {
				throw new RuntimeException("Approve at least one submitted CBC mark sheet before marking the exam as reviewed.");
			}
		} else {
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
	}

	if ($status === 'finalized') {
		if ($assessmentMode === 'consolidated') {
			if (!app_table_exists($conn, 'tbl_exam_components')) {
				throw new RuntimeException("Consolidated exam components table is not installed.");
			}
			$stmt = $conn->prepare("SELECT component_exam_id FROM tbl_exam_components WHERE exam_id = ?");
			$stmt->execute([$examId]);
			$componentExamIds = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
			if (count($componentExamIds) < 2) {
				throw new RuntimeException("This consolidated exam must include at least two source exams.");
			}
			$placeholders = implode(',', array_fill(0, count($componentExamIds), '?'));
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exams WHERE id IN ($placeholders) AND class_id = ? AND term_id = ? AND status IN ('finalized', 'published')");
			$params = array_merge($componentExamIds, [(int)$exam['class_id'], (int)$exam['term_id']]);
			$stmt->execute($params);
			$readyCount = (int)$stmt->fetchColumn();
			if ($readyCount < count($componentExamIds)) {
				throw new RuntimeException("All selected source exams must be finalized or published before finalizing the consolidated exam.");
			}
		} elseif ($assessmentMode === 'cbc') {
			if (!app_table_exists($conn, 'tbl_cbc_mark_submissions')) {
				throw new RuntimeException("CBC marks submission workflow is not installed.");
			}
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbc_mark_submissions WHERE class_id = ? AND term_id = ? AND status IN ('draft','submitted','rejected')");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id']]);
			if ((int)$stmt->fetchColumn() > 0) {
				throw new RuntimeException("Finalize only after all CBC mark sheets are approved.");
			}
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbc_mark_submissions WHERE class_id = ? AND term_id = ?");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id']]);
			if ((int)$stmt->fetchColumn() < 1) {
				throw new RuntimeException("No CBC mark sheets have been submitted for this exam yet.");
			}
			$stmt = $conn->prepare("UPDATE tbl_cbc_mark_submissions SET status = 'finalized', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE class_id = ? AND term_id = ? AND status = 'approved'");
			$stmt->execute([(int)$account_id, (int)$exam['class_id'], (int)$exam['term_id']]);
		} else {
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
	}

	if ($status === 'draft') {
		if ($assessmentMode === 'cbc' && app_table_exists($conn, 'tbl_cbc_mark_submissions')) {
			$stmt = $conn->prepare("UPDATE tbl_cbc_mark_submissions SET status = 'draft', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE class_id = ? AND term_id = ? AND status IN ('submitted','approved')");
			$stmt->execute([(int)$account_id, (int)$exam['class_id'], (int)$exam['term_id']]);
		} elseif (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
			$stmt = $conn->prepare("UPDATE tbl_exam_mark_submissions SET status = 'draft', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE exam_id = ? AND status IN ('submitted','reviewed')");
			$stmt->execute([(int)$account_id, $examId]);
		}
	}

	$stmt = $conn->prepare("UPDATE tbl_exams SET status = ? WHERE id = ?");
	$stmt->execute([$status, $examId]);

	$examLabel = trim((string)($exam['name'] ?? $exam['title'] ?? 'Exam #' . $examId));
	$statusMessage = $examLabel . ' moved from ' . strtoupper($currentStatus) . ' to ' . strtoupper($status) . '.';
	try {
		app_system_notify($conn, 'Exam Workflow Update', $statusMessage, [
			'audience' => 'staff',
			'class_id' => (int)($exam['class_id'] ?? 0) ?: null,
			'term_id' => (int)($exam['term_id'] ?? 0) ?: null,
			'link' => 'exams',
			'created_by' => (int)$account_id,
		]);
	} catch (Throwable $notificationError) {
		error_log('['.__FILE__.':'.__LINE__.'] Exam workflow notification failed: ' . $notificationError->getMessage());
	}

	$autoNotifySummary = '';
	if ($status === 'published') {
		try {
			$publishMessage = $examLabel . ' has been published. Results are now available to relevant users.';
			app_system_notify($conn, 'Results Release', $publishMessage, [
				'audience' => 'all',
				'class_id' => (int)($exam['class_id'] ?? 0) ?: null,
				'term_id' => (int)($exam['term_id'] ?? 0) ?: null,
				'link' => 'publish_results',
				'created_by' => (int)$account_id,
			]);
		} catch (Throwable $notificationError) {
			error_log('['.__FILE__.':'.__LINE__.'] Results release notification failed: ' . $notificationError->getMessage());
		}

		if (app_table_exists($conn, 'tbl_exam_results_locks')) {
			if (DBDriver === 'pgsql') {
				$lockStmt = $conn->prepare("INSERT INTO tbl_exam_results_locks (exam_id, class_id, term_id, locked, reason, locked_by, locked_at)
					VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)
					ON CONFLICT (exam_id) DO UPDATE SET locked = EXCLUDED.locked, reason = EXCLUDED.reason, locked_by = EXCLUDED.locked_by, locked_at = EXCLUDED.locked_at");
				$lockStmt->execute([$examId, (int)$exam['class_id'], (int)$exam['term_id'], 1, 'Auto-locked on result publish', (int)$account_id]);
			} else {
				$lockStmt = $conn->prepare("INSERT INTO tbl_exam_results_locks (exam_id, class_id, term_id, locked, reason, locked_by, locked_at)
					VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)
					ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), term_id = VALUES(term_id), locked = VALUES(locked), reason = VALUES(reason), locked_by = VALUES(locked_by), locked_at = VALUES(locked_at)");
				$lockStmt->execute([$examId, (int)$exam['class_id'], (int)$exam['term_id'], 1, 'Auto-locked on result publish', (int)$account_id]);
			}
		}

		try {
			$stats = app_results_send_notifications($conn, $examId, 'both');
			$autoNotifySummary = ' Auto-send => SMS: ' . (int)$stats['sent_sms'] . ' sent, Email: ' . (int)$stats['sent_email'] . ' sent.';
			app_audit_log($conn, 'staff', (string)$account_id, 'results.notify.auto', 'exam', (string)$examId, $stats);
		} catch (Throwable $notifyError) {
			$autoNotifySummary = ' Auto-send failed: ' . $notifyError->getMessage();
		}
	}

	if ($currentStatus === 'published' && $status === 'finalized' && app_table_exists($conn, 'tbl_exam_results_locks')) {
		$lockStmt = $conn->prepare("UPDATE tbl_exam_results_locks SET locked = 0, reason = ?, locked_by = ?, locked_at = CURRENT_TIMESTAMP WHERE exam_id = ?");
		$lockStmt->execute(['Unlocked on unpublish', (int)$account_id, $examId]);
	}

	app_audit_log($conn, 'staff', (string)$account_id, 'exam.status', 'exam', (string)$examId, ['from' => $currentStatus, 'to' => $status]);

	$_SESSION['reply'] = array (array("success", "Exam status updated." . $autoNotifySummary));
	header("location:../exams");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to update status: " . $e->getMessage()));
	header("location:../exams");
}
