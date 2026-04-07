<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('students.manage', '../import_export');

$format = strtolower(trim($_GET['format'] ?? 'csv'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT st.id, concat_ws(' ', st.fname, st.mname, st.lname) AS name, st.gender, st.email, c.name AS class_name
		FROM tbl_students st
		LEFT JOIN tbl_classes c ON c.id = st.class
		ORDER BY st.id");
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($format === 'pdf') {
		require_once('tcpdf/tcpdf.php');
		$pdf = new TCPDF();
		$pdf->SetCreator('Elimu Hub');
		$pdf->SetTitle('Students Export');
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 11);
		$pdf->Write(0, 'Students List', '', 0, 'L', true, 0, false, false, 0);
		$tbl = '<table border="1" cellpadding="4"><thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Email</th><th>Class</th></tr></thead><tbody>';
		foreach ($rows as $r) {
			$tbl .= '<tr><td>'.htmlspecialchars($r['id']).'</td><td>'.htmlspecialchars($r['name']).'</td><td>'.htmlspecialchars($r['gender']).'</td><td>'.htmlspecialchars($r['email']).'</td><td>'.htmlspecialchars($r['class_name'] ?? '').'</td></tr>';
		}
		$tbl .= '</tbody></table>';
		$pdf->writeHTML($tbl, true, false, true, false, '');
		$pdf->Output('students.pdf', 'D');
		exit;
	}

	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="students.csv"');
	$out = fopen('php://output', 'w');
	fputcsv($out, ['student_id','name','gender','email','class']);
	foreach ($rows as $r) {
		fputcsv($out, [$r['id'], $r['name'], $r['gender'], $r['email'], $r['class_name']]);
	}
	fclose($out);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Export failed: ".$e->getMessage()));
	header("location:../import_export");
}
