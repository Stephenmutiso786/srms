<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/pdf_branding.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('students.manage', '../import_export');

$format = strtolower(trim($_GET['format'] ?? 'csv'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$schoolIdSelect = app_column_exists($conn, 'tbl_students', 'school_id') ? 'st.school_id' : "'' AS school_id";
	$stmt = $conn->prepare("SELECT st.id, $schoolIdSelect, st.fname, st.mname, st.lname, concat_ws(' ', st.fname, st.mname, st.lname) AS name, st.gender, st.email, c.name AS class_name
		FROM tbl_students st
		LEFT JOIN tbl_classes c ON c.id = st.class
		ORDER BY st.id");
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($format === 'pdf') {
		require_once('tcpdf/tcpdf.php');
		$pdf = new TCPDF();
		$pdf->SetCreator(APP_NAME);
		$pdf->SetTitle('Students Export');
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 11);
		$brandingHeader = app_pdf_brand_header_html($conn, 'STUDENT EXPORT', 'Official student export listing for administrative and audit use', 52);
		$pdf->writeHTML($brandingHeader, true, false, true, false, '');
		$tbl = '<table border="1" cellpadding="4"><thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Email</th><th>Class</th></tr></thead><tbody>';
		foreach ($rows as $r) {
			$tbl .= '<tr><td>'.htmlspecialchars($r['id']).'</td><td>'.htmlspecialchars($r['name']).'</td><td>'.htmlspecialchars($r['gender']).'</td><td>'.htmlspecialchars($r['email']).'</td><td>'.htmlspecialchars($r['class_name'] ?? '').'</td></tr>';
		}
		$tbl .= '</tbody></table>';
		$pdf->writeHTML($tbl, true, false, true, false, '');
		app_pdf_draw_official_footer($pdf, [
			'base_y' => $pdf->getPageHeight() - 24,
			'date_value' => date('Y-m-d'),
			'title' => 'Headteacher',
		]);
		$pdf->Output('students.pdf', 'D');
		exit;
	}

	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="students.csv"');
	$out = fopen('php://output', 'w');
	fputcsv($out, ['admission_number','school_id','first_name','middle_name','last_name','gender','email','class']);
	foreach ($rows as $r) {
		fputcsv($out, [$r['id'], $r['school_id'] ?? '', $r['fname'], $r['mname'], $r['lname'], $r['gender'], $r['email'], $r['class_name']]);
	}
	fclose($out);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Export failed: ".$e->getMessage()));
	header("location:../import_export");
}
