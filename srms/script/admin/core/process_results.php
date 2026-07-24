<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
require_once(__DIR__ . '/../../db/config.php');
require_once(__DIR__ . '/../../const/check_session.php');
require_once(__DIR__ . '/../../const/report_engine.php');
require_once(__DIR__ . '/../../const/rbac.php');
require_once(__DIR__ . '/../../const/system_notifications.php');

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

@set_time_limit(0);
@ini_set('memory_limit', '-1');

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
$selectedAssessmentMode = 'normal';
$examAcademicYear = (int)date('Y');
	if (app_table_exists($conn, 'tbl_exams')) {
		$stmt = $conn->prepare("SELECT id, COALESCE(assessment_mode, 'normal') AS assessment_mode, academic_year FROM tbl_exams WHERE id = ? AND class_id = ? AND term_id = ? LIMIT 1");
		$stmt->execute([$examId, $classId, $termId]);
		$examRow = $stmt->fetch(PDO::FETCH_ASSOC);
		$examAllowed = $examRow && ((int)($examRow['id'] ?? 0) > 0);
		if ($examAllowed) {
			$selectedAssessmentMode = strtolower(trim((string)($examRow['assessment_mode'] ?? 'normal')));
			$examAcademicYear = (int)preg_replace('/[^0-9]/', '', (string)($examRow['academic_year'] ?? ''));
			if ($examAcademicYear < 1) {
				$examAcademicYear = (int)date('Y');
			}
		}
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
	$totalResults = 0;
	if ($useExamId) {
		if ($selectedAssessmentMode === 'consolidated' && app_table_exists($conn, 'tbl_exam_components')) {
			$stmt = $conn->prepare("SELECT component_exam_id FROM tbl_exam_components WHERE exam_id = ? ORDER BY component_exam_id");
			$stmt->execute([$examId]);
			$componentExamIds = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));

			if (!empty($componentExamIds)) {
				$placeholders = implode(',', array_fill(0, count($componentExamIds), '?'));
				$params = array_merge([$classId, $termId], $componentExamIds);
				if (app_table_exists($conn, 'tbl_exams')) {
					$stmt = $conn->prepare("SELECT COUNT(*)
						FROM tbl_exam_results er
						JOIN tbl_exams e ON e.id = er.exam_id
						WHERE er.class = ? AND er.term = ?
						AND er.exam_id IN ($placeholders)
						AND COALESCE(e.status, 'draft') = 'published'");
					$stmt->execute($params);
				} else {
					$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ? AND exam_id IN ($placeholders)");
					$stmt->execute($params);
				}
				$totalResults = (int)$stmt->fetchColumn();
			}
		} else {
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ? AND exam_id = ?");
			$stmt->execute([$classId, $termId, $examId]);
			$totalResults = (int)$stmt->fetchColumn();
		}
	} else {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ?");
		$stmt->execute([$classId, $termId]);
		$totalResults = (int)$stmt->fetchColumn();
	}

	$totalCbe = 0;
	if (app_table_exists($conn, 'tbl_cbe_assessments')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_cbe_assessments WHERE class_id = ? AND term_id = ?");
		$stmt->execute([$classId, $termId]);
		$totalCbe = (int)$stmt->fetchColumn();
	}

	if ($useExamId && app_table_exists($conn, 'tbl_exam_subjects')) {
		$stmt = $conn->prepare("SELECT es.subject_id, s.name
			FROM tbl_exam_subjects es
			LEFT JOIN tbl_subjects s ON s.id = es.subject_id
			WHERE es.exam_id = ?
			ORDER BY s.name");
		$stmt->execute([$examId]);
		$missingSubjects = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $subjectRow) {
			$subjectId = (int)($subjectRow['subject_id'] ?? 0);
			if ($subjectId < 1) {
				continue;
			}
			$check = $conn->prepare("SELECT COUNT(DISTINCT er.student)
				FROM tbl_exam_results er
				JOIN tbl_subject_combinations sc ON sc.id = er.subject_combination
				WHERE er.class = ? AND er.term = ? AND er.exam_id = ? AND sc.subject = ?");
			$check->execute([$classId, $termId, $examId, $subjectId]);
			$subjectCount = (int)$check->fetchColumn();
			if ($subjectCount < $totalStudents) {
				$subjectLabel = (string)($subjectRow['name'] ?? ('Subject ' . $subjectId));
				$reasonBits = [];

				if (app_table_exists($conn, 'tbl_teacher_assignments') && app_table_exists($conn, 'tbl_staff')) {
					$assignmentStmt = $conn->prepare("SELECT ta.teacher_id, st.level, st.fname, st.lname
						FROM tbl_teacher_assignments ta
						LEFT JOIN tbl_staff st ON st.id = ta.teacher_id
						WHERE ta.class_id = ? AND ta.subject_id = ? AND ta.term_id = ? AND ta.year = ?
						ORDER BY ta.id DESC
						LIMIT 1");
					$assignmentStmt->execute([$classId, $subjectId, $termId, $examAcademicYear]);
					$assignmentRow = $assignmentStmt->fetch(PDO::FETCH_ASSOC);
					if ($assignmentRow) {
						$assignedName = trim((string)($assignmentRow['fname'] ?? '') . ' ' . (string)($assignmentRow['lname'] ?? ''));
						if (!in_array((string)($assignmentRow['level'] ?? ''), ['0', '1', '2'], true)) {
							$reasonBits[] = $assignedName !== ''
								? 'assigned to non-instructional account ' . $assignedName
								: 'assigned to a non-instructional account';
						}
					} else {
						$reasonBits[] = 'no active teacher allocation';
					}
				}

				if (empty($reasonBits) && app_table_exists($conn, 'tbl_subject_combinations') && app_table_exists($conn, 'tbl_staff')) {
					$comboStmt = $conn->prepare("SELECT sc.teacher, st.level, st.fname, st.lname
						FROM tbl_subject_combinations sc
						LEFT JOIN tbl_staff st ON st.id = sc.teacher
						WHERE sc.subject = ? AND sc.class LIKE ?
						ORDER BY sc.id DESC
						LIMIT 1");
					$comboStmt->execute([$subjectId, '%"' . $classId . '"%']);
					$comboRow = $comboStmt->fetch(PDO::FETCH_ASSOC);
					if (!$comboRow) {
						$reasonBits[] = 'no subject combination for this class';
					} elseif (!in_array((string)($comboRow['level'] ?? ''), ['0', '1', '2'], true)) {
						$comboTeacherName = trim((string)($comboRow['fname'] ?? '') . ' ' . (string)($comboRow['lname'] ?? ''));
						$reasonBits[] = $comboTeacherName !== ''
							? 'subject combination points to non-instructional account ' . $comboTeacherName
							: 'subject combination points to a non-instructional account';
					}
				}

				$detail = '';
				if (!empty($reasonBits)) {
					$detail = '; ' . implode('; ', array_values(array_unique($reasonBits)));
				}
				$missingSubjects[] = $subjectLabel . ' (' . $subjectCount . '/' . $totalStudents . $detail . ')';
			}
		}
		if (!empty($missingSubjects)) {
			throw new RuntimeException('Report card generation stopped. These exam subjects still have missing marks: ' . implode(', ', $missingSubjects) . '.');
		}
	}

	if (($totalResults + $totalCbe) < 1) {
		if ($selectedAssessmentMode === 'consolidated') {
			throw new RuntimeException('No saved results were found for this consolidated exam. Ensure its source exams are published and contain marks for the selected class and term.');
		}
		throw new RuntimeException('No saved results were found for the selected class and term (exam results and CBE assessments are both empty).');
	}

	if (!app_table_exists($conn, 'tbl_report_cards')) {
		throw new RuntimeException('Report card support is not installed. Please run migrations.');
	}
	report_ensure_exam_batch_schema($conn);

	$generatedBy = isset($account_id) ? (int)$account_id : null;
	$meritList = report_class_merit_list($conn, $classId, $termId, $generatedBy, $examId);
	if (empty($meritList['rows'])) {
		throw new RuntimeException('No report cards could be generated for the selected class and term.');
	}

	try {
		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$classId]);
		$className = (string)$stmt->fetchColumn();
		$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
		$stmt->execute([$termId]);
		$termName = (string)$stmt->fetchColumn();
		$examName = '';
		if (app_table_exists($conn, 'tbl_exams')) {
			$stmt = $conn->prepare("SELECT name, academic_year FROM tbl_exams WHERE id = ? LIMIT 1");
			$stmt->execute([$examId]);
			$examMeta = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
			$examName = (string)($examMeta['name'] ?? '');
			$examAcademicYearLabel = trim((string)($examMeta['academic_year'] ?? ''));
		} else {
			$examAcademicYearLabel = '';
		}

		app_data_camp_store_record($conn, [
			'module_key' => 'report_batches',
			'record_type' => 'report_batch',
			'entity_table' => 'tbl_report_cards',
			'entity_id' => $classId . ':' . $termId . ':' . $examId,
			'title' => trim(($className !== '' ? $className : 'Class') . ' report batch - ' . ($termName !== '' ? $termName : ('Term ' . $termId))),
			'description' => trim('Generated and retained full report-card batch' . ($examName !== '' ? ' for ' . $examName : '')),
			'academic_year' => $examAcademicYearLabel,
			'class_id' => $classId,
			'owner_portal' => 'admin,headteacher,deputy_headteacher',
			'mime_type' => 'application/pdf',
			'status' => 'retained',
			'source_key' => 'report_batch:' . $classId . ':' . $termId . ':' . $examId,
			'created_by' => $generatedBy,
			'payload_json' => [
				'class_id' => $classId,
				'class_name' => $className,
				'term_id' => $termId,
				'term_name' => $termName,
				'exam_id' => $examId,
				'exam_name' => $examName,
				'total_students' => (int)($meritList['total_students'] ?? 0),
				'total_rows' => count((array)($meritList['rows'] ?? [])),
			],
		]);
	} catch (Throwable $archiveError) {
		error_log('[process_results/data_camp] ' . $archiveError->getMessage());
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

			app_system_notify($conn, $title, $message, [
				'audience' => 'class',
				'class_id' => $classId,
				'term_id' => $termId,
				'link' => 'report_card?term=' . $termId,
				'created_by' => $generatedBy,
				'module_name' => 'performance',
				'type' => 'success',
				'priority' => 82,
				'force_email' => true,
				'email_link' => 'parent/report_card',
			]);
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
