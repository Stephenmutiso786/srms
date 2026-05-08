<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('results.enter', 'admin');

$importSummary = null;
$uploadError = null;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_kpsea_results_table($conn);

	// Get subjects for validation
	$stmt = $conn->prepare("SELECT id, name, code FROM tbl_subjects ORDER BY name");
	$stmt->execute();
	$subjectsMap = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $subj) {
		$subjectsMap[(int)$subj['id']] = $subj;
	}

	// Get students by admission number
	$stmt = $conn->prepare("SELECT id, admission_number FROM tbl_students");
	$stmt->execute();
	$studentsMap = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
		$studentsMap[trim($student['admission_number'])] = (int)$student['id'];
	}

} catch (Throwable $e) {
	$uploadError = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['kpsea_csv'])) {
	try {
		$file = $_FILES['kpsea_csv'];
		if ($file['error'] !== UPLOAD_ERR_OK) {
			throw new RuntimeException("File upload error: " . $file['error']);
		}

		if ($file['size'] === 0) {
			throw new RuntimeException("File is empty.");
		}

		if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
			throw new RuntimeException("File is too large (max 5MB).");
		}

		$mimeType = mime_content_type($file['tmp_name']);
		if (strpos($mimeType, 'text/') === false && strpos($mimeType, 'spreadsheet') === false) {
			throw new RuntimeException("Invalid file type. Please upload a CSV file.");
		}

		$handle = fopen($file['tmp_name'], 'r');
		if ($handle === false) {
			throw new RuntimeException("Could not open file for reading.");
		}

		// Read header row
		$header = fgetcsv($handle);
		if (!$header) {
			throw new RuntimeException("CSV file is empty or invalid.");
		}

		// Normalize header names
		$header = array_map('strtolower', $header);
		$header = array_map('trim', $header);

		// Find column indices (flexible mapping)
		$admissionIdx = array_search('admission_number', $header);
		if ($admissionIdx === false) {
			$admissionIdx = array_search('admission', $header);
		}
		if ($admissionIdx === false) {
			$admissionIdx = array_search('student_id', $header);
		}
		if ($admissionIdx === false) {
			$admissionIdx = null;
		}

		$subjectIdx = array_search('subject_id', $header);
		if ($subjectIdx === false) {
			$subjectIdx = array_search('subject', $header);
		}
		if ($subjectIdx === false) {
			$subjectIdx = null;
		}

		$scoreIdx = array_search('score', $header);
		if ($scoreIdx === false) {
			$scoreIdx = array_search('marks', $header);
		}
		if ($scoreIdx === false) {
			$scoreIdx = array_search('mark', $header);
		}
		if ($scoreIdx === false) {
			$scoreIdx = null;
		}

		$yearIdx = array_search('exam_session_year', $header);
		if ($yearIdx === false) {
			$yearIdx = array_search('year', $header);
		}
		if ($yearIdx === false) {
			$yearIdx = null;
		}

		if ($admissionIdx === null || $subjectIdx === null || $scoreIdx === null) {
			throw new RuntimeException("CSV missing required columns: admission_number, subject_id, score. Found: " . implode(', ', $header));
		}

		// Default year to current year if not in CSV
		$defaultYear = date('Y');

		// Parse records
		$imported = 0;
		$skipped = 0;
		$errors = [];
		$insertStmt = $conn->prepare("
			INSERT INTO tbl_kpsea_results (student_id, subject_id, score, exam_session_year)
			VALUES (?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE score = VALUES(score)
		");

		$rowNum = 1;
		while (($row = fgetcsv($handle)) !== false) {
			$rowNum++;

			if (empty($row) || (count($row) === 1 && $row[0] === '')) {
				continue;
			}

			$admission = isset($row[$admissionIdx]) ? trim((string)$row[$admissionIdx]) : '';
			$subjectId = isset($row[$subjectIdx]) ? (int)$row[$subjectIdx] : 0;
			$score = isset($row[$scoreIdx]) ? floatval($row[$scoreIdx]) : 0;
			$year = isset($row[$yearIdx]) && !empty($row[$yearIdx]) ? (int)$row[$yearIdx] : $defaultYear;

			// Validation
			if (empty($admission)) {
				$errors[] = "Row $rowNum: Missing admission number";
				$skipped++;
				continue;
			}

			if (!isset($studentsMap[$admission])) {
				$errors[] = "Row $rowNum: Student with admission number '$admission' not found";
				$skipped++;
				continue;
			}

			if ($subjectId < 1 || !isset($subjectsMap[$subjectId])) {
				$errors[] = "Row $rowNum: Invalid subject ID $subjectId";
				$skipped++;
				continue;
			}

			if ($score < 0 || $score > 100) {
				$errors[] = "Row $rowNum: Score must be between 0-100 (got $score)";
				$skipped++;
				continue;
			}

			// Insert record
			$studentId = $studentsMap[$admission];
			$insertStmt->execute([$studentId, $subjectId, $score, $year]);
			$imported++;
		}

		fclose($handle);

		$message = "KPSEA results imported: $imported records added/updated";
		if ($skipped > 0) {
			$message .= ", $skipped records skipped.";
		}

		$_SESSION['reply'] = array (array("success", $message));
		if (!empty($errors) && count($errors) <= 10) {
			foreach ($errors as $err) {
				$_SESSION['reply'][] = array("warning", "  → " . $err);
			}
		} elseif (!empty($errors)) {
			$_SESSION['reply'][] = array("warning", "  → " . count($errors) . " validation errors (showing first 10)");
			for ($i = 0; $i < min(10, count($errors)); $i++) {
				$_SESSION['reply'][] = array("warning", "     " . $errors[$i]);
			}
		}

		app_audit_log($conn, $_SESSION['account_id'] ?? null, 'kpsea_results.import', "Imported: $imported, Skipped: $skipped", 'admin');

	} catch (Throwable $e) {
		$uploadError = $e->getMessage();
		$_SESSION['reply'] = array (array("danger", "Import failed: " . $uploadError));
	}

	header("location:kpsea_import.php");
	exit;
}

?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>KPSEA Results Import - ELIMU HUB</title>
	<link href="../cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="../css/app.css?t=<?php echo filemtime('../css/app.css'); ?>">
</head>
<body>
<div class="container-fluid">
	<?php require_once('partials/topbar.php'); ?>
	<div class="row g-0">
		<?php require_once('partials/sidebar.php'); ?>
		<main class="col-lg-10 ms-lg-auto p-4">
			<div class="page-header mb-4">
				<h1>KPSEA Results Import</h1>
				<p class="text-muted">Bulk import Kenya Primary School Examination (KPSEA) results from CSV file</p>
			</div>

			<?php if (isset($_SESSION['reply']) && is_array($_SESSION['reply'])) {
				foreach ($_SESSION['reply'] as $reply) {
					echo '<div class="alert alert-' . htmlspecialchars($reply[0]) . ' alert-dismissible fade show" role="alert">';
					echo htmlspecialchars($reply[1]);
					echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
				}
				unset($_SESSION['reply']);
			} ?>

			<div class="row">
				<div class="col-lg-8">
					<div class="card">
						<div class="card-header bg-light">
							<h5 class="mb-0">Upload CSV File</h5>
						</div>
						<div class="card-body">
							<form method="POST" enctype="multipart/form-data" class="needs-validation">
								<div class="mb-3">
									<label for="kpsea_csv" class="form-label">CSV File</label>
									<input type="file" class="form-control" id="kpsea_csv" name="kpsea_csv" accept=".csv,.txt" required>
									<div class="form-text">
										Select a CSV file containing KPSEA results (max 5MB)
									</div>
								</div>

								<button type="submit" class="btn btn-primary">
									<i class="bi bi-upload"></i> Import Results
								</button>
							</form>
						</div>
					</div>
				</div>

				<div class="col-lg-4">
					<div class="card">
						<div class="card-header bg-light">
							<h5 class="mb-0">CSV Format</h5>
						</div>
						<div class="card-body">
							<p class="small mb-3">Your CSV file should have these columns (in any order):</p>
							<ul class="small">
								<li><code>admission_number</code> (required)</li>
								<li><code>subject_id</code> (required)</li>
								<li><code>score</code> (required, 0-100)</li>
								<li><code>exam_session_year</code> (optional, defaults to current year)</li>
							</ul>

							<hr>

							<p class="small mb-2"><strong>Example CSV:</strong></p>
							<pre class="small bg-light p-2" style="font-size: 11px;">admission_number,subject_id,score,year
KYS001,1,85,2026
KYS002,1,92,2026
KYS003,1,78,2026</pre>

							<hr>

							<p class="small text-muted mb-0">
								<i class="bi bi-info-circle"></i> 
								Subject IDs and student admission numbers must match those in the system.
							</p>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>

<script src="../cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
