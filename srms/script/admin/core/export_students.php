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
$groupByClass = strtolower(trim($_GET['group'] ?? '')) === 'class';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$schoolIdSelect = app_column_exists($conn, 'tbl_students', 'school_id') ? 'st.school_id' : "'' AS school_id";
	$orderBy = $groupByClass ? 'class_name ASC, st.fname ASC, st.mname ASC, st.lname ASC, st.id ASC' : 'st.id ASC';
	$stmt = $conn->prepare("SELECT st.id, $schoolIdSelect, st.fname, st.mname, st.lname, concat_ws(' ', st.fname, st.mname, st.lname) AS name, st.gender, st.email, c.name AS class_name
		FROM tbl_students st
		LEFT JOIN tbl_classes c ON c.id = st.class
		ORDER BY $orderBy");
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$groupedRows = [];
	if ($groupByClass) {
		foreach ($rows as $row) {
			$className = trim((string)($row['class_name'] ?? ''));
			$groupedRows[$className !== '' ? $className : 'Unassigned'][] = $row;
		}
		ksort($groupedRows, SORT_NATURAL | SORT_FLAG_CASE);
	}

	if ($format === 'pdf') {
		require_once('tcpdf/tcpdf.php');
		$pdf = new TCPDF();
		$pdf->SetCreator(APP_NAME);
		$pdf->SetTitle($groupByClass ? 'Students Export by Class' : 'Students Export');
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 11);
		$brandingHeader = app_pdf_brand_header_html(
			$conn,
			$groupByClass ? 'STUDENT EXPORT BY CLASS' : 'STUDENT EXPORT',
			$groupByClass ? 'Official class-wise student roster for administrative and audit use' : 'Official student export listing for administrative and audit use',
			52
		);
		$pdf->writeHTML($brandingHeader, true, false, true, false, '');
		if ($groupByClass) {
			foreach ($groupedRows as $className => $classRows) {
				$pdf->Ln(2);
				$pdf->SetFont('helvetica', 'B', 12);
				$pdf->Cell(0, 8, $className, 0, 1, 'L');
				$pdf->SetFont('helvetica', '', 11);
				$tbl = '<table border="1" cellpadding="4"><thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Email</th></tr></thead><tbody>';
				foreach ($classRows as $r) {
					$tbl .= '<tr><td>'.htmlspecialchars($r['id']).'</td><td>'.htmlspecialchars($r['name']).'</td><td>'.htmlspecialchars($r['gender']).'</td><td>'.htmlspecialchars($r['email']).'</td></tr>';
				}
				$tbl .= '</tbody></table>';
				$pdf->writeHTML($tbl, true, false, true, false, '');
			}
		} else {
			$tbl = '<table border="1" cellpadding="4"><thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Email</th><th>Class</th></tr></thead><tbody>';
			foreach ($rows as $r) {
				$tbl .= '<tr><td>'.htmlspecialchars($r['id']).'</td><td>'.htmlspecialchars($r['name']).'</td><td>'.htmlspecialchars($r['gender']).'</td><td>'.htmlspecialchars($r['email']).'</td><td>'.htmlspecialchars($r['class_name'] ?? '').'</td></tr>';
			}
			$tbl .= '</tbody></table>';
			$pdf->writeHTML($tbl, true, false, true, false, '');
		}
		app_pdf_draw_official_footer($pdf, [
			'base_y' => $pdf->getPageHeight() - 24,
			'date_value' => date('Y-m-d'),
			'title' => 'Headteacher',
		]);
		$pdf->Output('students.pdf', 'D');
		exit;
	}

	header('Content-Type: text/csv');
	$filename = $groupByClass ? 'students-by-class.csv' : 'students.csv';
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	$out = fopen('php://output', 'w');
	if ($groupByClass) {
		fputcsv($out, ['class', 'admission_number', 'school_id', 'first_name', 'middle_name', 'last_name', 'gender', 'email']);
		foreach ($groupedRows as $className => $classRows) {
			foreach ($classRows as $r) {
				fputcsv($out, [$className, $r['id'], $r['school_id'] ?? '', $r['fname'], $r['mname'], $r['lname'], $r['gender'], $r['email']]);
			}
		}
	} else {
		fputcsv($out, ['admission_number','school_id','first_name','middle_name','last_name','gender','email','class']);
		foreach ($rows as $r) {
			fputcsv($out, [$r['id'], $r['school_id'] ?? '', $r['fname'], $r['mname'], $r['lname'], $r['gender'], $r['email'], $r['class_name']]);
		}
	}
	fclose($out);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Export failed: ".$e->getMessage()));
	header("location:../import_export");
}
