<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../report");
	exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);

if ($classId < 1 || $termId < 1) {
	$_SESSION['reply'] = array (array("danger", "Select class and term"));
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

	$rankData = report_rank_students($conn, $classId, $termId);
	$positions = $rankData['positions'];
	$totalStudents = $rankData['total_students'];

	$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
	$stmt->execute([$classId]);
	$students = $stmt->fetchAll(PDO::FETCH_COLUMN);

	$conn->beginTransaction();

	foreach ($students as $studentId) {
		$report = report_compute_for_student($conn, (string)$studentId, $classId, $termId);
		$position = $positions[(string)$studentId] ?? 0;
		$code = report_generate_code((string)$studentId);
		$payload = [
			'student_id' => (string)$studentId,
			'class_id' => $classId,
			'term_id' => $termId,
			'total' => $report['total'],
			'mean' => $report['mean'],
			'grade' => $report['grade'],
			'position' => $position
		];
		$hash = report_generate_hash($payload);
		$trend = $report['trend'];

		$stmt = $conn->prepare("SELECT id FROM tbl_report_cards WHERE student_id = ? AND term_id = ? LIMIT 1");
		$stmt->execute([(string)$studentId, $termId]);
		$existingId = $stmt->fetchColumn();

		if ($existingId) {
			$stmt = $conn->prepare("SELECT verification_code FROM tbl_report_cards WHERE id = ? LIMIT 1");
			$stmt->execute([$existingId]);
			$existingCode = (string)$stmt->fetchColumn();
			if ($existingCode === '') {
				$existingCode = $code;
			}
			$stmt = $conn->prepare("UPDATE tbl_report_cards SET total = ?, mean = ?, grade = ?, remark = ?, position = ?, total_students = ?, trend = ?, report_hash = ?, verification_code = ?, generated_by = ?, generated_at = CURRENT_TIMESTAMP WHERE id = ?");
			$stmt->execute([
				$report['total'],
				$report['mean'],
				$report['grade'],
				$report['remark'],
				$position,
				$totalStudents,
				$trend,
				$hash,
				$existingCode,
				$myid,
				$existingId
			]);
			$reportId = $existingId;
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_report_cards (student_id, class_id, term_id, total, mean, grade, remark, position, total_students, trend, verification_code, report_hash, generated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
			$stmt->execute([
				(string)$studentId,
				$classId,
				$termId,
				$report['total'],
				$report['mean'],
				$report['grade'],
				$report['remark'],
				$position,
				$totalStudents,
				$trend,
				$code,
				$hash,
				$myid
			]);
			$reportId = $conn->lastInsertId();
		}

		if (app_table_exists($conn, 'tbl_report_card_subjects')) {
			$stmt = $conn->prepare("DELETE FROM tbl_report_card_subjects WHERE report_id = ?");
			$stmt->execute([$reportId]);
			$stmt = $conn->prepare("INSERT INTO tbl_report_card_subjects (report_id, subject_id, score, grade, weight, teacher_id) VALUES (?,?,?,?,?,?)");
			foreach ($report['subjects'] as $subject) {
				list($g, $r) = report_grade_for_score($conn, (float)$subject['score']);
				$stmt->execute([
					$reportId,
					$subject['subject_id'],
					$subject['score'],
					$g,
					$subject['weight'],
					$subject['teacher_id']
				]);
			}
		}
	}

	$conn->commit();

	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
		$stmt->execute([$termId]);
		$termName = (string)$stmt->fetchColumn();
		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$classId]);
		$className = (string)$stmt->fetchColumn();
		$title = "Results Released";
		$message = "Report cards for " . ($className !== '' ? $className : "the class") . " (" . ($termName !== '' ? $termName : "term") . ") are now available.";

		$stmt = $conn->prepare("INSERT INTO tbl_notifications (title, message, audience, class_id, term_id, link, created_by) VALUES (?,?,?,?,?,?,?)");
		$stmt->execute([$title, $message, 'class', $classId, $termId, 'report_card?term=' . $termId, $myid]);
	}

	$_SESSION['reply'] = array (array("success", "Report cards generated successfully."));
	header("location:../report");
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array (array("danger", "Failed to generate report cards: " . $e->getMessage()));
	header("location:../report");
}
