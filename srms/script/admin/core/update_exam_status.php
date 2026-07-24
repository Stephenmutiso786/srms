<?php
session_start();
require_once(__DIR__ . '/../../db/config.php');
require_once(__DIR__ . '/../../const/check_session.php');
require_once(__DIR__ . '/../../const/rbac.php');
require_once(__DIR__ . '/../../const/results_notifications.php');
require_once(__DIR__ . '/../../const/system_notifications.php');
require_once(__DIR__ . '/../../const/report_engine.php');
if ($res !== "1") { header("location:../../"); exit; }
$portalHome = ((string)$level === '1') ? '../../academic' : '../exams';
app_require_permission('exams.manage', $portalHome);
app_require_unlocked('exams', $portalHome);

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
	$beforeSnapshot = app_exam_archive_payload($conn, $examId);

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

	$validateConsolidatedSources = function () use ($conn, $examId, $exam): void {
		if (!app_table_exists($conn, 'tbl_exam_components')) {
			throw new RuntimeException("Consolidated exam components table is not installed.");
		}
		if (!app_table_exists($conn, 'tbl_exam_results') || !app_column_exists($conn, 'tbl_exam_results', 'exam_id')) {
			throw new RuntimeException("Exam results table is not ready for consolidated validation.");
		}

		$stmt = $conn->prepare("SELECT component_exam_id FROM tbl_exam_components WHERE exam_id = ?");
		$stmt->execute([$examId]);
		$componentExamIds = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
		if (count($componentExamIds) < 2) {
			throw new RuntimeException("This consolidated exam must include at least two source exams.");
		}

		$placeholders = implode(',', array_fill(0, count($componentExamIds), '?'));
		$params = array_merge($componentExamIds, [(int)$exam['class_id'], (int)$exam['term_id']]);
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exams
			WHERE id IN ($placeholders)
			AND class_id = ? AND term_id = ?
			AND COALESCE(assessment_mode, 'normal') <> 'cbe'
			AND COALESCE(assessment_mode, 'normal') <> 'consolidated'
			AND COALESCE(status, 'draft') = 'published'");
		$stmt->execute($params);
		$publishedCount = (int)$stmt->fetchColumn();
		if ($publishedCount < count($componentExamIds)) {
			throw new RuntimeException("All selected source exams must be published before proceeding with this consolidated exam.");
		}

		foreach ($componentExamIds as $componentExamId) {
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ? AND exam_id = ?");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id'], $componentExamId]);
			if ((int)$stmt->fetchColumn() < 1) {
				throw new RuntimeException("Each selected source exam must already contain marks before proceeding with this consolidated exam.");
			}
		}
	};
	$repairCompletedSubjectSubmissions = function (string $targetStatus = 'finalized') use ($conn, $examId, $exam, $account_id, $assessmentMode): int {
		if ($assessmentMode === 'consolidated' || $assessmentMode === 'cbe') {
			return 0;
		}
		if (!app_table_exists($conn, 'tbl_exam_mark_submissions')) {
			return 0;
		}

		$gapSummary = report_exam_submission_gap_summary($conn, $examId);
		$fixed = 0;
		foreach ((array)($gapSummary['missing_subjects'] ?? []) as $missingSubject) {
			$subjectCombinationId = (int)($missingSubject['subject_combination_id'] ?? 0);
			$teacherId = (int)($missingSubject['teacher_id'] ?? 0);
			$missingStudentsCount = (int)($missingSubject['missing_students_count'] ?? 0);
			if ($subjectCombinationId < 1 || $teacherId < 1 || $missingStudentsCount > 0) {
				continue;
			}

			$stmt = $conn->prepare("SELECT id FROM tbl_exam_mark_submissions WHERE exam_id = ? AND subject_combination_id = ? LIMIT 1");
			$stmt->execute([$examId, $subjectCombinationId]);
			if ($stmt->fetchColumn()) {
				continue;
			}

			$stmt = $conn->prepare("INSERT INTO tbl_exam_mark_submissions (exam_id, class_id, term_id, subject_combination_id, teacher_id, status, submitted_at, reviewed_at, reviewed_by)
				VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,?)");
			$stmt->execute([
				$examId,
				(int)$exam['class_id'],
				(int)$exam['term_id'],
				$subjectCombinationId,
				$teacherId,
				$targetStatus,
				(int)$account_id,
			]);
			$fixed++;
		}

		return $fixed;
	};
	$validateRequiredSubjectSubmissions = function () use ($conn, $examId, $exam, $assessmentMode, $repairCompletedSubjectSubmissions): void {
		if ($assessmentMode === 'consolidated') {
			return;
		}
		if ($assessmentMode === 'cbe') {
			if (!app_table_exists($conn, 'tbl_exam_subjects')) {
				return;
			}
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_subjects WHERE exam_id = ?");
			$stmt->execute([$examId]);
			$expectedSubjects = (int)$stmt->fetchColumn();
			if ($expectedSubjects < 1) {
				return;
			}
			if (!app_table_exists($conn, 'tbl_cbe_mark_submissions')) {
				throw new RuntimeException("CBE marks submission workflow is not installed.");
			}
			$stmt = $conn->prepare("SELECT COUNT(DISTINCT subject_id) FROM tbl_cbe_mark_submissions WHERE class_id = ? AND term_id = ?");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id']]);
			$submittedSubjects = (int)$stmt->fetchColumn();
			if ($submittedSubjects < $expectedSubjects) {
				throw new RuntimeException("Cannot proceed. Missing submissions for some subjects: $submittedSubjects / $expectedSubjects submitted.");
			}
			return;
		}
		if (!app_table_exists($conn, 'tbl_exam_subjects')) {
			return;
		}
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_subjects WHERE exam_id = ?");
		$stmt->execute([$examId]);
		$expectedSubjects = (int)$stmt->fetchColumn();
		if ($expectedSubjects < 1) {
			return;
		}
		if (!app_table_exists($conn, 'tbl_exam_mark_submissions')) {
			throw new RuntimeException("Marks submission workflow is not installed.");
		}
		$stmt = $conn->prepare("SELECT COUNT(DISTINCT ms.subject_combination_id)
			FROM tbl_exam_mark_submissions ms
			JOIN tbl_subject_combinations sc ON sc.id = ms.subject_combination_id
			JOIN tbl_exam_subjects es ON es.subject_id = sc.subject AND es.exam_id = ms.exam_id
			WHERE ms.exam_id = ?");
		$stmt->execute([$examId]);
		$submittedSubjects = (int)$stmt->fetchColumn();
		if ($submittedSubjects < $expectedSubjects) {
			$repairCompletedSubjectSubmissions('finalized');
			$stmt->execute([$examId]);
			$submittedSubjects = (int)$stmt->fetchColumn();
		}
		if ($submittedSubjects < $expectedSubjects) {
			throw new RuntimeException("Cannot proceed. Missing submissions for some subjects: $submittedSubjects / $expectedSubjects submitted.");
		}
	};
	$notifyMissingSubmissionWarnings = function () use ($conn, $examId, $exam, $account_id): void {
		$gapSummary = report_exam_submission_gap_summary($conn, $examId);
		if (empty($gapSummary['missing_subjects'])) {
			return;
		}
		$subjectNames = array_map(static function ($row) {
			return (string)($row['subject_name'] ?? '');
		}, $gapSummary['missing_subjects']);
		$adminMessage = 'Cannot proceed with ' . (string)($gapSummary['exam_name'] ?? 'this exam') . '. Missing subject submissions: '
			. implode(', ', array_filter($subjectNames)) . '. Submitted '
			. (int)($gapSummary['submitted_subjects'] ?? 0) . ' / ' . (int)($gapSummary['total_subjects'] ?? 0) . '.';
		app_system_notify_unique($conn, 'Exam Missing Submissions', $adminMessage, 'exam-gap-admin-' . $examId, [
			'audience' => 'staff',
			'user_role' => 'admin',
			'class_id' => (int)($exam['class_id'] ?? 0),
			'term_id' => (int)($exam['term_id'] ?? 0),
			'link' => 'admin/exams',
			'created_by' => (int)$account_id,
			'type' => 'warning',
			'module_name' => 'exams',
			'priority' => 90,
		]);
		foreach ($gapSummary['missing_subjects'] as $missingSubject) {
			$teacherId = (int)($missingSubject['teacher_id'] ?? 0);
			if ($teacherId < 1) {
				continue;
			}
			$missingStudents = array_map(static function ($row) {
				return (string)($row['student_name'] ?? '');
			}, array_slice((array)($missingSubject['missing_students'] ?? []), 0, 5));
			$message = (string)($missingSubject['subject_name'] ?? 'Subject') . ' still has missing marks'
				. (!empty($missingStudents) ? ' for ' . implode(', ', array_filter($missingStudents)) : '')
				. '. Complete and submit this subject before the exam can be finalized or published.';
			app_system_notify_unique($conn, 'Missing Marks Warning', $message, 'exam-gap-teacher-' . $examId . '-' . (int)($missingSubject['subject_id'] ?? 0), [
				'audience' => 'staff',
				'user_role' => 'teacher',
				'class_id' => (int)($exam['class_id'] ?? 0),
				'term_id' => (int)($exam['term_id'] ?? 0),
				'link' => 'teacher/exam_marks_entry',
				'created_by' => (int)$account_id,
				'type' => 'warning',
				'module_name' => 'marks_entry',
				'priority' => 90,
			]);
		}
	};
	$transitionMap = [
		'draft' => ['active', 'reviewed', 'finalized'],
		'active' => $assessmentMode === 'consolidated'
			? ['draft', 'reviewed', 'finalized', 'published']
			: ['draft', 'reviewed', 'finalized'],
		'reviewed' => ['draft', 'active', 'finalized', 'published'],
		'finalized' => ['draft', 'active', 'reviewed', 'published'],
		'published' => ['draft', 'active', 'reviewed', 'finalized'],
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
		} elseif ($assessmentMode === 'cbe') {
			if (!app_table_exists($conn, 'tbl_cbe_mark_submissions')) {
				throw new RuntimeException("CBE marks submission workflow is not installed.");
			}
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbe_mark_submissions WHERE class_id = ? AND term_id = ? AND status = 'submitted'");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id']]);
			if ((int)$stmt->fetchColumn() > 0) {
				throw new RuntimeException("Some CBE mark sheets are still awaiting review.");
			}
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbe_mark_submissions WHERE class_id = ? AND term_id = ? AND status IN ('approved','finalized')");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id']]);
			if ((int)$stmt->fetchColumn() < 1) {
				throw new RuntimeException("Approve at least one submitted CBE mark sheet before marking the exam as reviewed.");
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
		$notifyMissingSubmissionWarnings();
		$validateRequiredSubjectSubmissions();
		if ($assessmentMode === 'consolidated') {
			$validateConsolidatedSources();
		} elseif ($assessmentMode === 'cbe') {
			if (!app_table_exists($conn, 'tbl_cbe_mark_submissions')) {
				throw new RuntimeException("CBE marks submission workflow is not installed.");
			}
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbe_mark_submissions WHERE class_id = ? AND term_id = ? AND status IN ('draft','submitted','rejected')");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id']]);
			if ((int)$stmt->fetchColumn() > 0) {
				throw new RuntimeException("Finalize only after all CBE mark sheets are approved.");
			}
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbe_mark_submissions WHERE class_id = ? AND term_id = ?");
			$stmt->execute([(int)$exam['class_id'], (int)$exam['term_id']]);
			if ((int)$stmt->fetchColumn() < 1) {
				throw new RuntimeException("No CBE mark sheets have been submitted for this exam yet.");
			}
			$stmt = $conn->prepare("UPDATE tbl_cbe_mark_submissions SET status = 'finalized', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE class_id = ? AND term_id = ? AND status = 'approved'");
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

	// Handle KJSEA final score computation and exam locking when finalized
	if ($status === 'finalized' && strtoupper($assessmentMode) === 'KJSEA') {
		try {
			// Ensure kjsea_final_score column exists
			if (app_table_exists($conn, 'tbl_exam_results') && !app_column_exists($conn, 'tbl_exam_results', 'kjsea_final_score')) {
				if (DBDriver === 'pgsql') {
					$conn->exec("ALTER TABLE tbl_exam_results ADD COLUMN kjsea_final_score DECIMAL(5, 2) DEFAULT NULL");
				} else {
					$conn->exec("ALTER TABLE tbl_exam_results ADD COLUMN kjsea_final_score DECIMAL(5, 2) DEFAULT NULL");
				}
			}

			// Compute final scores for all students
			$classId = (int)($exam['class_id'] ?? 0);
			if ($classId > 0) {
				$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ? OR class_id = ? ORDER BY id");
				$stmt->execute([$classId, $classId]);
				$studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

				foreach ($studentIds as $studentId) {
					$studentId = (int)$studentId;
					if ($studentId < 1) {
						continue;
					}

					// Get average SBA score (Grade 7 & 8)
					$sbaScore = app_get_sba_scores($conn, $studentId, 7, null);
					if (empty($sbaScore)) {
						$sbaScore = app_get_sba_scores($conn, $studentId, 8, null);
					}

					if (empty($sbaScore)) {
						continue; // No SBA data
					}

					$sbaAverage = array_sum($sbaScore) / count($sbaScore);

					// Get exam mark
					$stmt = $conn->prepare("SELECT MAX(mark) as exam_mark FROM tbl_exam_results WHERE student = ? AND exam_id = ? LIMIT 1");
					$stmt->execute([$studentId, $examId]);
					$examMark = (float)($stmt->fetchColumn() ?: 0);

					if ($examMark === 0) {
						continue; // No exam mark
					}

					// Compute final score: SBA 30% + Exam 70%
					$finalScore = ($sbaAverage * 0.30) + ($examMark * 0.70);

					// Update exam results
					$updateStmt = $conn->prepare("UPDATE tbl_exam_results SET kjsea_final_score = ? WHERE student = ? AND exam_id = ?");
					$updateStmt->execute([$finalScore, $studentId, $examId]);
				}
			}

			// Lock the KJSEA exam to prevent further edits
			app_lock_exam($conn, $examId);
			app_audit_log($conn, 'staff', (string)$account_id, 'kjsea_scores.finalized', 'exam', (string)$examId);
		} catch (Throwable $e) {
			error_log('[update_exam_status KJSEA] KJSEA finalization error: ' . $e->getMessage());
			// Log but don't block the status update
		}
	}

	if ($status === 'draft') {
		if ($assessmentMode === 'cbe' && app_table_exists($conn, 'tbl_cbe_mark_submissions')) {
			$stmt = $conn->prepare("UPDATE tbl_cbe_mark_submissions SET status = 'draft', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE class_id = ? AND term_id = ? AND status IN ('submitted','approved')");
			$stmt->execute([(int)$account_id, (int)$exam['class_id'], (int)$exam['term_id']]);
		} elseif (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
			$stmt = $conn->prepare("UPDATE tbl_exam_mark_submissions SET status = 'draft', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE exam_id = ? AND status IN ('submitted','reviewed')");
			$stmt->execute([(int)$account_id, $examId]);
		}
	}

	$stmt = $conn->prepare("UPDATE tbl_exams SET status = ? WHERE id = ?");
	if ($assessmentMode === 'consolidated' && $status === 'published') {
		$validateConsolidatedSources();
	}
	if ($status === 'published') {
		$notifyMissingSubmissionWarnings();
		$validateRequiredSubjectSubmissions();
	}
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
				'priority' => 85,
				'force_email' => true,
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
			$whatsappStats = app_results_send_notifications($conn, $examId, 'whatsapp');
			$emailStats = app_results_send_notifications($conn, $examId, 'email');
			$stats = [
				'sent_whatsapp' => (int)($whatsappStats['sent_whatsapp'] ?? 0),
				'failed_whatsapp' => (int)($whatsappStats['failed_whatsapp'] ?? 0),
				'sent_email' => (int)($emailStats['sent_email'] ?? 0),
				'failed_email' => (int)($emailStats['failed_email'] ?? 0),
				'missing_contacts' => (int)($whatsappStats['missing_contacts'] ?? 0) + (int)($emailStats['missing_contacts'] ?? 0),
				'skipped_fees' => max((int)($whatsappStats['skipped_fees'] ?? 0), (int)($emailStats['skipped_fees'] ?? 0)),
			];
			$firstWhatsappFailure = (string)($whatsappStats['failed'][0]['reason'] ?? '');
			$firstEmailFailure = (string)($emailStats['failed'][0]['reason'] ?? '');
			$autoNotifySummary = ' Auto-send => WhatsApp: ' . (int)$stats['sent_whatsapp'] . ' sent, ' . (int)$stats['failed_whatsapp'] . ' failed'
				. '; Email: ' . (int)$stats['sent_email'] . ' sent, ' . (int)$stats['failed_email'] . ' failed'
				. '; Missing contacts: ' . (int)$stats['missing_contacts'] . '.';
			if ($firstWhatsappFailure !== '') {
				$autoNotifySummary .= ' WhatsApp issue: ' . $firstWhatsappFailure . '.';
			}
			if ($firstEmailFailure !== '') {
				$autoNotifySummary .= ' Email issue: ' . $firstEmailFailure . '.';
			}
			app_audit_log($conn, 'staff', (string)$account_id, 'results.notify.auto.publish', 'exam', (string)$examId, $stats);
		} catch (Throwable $notifyError) {
			$autoNotifySummary = ' Auto-send failed: ' . $notifyError->getMessage();
		}
	}

	if ($currentStatus === 'published' && $status === 'finalized' && app_table_exists($conn, 'tbl_exam_results_locks')) {
		$lockStmt = $conn->prepare("UPDATE tbl_exam_results_locks SET locked = 0, reason = ?, locked_by = ?, locked_at = CURRENT_TIMESTAMP WHERE exam_id = ?");
		$lockStmt->execute(['Unlocked on unpublish', (int)$account_id, $examId]);
	}

	app_audit_log($conn, 'staff', (string)$account_id, 'exam.status', 'exam', (string)$examId, ['from' => $currentStatus, 'to' => $status]);
	$afterSnapshot = app_exam_archive_payload($conn, $examId);
	app_data_camp_store_event($conn, [
		'module_key' => 'exams',
		'record_type' => 'exam_status_changed',
		'entity_table' => 'tbl_exams',
		'entity_id' => (string)$examId,
		'title' => $examLabel,
		'description' => 'Exam workflow snapshot retained before and after status change',
		'class_id' => (int)($exam['class_id'] ?? 0) > 0 ? (int)$exam['class_id'] : null,
		'owner_portal' => 'admin,academic,teacher',
		'mime_type' => 'application/json',
		'status' => 'retained',
		'payload_json' => [
			'from' => $currentStatus,
			'to' => $status,
			'before' => $beforeSnapshot,
			'after' => $afterSnapshot,
		],
		'created_by' => (int)$account_id,
	]);

	$_SESSION['reply'] = array (array("success", "Exam status updated." . $autoNotifySummary));
	header("location:../exams");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to update status: " . $e->getMessage()));
	header("location:../exams");
}
