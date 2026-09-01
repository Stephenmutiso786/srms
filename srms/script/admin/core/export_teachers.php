<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/pdf_branding.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('staff.manage', '../import_export');

$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$schoolIdSelect = app_column_exists($conn, 'tbl_staff', 'school_id') ? 'school_id' : "'' AS school_id";
	$stmt = $conn->prepare("SELECT id, $schoolIdSelect, fname, lname, gender, email, level, status
		FROM tbl_staff
		WHERE level = 2
		ORDER BY fname, lname, id");
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($format === 'txt') {
		header('Content-Type: text/plain; charset=UTF-8');
		header('Content-Disposition: attachment; filename="teachers-for-paste.txt"');
		foreach ($rows as $row) {
			$name = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
			if ($name !== '') {
				echo $name . PHP_EOL;
			}
		}
		exit;
	}

	if ($format === 'pdf') {
		require_once('tcpdf/tcpdf.php');
		$pdf = new TCPDF();
		$pdf->SetCreator(APP_NAME);
		$pdf->SetTitle('Teachers Export');
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 10);
		$pdf->writeHTML(app_pdf_brand_header_html($conn, 'TEACHER EXPORT', 'Official teacher export listing for administrative and audit use', 52), true, false, true, false, '');
		$html = '<table border="1" cellpadding="4"><thead><tr><th>ID</th><th>School ID</th><th>Name</th><th>Gender</th><th>Email</th><th>Status</th></tr></thead><tbody>';
		foreach ($rows as $row) {
			$name = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
			$html .= '<tr>'
				. '<td>' . htmlspecialchars((string)$row['id']) . '</td>'
				. '<td>' . htmlspecialchars((string)($row['school_id'] ?? '')) . '</td>'
				. '<td>' . htmlspecialchars($name) . '</td>'
				. '<td>' . htmlspecialchars((string)($row['gender'] ?? '')) . '</td>'
				. '<td>' . htmlspecialchars((string)($row['email'] ?? '')) . '</td>'
				. '<td>' . ((int)($row['status'] ?? 0) === 1 ? 'Active' : 'Blocked') . '</td>'
				. '</tr>';
		}
		$html .= '</tbody></table>';
		$pdf->writeHTML($html, true, false, true, false, '');
		app_pdf_draw_official_footer($pdf, [
			'base_y' => $pdf->getPageHeight() - 24,
			'date_value' => date('Y-m-d'),
			'title' => 'Headteacher',
		]);
		$pdf->Output('teachers.pdf', 'D');
		exit;
	}

	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="teachers.csv"');
	$out = fopen('php://output', 'w');
	fputcsv($out, ['teacher_id', 'school_id', 'first_name', 'last_name', 'gender', 'email', 'status']);
	foreach ($rows as $row) {
		fputcsv($out, [
			$row['id'],
			$row['school_id'] ?? '',
			$row['fname'] ?? '',
			$row['lname'] ?? '',
			$row['gender'] ?? '',
			$row['email'] ?? '',
			((int)($row['status'] ?? 0) === 1 ? 'Active' : 'Blocked'),
		]);
	}
	fclose($out);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Teacher export failed: " . $e->getMessage()));
	header("location:../import_export");
}
