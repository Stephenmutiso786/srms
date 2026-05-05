<?php
/**
 * KJSEA Final Score Computation Handler
 * 
 * Called when a KJSEA exam is finalized.
 * Computes final scores: (SBA_avg * 0.30) + (exam_score * 0.70)
 * Stores results in tbl_exam_results with kjsea_final_score field
 */

function app_compute_and_store_kjsea_final_scores(PDO $conn, int $examId, int $classId, int $termId) {
	try {
		// Verify exam is KJSEA
		$stmt = $conn->prepare("SELECT assessment_mode FROM tbl_exams WHERE id = ? LIMIT 1");
		$stmt->execute([$examId]);
		$assessmentMode = $stmt->fetchColumn();
		
		if (strtoupper($assessmentMode) !== 'KJSEA') {
			return false; // Not a KJSEA exam
		}

		// Ensure the exam results table has the kjsea_final_score column
		if (!app_column_exists($conn, 'tbl_exam_results', 'kjsea_final_score')) {
			$stmt = $conn->prepare("ALTER TABLE tbl_exam_results ADD COLUMN kjsea_final_score DECIMAL(5, 2) DEFAULT NULL");
			try {
				$stmt->execute();
			} catch (PDOException $e) {
				if (strpos($e->getMessage(), 'Duplicate column') === false) {
					throw $e;
				}
			}
		}

		// Get all students in the class
		$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ? ORDER BY id");
		$stmt->execute([$classId]);
		$studentIds = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));

		if (empty($studentIds)) {
			return false; // No students
		}

		$computedCount = 0;
		$year = date('Y');

		foreach ($studentIds as $studentId) {
			// Get SBA average (Grade 7 & 8)
			$sbaScores = app_get_sba_scores($conn, $studentId, 7, null);
			if (empty($sbaScores)) {
				$sbaScores = app_get_sba_scores($conn, $studentId, 8, null);
			}

			if (empty($sbaScores)) {
				// No SBA data for this student
				continue;
			}

			$sbaAverage = array_sum($sbaScores) / count($sbaScores);

			// Get exam mark for this student
			$stmt = $conn->prepare("
				SELECT MAX(mark) as exam_mark
				FROM tbl_exam_results
				WHERE student = ? AND exam_id = ? AND subject_combination = ?
				LIMIT 1
			");
			$stmt->execute([$studentId, $examId, 0]); // Note: This is simplified; adjust based on actual subject_combination tracking
			$examMark = (float)($stmt->fetch(PDO::FETCH_ASSOC)['exam_mark'] ?? 0);

			if ($examMark === 0) {
				// Try alternative query without subject_combination filter
				$stmt = $conn->prepare("
					SELECT MAX(mark) as exam_mark
					FROM tbl_exam_results
					WHERE student = ? AND exam_id = ?
					LIMIT 1
				");
				$stmt->execute([$studentId, $examId]);
				$examMark = (float)($stmt->fetch(PDO::FETCH_ASSOC)['exam_mark'] ?? 0);
			}

			if ($examMark === 0) {
				// No exam mark for this student
				continue;
			}

			// Compute final score: SBA 30% + Exam 70%
			$finalScore = ($sbaAverage * 0.30) + ($examMark * 0.70);

			// Update exam results with final score
			$stmt = $conn->prepare("
				UPDATE tbl_exam_results
				SET kjsea_final_score = ?
				WHERE student = ? AND exam_id = ?
			");
			$stmt->execute([$finalScore, $studentId, $examId]);

			$computedCount++;
		}

		// Log the computation
		app_audit_log($conn, null, 'kjsea_scores.compute', "Exam $examId: Computed $computedCount final scores", 'system');

		return $computedCount;

	} catch (Throwable $e) {
		error_log('[app_compute_and_store_kjsea_final_scores] Error: ' . $e->getMessage());
		return false;
	}
}
