<?php
require_once('db/config.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('tcpdf/tcpdf.php');

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($code === '') {
	http_response_code(400);
	exit('Missing verification code.');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT * FROM tbl_report_cards WHERE verification_code = ? LIMIT 1");
	$stmt->execute([$code]);
	$card = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$card) {
		http_response_code(404);
		exit('Report not found.');
	}

	$stmt = $conn->prepare("SELECT id, school_id, fname, mname, lname, class FROM tbl_students WHERE id = ? LIMIT 1");
	$stmt->execute([(string)$card['student_id']]);
	$student = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$student) {
		http_response_code(404);
		exit('Student not found.');
	}

	$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
	$stmt->execute([(int)$card['class_id']]);
	$className = (string)($stmt->fetchColumn() ?: '');

	$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
	$stmt->execute([(int)$card['term_id']]);
	$termName = (string)($stmt->fetchColumn() ?: '');

	$studentId = (string)$card['student_id'];
	$classId = (int)$card['class_id'];
	$termId = (int)$card['term_id'];
	$attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
	$feesBalance = report_fees_balance($conn, $studentId, $termId);

	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	app_output_single_page_report_pdf($conn, $pdf, [
		'student_id' => $studentId,
		'student_name' => trim((string)($student['fname'] ?? '') . ' ' . (string)($student['mname'] ?? '') . ' ' . (string)($student['lname'] ?? '')),
		'school_id' => (string)($student['school_id'] ?? $studentId),
		'class_name' => $className,
		'term_name' => $termName,
		'attendance' => $attendance,
		'fees_balance' => $feesBalance,
		'card' => $card,
		'exam_summary' => [
			'exam_name' => 'Published Results',
			'grade' => (string)($card['grade'] ?? 'N/A'),
			'mean_points' => (float)($card['mean'] ?? 0),
		],
	]);

	$studentToken = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string)($student['fname'] ?? '') . '_' . (string)($student['lname'] ?? '')));
	$classToken = preg_replace('/[^A-Za-z0-9_-]+/', '_', $className !== '' ? $className : 'Class');
	$termToken = preg_replace('/[^A-Za-z0-9_-]+/', '_', $termName !== '' ? $termName : 'Term');
	$fileName = $studentToken . '_' . $classToken . '_' . $termToken . '_Report.pdf';
	$pdf->Output($fileName, 'I');
	exit;
} catch (Throwable $e) {
	http_response_code(500);
	exit('Unable to generate report PDF.');
}
