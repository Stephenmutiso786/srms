<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}
app_require_permission('marks.enter', '../merit_list');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../merit_list");
	exit;
}

$_SESSION['reply'] = array(array('info', 'Bulk merit-list editing has been turned off for safety. Open a specific student from the merit list and edit one chosen subject there.'));
header('location:../merit_list');
exit;

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$examId = (int)($_POST['exam_id'] ?? 0);
$scores = $_POST['scores'] ?? [];

$redirect = '../merit_list?class_id=' . $classId . '&term_id=' . $termId . '&exam_id=' . $examId;
if ($classId < 1 || $termId < 1 || $examId < 1 || !is_array($scores)) {
	$_SESSION['reply'] = array(array('danger', 'Select class, term, and exam before saving merit-list marks.'));
	header('location:' . $redirect);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_exam_grading_schema($conn);

	$stmt = $conn->prepare("SELECT COALESCE(status, 'draft') AS status FROM tbl_exams WHERE id = ? AND class_id = ? AND term_id = ? LIMIT 1");
	$stmt->execute([$examId, $classId, $termId]);
	$examStatus = strtolower(trim((string)$stmt->fetchColumn()));
	if ($examStatus === '') {
		throw new RuntimeException('Exam not found for the selected class and term.');
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
	$stmt->execute([$classId]);
	$validStudents = array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
	if (empty($validStudents)) {
		throw new RuntimeException('No learners found for the selected class.');
	}

	$subjects = report_fetch_subjects_for_class($conn, $classId);
	$subjectByCombination = [];
	foreach ($subjects as $subject) {
		$combinationId = (int)($subject['combination_id'] ?? 0);
		if ($combinationId > 0) {
			$subjectByCombination[$combinationId] = $subject;
		}
	}
	if (empty($subjectByCombination)) {
		throw new RuntimeException('No class subjects found for the selected class.');
	}

	$useGradeColumns = app_column_exists($conn, 'tbl_exam_results', 'grade_label') && app_column_exists($conn, 'tbl_exam_results', 'grade_points');
	$gradingSystemId = report_exam_grading_system_id($conn, $examId);
	$savedCount = 0;

	$conn->beginTransaction();
	foreach ($scores as $studentId => $studentScores) {
		$studentId = (string)$studentId;
		if (!isset($validStudents[$studentId]) || !is_array($studentScores)) {
			continue;
		}

		foreach ($studentScores as $combinationIdRaw => $scoreRaw) {
			$combinationId = (int)$combinationIdRaw;
			if ($combinationId < 1 || !isset($subjectByCombination[$combinationId])) {
				continue;
			}
			if ($scoreRaw === '' || $scoreRaw === null) {
				continue;
			}

			if (!is_numeric($scoreRaw)) {
				throw new RuntimeException('Marks must be numeric.');
			}
			$score = (float)$scoreRaw;
			if ($score < 0 || $score > 100) {
				throw new RuntimeException('Marks must be between 0 and 100.');
			}

			list($gradeLabel, , $gradePoints) = report_grade_for_score($conn, $score, $gradingSystemId);

			$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? AND exam_id = ? LIMIT 1");
			$stmt->execute([$studentId, $classId, $combinationId, $termId, $examId]);
			$existingId = (int)$stmt->fetchColumn();

			if ($existingId > 0) {
				if ($useGradeColumns) {
					$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ?, grade_label = ?, grade_points = ? WHERE id = ?");
					$stmt->execute([$score, $gradeLabel, $gradePoints, $existingId]);
				} else {
					$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE id = ?");
					$stmt->execute([$score, $existingId]);
				}
			} else {
				if ($useGradeColumns) {
					$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id, grade_label, grade_points) VALUES (?,?,?,?,?,?,?,?)");
					$stmt->execute([$studentId, $classId, $combinationId, $termId, $score, $examId, $gradeLabel, $gradePoints]);
				} else {
					$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id) VALUES (?,?,?,?,?,?)");
					$stmt->execute([$studentId, $classId, $combinationId, $termId, $score, $examId]);
				}
			}
			$savedCount++;
		}
	}
	$conn->commit();

	$_SESSION['reply'] = array(array('success', 'Merit-list marks saved successfully. Updated cells: ' . $savedCount));
	header('location:' . $redirect);
	exit;
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', $e->getMessage()));
	header('location:' . $redirect);
	exit;
}
