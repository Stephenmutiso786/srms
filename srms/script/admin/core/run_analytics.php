<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('results.approve', '../analytics_engine');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../analytics_engine");
	exit;
}

$termId = (int)($_POST['term_id'] ?? 0);
$classId = (int)($_POST['class_id'] ?? 0);
$scope = $classId > 0 ? 'class' : 'all';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_insights_alerts') || !app_table_exists($conn, 'tbl_validation_issues')) {
		$_SESSION['reply'] = array (array("danger", "Analytics tables missing. Run migration 016."));
		header("location:../analytics_engine");
		exit;
	}

	if ($termId < 1) {
		$stmt = $conn->prepare("SELECT id FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		$termId = (int)$stmt->fetchColumn();
		if ($termId < 1) {
			$stmt = $conn->prepare("SELECT id FROM tbl_terms ORDER BY id DESC LIMIT 1");
			$stmt->execute();
			$termId = (int)$stmt->fetchColumn();
		}
	}

	if ($termId < 1) {
		throw new RuntimeException("No term found.");
	}

	$classes = [];
	if ($classId > 0) {
		$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id = ?");
		$stmt->execute([$classId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) { $classes[] = $row; }
	} else {
		$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
		$stmt->execute();
		$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$conn->beginTransaction();

	// clear previous analytics for scope
	if ($scope === 'class') {
		$stmt = $conn->prepare("DELETE FROM tbl_validation_issues WHERE class_id = ? AND term_id = ?");
		$stmt->execute([$classId, $termId]);
		$stmt = $conn->prepare("DELETE FROM tbl_insights_alerts WHERE class_id = ? AND term_id = ?");
		$stmt->execute([$classId, $termId]);
	} else {
		$stmt = $conn->prepare("DELETE FROM tbl_validation_issues WHERE term_id = ?");
		$stmt->execute([$termId]);
		$stmt = $conn->prepare("DELETE FROM tbl_insights_alerts WHERE term_id = ?");
		$stmt->execute([$termId]);
	}

	$insertIssue = $conn->prepare("INSERT INTO tbl_validation_issues (issue_type, severity, message, student_id, class_id, term_id, subject_id) VALUES (?,?,?,?,?,?,?)");
	$insertAlert = $conn->prepare("INSERT INTO tbl_insights_alerts (alert_type, severity, title, message, student_id, class_id, term_id, subject_id) VALUES (?,?,?,?,?,?,?,?)");

	foreach ($classes as $class) {
		$cid = (int)$class['id'];

		// subjects mapped to class via combinations
		$stmt = $conn->prepare("SELECT id, subject, class FROM tbl_subject_combinations");
		$stmt->execute();
		$combos = [];
		$subjects = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$classList = app_unserialize($row['class']);
			if (in_array((string)$cid, array_map('strval', $classList), true)) {
				$combos[] = (int)$row['id'];
				$subjects[(int)$row['subject']] = true;
			}
		}
		$subjectCount = count($subjects);

		// students in class
		$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, mname, lname) AS name FROM tbl_students WHERE class = ? ORDER BY id");
		$stmt->execute([$cid]);
		$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// missing marks per student
		if ($subjectCount > 0 && app_table_exists($conn, 'tbl_exam_results')) {
			foreach ($students as $st) {
				$stmt = $conn->prepare("SELECT COUNT(DISTINCT subject_combination) FROM tbl_exam_results WHERE class = ? AND term = ? AND student = ?");
				$stmt->execute([$cid, $termId, $st['id']]);
				$count = (int)$stmt->fetchColumn();
				if ($count < $subjectCount) {
					$missing = $subjectCount - $count;
					$msg = "Missing $missing subject(s) for {$st['name']}.";
					$insertIssue->execute(['missing_marks', 'high', $msg, $st['id'], $cid, $termId, null]);
					$insertAlert->execute(['missing_marks', 'high', 'Missing marks detected', $msg, $st['id'], $cid, $termId, null]);
				}
			}
		}

		// subject outliers + low averages
		if (count($combos) > 0 && app_table_exists($conn, 'tbl_exam_results')) {
			foreach ($combos as $comboId) {
				$stmt = $conn->prepare("SELECT AVG(score) AS avg_score, STDDEV_POP(score) AS std_score FROM tbl_exam_results WHERE class = ? AND term = ? AND subject_combination = ?");
				$stmt->execute([$cid, $termId, $comboId]);
				$statsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['avg_score' => 0, 'std_score' => 0];
				$avg = (float)$statsRow['avg_score'];
				$std = (float)$statsRow['std_score'];

				if ($avg > 0 && $avg < 40) {
					$msg = "Subject combo $comboId average is low (".number_format($avg,2).").";
					$insertAlert->execute(['low_subject_avg', 'medium', 'Low subject average', $msg, null, $cid, $termId, null]);
				}

				if ($std > 0) {
					$stmt = $conn->prepare("SELECT student, score FROM tbl_exam_results WHERE class = ? AND term = ? AND subject_combination = ?");
					$stmt->execute([$cid, $termId, $comboId]);
					foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
						$score = (float)$row['score'];
						if (abs($score - $avg) > (2 * $std)) {
							$msg = "Outlier score $score in subject combo $comboId (avg ".number_format($avg,2).").";
							$insertIssue->execute(['outlier_score', 'medium', $msg, $row['student'], $cid, $termId, null]);
						}
					}
				}
			}
		}

		// attendance alerts (last 30 days)
		if (app_table_exists($conn, 'tbl_attendance_sessions') && app_table_exists($conn, 'tbl_attendance_records')) {
			$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
			$dateExpr = $driver === 'mysql' ? "DATE_SUB(CURDATE(), INTERVAL 30 DAY)" : "CURRENT_DATE - INTERVAL '30 days'";
			$stmt = $conn->prepare("SELECT r.student_id,
				SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) AS present_count,
				COUNT(*) AS total_count
				FROM tbl_attendance_records r
				JOIN tbl_attendance_sessions s ON s.id = r.session_id
				WHERE s.class_id = ? AND s.session_date >= $dateExpr
				GROUP BY r.student_id");
			$stmt->execute([$cid]);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$total = (int)$row['total_count'];
				if ($total < 1) { continue; }
				$rate = ((int)$row['present_count'] / $total) * 100;
				if ($rate < 75) {
					$msg = "Attendance is low (".number_format($rate,1)."%) in last 30 days.";
					$insertAlert->execute(['low_attendance', 'high', 'Low attendance detected', $msg, $row['student_id'], $cid, $termId, null]);
				}
			}
		}

		// performance trend drop (previous term)
		$prevTerm = $termId - 1;
		if ($prevTerm > 0 && app_table_exists($conn, 'tbl_exam_results')) {
			foreach ($students as $st) {
				$stmt = $conn->prepare("SELECT AVG(score) FROM tbl_exam_results WHERE class = ? AND term = ? AND student = ?");
				$stmt->execute([$cid, $termId, $st['id']]);
				$curAvg = (float)$stmt->fetchColumn();
				$stmt = $conn->prepare("SELECT AVG(score) FROM tbl_exam_results WHERE class = ? AND term = ? AND student = ?");
				$stmt->execute([$cid, $prevTerm, $st['id']]);
				$prevAvg = (float)$stmt->fetchColumn();
				if ($prevAvg > 0 && ($prevAvg - $curAvg) >= 10) {
					$msg = "Performance dropped by ".number_format($prevAvg - $curAvg,1)." points from previous term.";
					$insertAlert->execute(['performance_drop', 'medium', 'Performance drop detected', $msg, $st['id'], $cid, $termId, null]);
				}
			}
		}
	}

	$conn->commit();

	$_SESSION['reply'] = array (array("success", "Analytics generated successfully."));
	header("location:../analytics_engine");
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
	$_SESSION['reply'] = array (array("danger", "Analytics failed: " . $e->getMessage()));
	header("location:../analytics_engine");
}
