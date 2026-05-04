<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/pdf_branding.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('academic.manage', '../school_timetable');

$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);
$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));

if ($termId < 1 || !in_array($format, ['csv', 'pdf'], true)) {
	$_SESSION['reply'] = array(array('danger', 'Choose a valid term before downloading the timetable.'));
	header('location:../school_timetable');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_timetable_table($conn);

	$termStmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
	$termStmt->execute([$termId]);
	$termName = (string)($termStmt->fetchColumn() ?: ('Term ' . $termId));

	$className = 'Whole School';
	if ($classId > 0) {
		$classStmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$classStmt->execute([$classId]);
		$className = (string)($classStmt->fetchColumn() ?: ('Class ' . $classId));
	}

	$sql = "SELECT st.day_name, st.session_label, st.start_time, st.end_time, st.room,
		c.name AS class_name, sb.name AS subject_name, concat_ws(' ', t.fname, t.lname) AS teacher_name
		FROM tbl_school_timetable st
		JOIN tbl_classes c ON c.id = st.class_id
		JOIN tbl_subjects sb ON sb.id = st.subject_id
		JOIN tbl_staff t ON t.id = st.teacher_id
		WHERE st.term_id = ?";
	$params = [$termId];
	if ($classId > 0) {
		$sql .= " AND st.class_id = ?";
		$params[] = $classId;
	}
	$sql .= " ORDER BY c.name,
		CASE st.day_name
			WHEN 'Monday' THEN 1
			WHEN 'Tuesday' THEN 2
			WHEN 'Wednesday' THEN 3
			WHEN 'Thursday' THEN 4
			WHEN 'Friday' THEN 5
			WHEN 'Saturday' THEN 6
			WHEN 'Sunday' THEN 7
			ELSE 8 END,
		st.start_time";

	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (!$rows) {
		$_SESSION['reply'] = array(array('warning', 'No generated timetable was found to download for the selected filters.'));
		header('location:../school_timetable?term_id=' . $termId . ($classId > 0 ? '&class_id=' . $classId : ''));
		exit;
	}

	$safeTerm = preg_replace('/[^A-Za-z0-9_-]+/', '_', $termName);
	$safeClass = preg_replace('/[^A-Za-z0-9_-]+/', '_', $className);
	$fileBase = 'school_timetable_' . strtolower(trim($safeClass, '_')) . '_' . strtolower(trim($safeTerm, '_'));

	if ($format === 'pdf') {
		require_once('tcpdf/tcpdf.php');
		$pdf = new TCPDF('L', 'mm', 'A4');
		$pdf->SetCreator(APP_NAME);
		$pdf->SetTitle('School Timetable');
		$pdf->SetMargins(10, 10, 10);
		$pdf->SetAutoPageBreak(true, 10);
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 10);

		$subtitle = 'Generated timetable for ' . $className . ' - ' . $termName;
		$brandingHeader = app_pdf_brand_header_html($conn, 'SCHOOL TIMETABLE', $subtitle, 44);
		$pdf->writeHTML($brandingHeader, true, false, true, false, '');

		$table = '<table border="1" cellpadding="4">
			<thead>
				<tr style="background-color:#eef6ff;font-weight:bold;">
					<th width="12%">Class</th>
					<th width="11%">Day</th>
					<th width="12%">Session</th>
					<th width="10%">Start</th>
					<th width="10%">End</th>
					<th width="19%">Subject</th>
					<th width="16%">Teacher</th>
					<th width="10%">Room</th>
				</tr>
			</thead>
			<tbody>';
		foreach ($rows as $row) {
			$table .= '<tr>'
				. '<td>' . htmlspecialchars((string)$row['class_name']) . '</td>'
				. '<td>' . htmlspecialchars((string)$row['day_name']) . '</td>'
				. '<td>' . htmlspecialchars((string)$row['session_label']) . '</td>'
				. '<td>' . htmlspecialchars(substr((string)$row['start_time'], 0, 5)) . '</td>'
				. '<td>' . htmlspecialchars(substr((string)$row['end_time'], 0, 5)) . '</td>'
				. '<td>' . htmlspecialchars((string)$row['subject_name']) . '</td>'
				. '<td>' . htmlspecialchars((string)$row['teacher_name']) . '</td>'
				. '<td>' . htmlspecialchars((string)($row['room'] ?? '')) . '</td>'
				. '</tr>';
		}
		$table .= '</tbody></table>';

		$pdf->writeHTML($table, true, false, true, false, '');
		$pdf->Output($fileBase . '.pdf', 'D');
		exit;
	}

	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="' . $fileBase . '.csv"');
	$out = fopen('php://output', 'w');
	fputcsv($out, ['class', 'term', 'day', 'session', 'start_time', 'end_time', 'subject', 'teacher', 'room']);
	foreach ($rows as $row) {
		fputcsv($out, [
			(string)$row['class_name'],
			$termName,
			(string)$row['day_name'],
			(string)$row['session_label'],
			substr((string)$row['start_time'], 0, 5),
			substr((string)$row['end_time'], 0, 5),
			(string)$row['subject_name'],
			(string)$row['teacher_name'],
			(string)($row['room'] ?? ''),
		]);
	}
	fclose($out);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Timetable download failed: ' . $e->getMessage()));
	header('location:../school_timetable?term_id=' . $termId . ($classId > 0 ? '&class_id=' . $classId : ''));
	exit;
}
