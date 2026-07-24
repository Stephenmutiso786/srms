<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('results.enter', 'admin');

$selectedGrade = (int)($_GET['grade'] ?? 0);
$selectedSubject = (int)($_GET['subject_id'] ?? 0);
$selectedTerm = (int)($_GET['term_id'] ?? 0);

$subjects = [];
$terms = [];
$students = [];
$existingScores = [];
$classes = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_sba_scores_table($conn);

	// Get all terms
	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Get all subjects
	$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
	$stmt->execute();
	$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Get classes (Grade 7 and Grade 8 only)
	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE LOWER(name) LIKE ? OR LOWER(name) LIKE ? ORDER BY id");
	$stmt->execute(['%grade 7%', '%form 1%']);
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE LOWER(name) LIKE ? OR LOWER(name) LIKE ? ORDER BY id");
	$stmt->execute(['%grade 8%', '%form 2%']);
	$classes = array_merge($classes, $stmt->fetchAll(PDO::FETCH_ASSOC));

	// Get students if grade, subject, and term selected
	if ($selectedGrade > 0 && $selectedSubject > 0 && $selectedTerm > 0) {
		// Find classes matching the selected grade
		$gradeClasses = [];
		foreach ($classes as $cls) {
			if (($selectedGrade === 7 && (stripos($cls['name'], 'grade 7') !== false || stripos($cls['name'], 'form 1') !== false)) ||
			    ($selectedGrade === 8 && (stripos($cls['name'], 'grade 8') !== false || stripos($cls['name'], 'form 2') !== false))) {
				$gradeClasses[] = (int)$cls['id'];
			}
		}

		if (!empty($gradeClasses)) {
			// Get students from these classes
			$classPlaceholders = implode(',', array_fill(0, count($gradeClasses), '?'));
			$stmt = $conn->prepare("
				SELECT DISTINCT st.id, st.first_name, st.middle_name, st.last_name, st.admission_number
				FROM tbl_students st
				WHERE st.class_id IN ($classPlaceholders)
				ORDER BY st.admission_number, st.first_name
			");
			$stmt->execute($gradeClasses);
			$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get existing scores
			if (!empty($students)) {
				$studentIds = array_column($students, 'id');
				$studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
				$params = array_merge($studentIds, [$selectedGrade, $selectedSubject, $selectedTerm]);
				$stmt = $conn->prepare("
					SELECT student_id, score
					FROM tbl_sba_scores
					WHERE student_id IN ($studentPlaceholders)
					AND grade = ?
					AND subject_id = ?
					AND term_id = ?
				");
				$stmt->execute($params);
				foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
					$existingScores[(int)$row['student_id']] = (float)$row['score'];
				}
			}
		}
	}

} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Error: " . $e->getMessage()));
}

?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>SBA Score Entry - ELIMU HUB</title>
	<base href="../">
	<link rel="stylesheet" type="text/css" href="css/main.css">
	<link rel="icon" href="images/icon.ico">
	<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
	<link href="../cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
			<div class="app-title">
				<div>
				<h1>SBA Score Entry</h1>
				<p class="text-muted">Enter School-Based Assessment (SBA) scores for Grade 7 & 8 students</p>
				</div>
			</div>

			<?php if (isset($_SESSION['reply']) && is_array($_SESSION['reply'])) {
				foreach ($_SESSION['reply'] as $reply) {
					echo '<div class="alert alert-' . htmlspecialchars($reply[0]) . ' alert-dismissible fade show" role="alert">';
					echo htmlspecialchars($reply[1]);
					echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
				}
				unset($_SESSION['reply']);
			} ?>

			<div class="card">
				<div class="card-body">
					<form method="GET" class="row g-3 mb-4">
						<div class="col-md-3">
							<label class="form-label">Grade</label>
							<select class="form-select" name="grade" id="gradeSelect" required onchange="this.form.submit()">
								<option value="">-- Select Grade --</option>
								<option value="7" <?php echo $selectedGrade === 7 ? 'selected' : ''; ?>>Grade 7</option>
								<option value="8" <?php echo $selectedGrade === 8 ? 'selected' : ''; ?>>Grade 8</option>
							</select>
						</div>
						<div class="col-md-4">
							<label class="form-label">Subject</label>
							<select class="form-select" name="subject_id" id="subjectSelect" required onchange="this.form.submit()">
								<option value="">-- Select Subject --</option>
								<?php foreach ($subjects as $subj): ?>
									<option value="<?php echo (int)$subj['id']; ?>" <?php echo $selectedSubject === (int)$subj['id'] ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($subj['name']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label">Term</label>
							<select class="form-select" name="term_id" id="termSelect" required onchange="this.form.submit()">
								<option value="">-- Select Term --</option>
								<?php foreach ($terms as $term): ?>
									<option value="<?php echo (int)$term['id']; ?>" <?php echo $selectedTerm === (int)$term['id'] ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($term['name']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</form>

					<?php if ($selectedGrade > 0 && $selectedSubject > 0 && $selectedTerm > 0): ?>
						<?php if (empty($students)): ?>
							<div class="alert alert-info">
								No students found for Grade <?php echo $selectedGrade; ?> in the selected subject.
							</div>
						<?php else: ?>
							<form method="POST" action="core/save_sba_scores.php" class="needs-validation">
								<input type="hidden" name="grade" value="<?php echo (int)$selectedGrade; ?>">
								<input type="hidden" name="subject_id" value="<?php echo (int)$selectedSubject; ?>">
								<input type="hidden" name="term_id" value="<?php echo (int)$selectedTerm; ?>">

								<div class="table-responsive">
									<table class="table table-striped table-hover">
										<thead class="table-light">
											<tr>
												<th>Admission #</th>
												<th>Student Name</th>
												<th style="width: 120px;">Score (0-100)</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($students as $student): ?>
												<?php $studentId = (int)$student['id'];
													  $existingScore = $existingScores[$studentId] ?? '';
												?>
												<tr>
													<td><code><?php echo htmlspecialchars($student['admission_number']); ?></code></td>
													<td>
														<?php echo htmlspecialchars($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']); ?>
													</td>
													<td>
														<input type="number" 
															name="scores[<?php echo $studentId; ?>]" 
															class="form-control form-control-sm" 
															min="0" max="100" step="0.01"
															value="<?php echo $existingScore !== '' ? htmlspecialchars((string)$existingScore) : ''; ?>"
															placeholder="0"
															data-student-id="<?php echo $studentId; ?>">
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>

								<div class="d-flex gap-2 mt-4">
									<button type="submit" class="btn btn-primary">
										<i class="bi bi-check-circle"></i> Save Scores
									</button>
									<a href="sba_entry.php" class="btn btn-secondary">Reset</a>
								</div>
							</form>
						<?php endif; ?>
					<?php else: ?>
						<div class="alert alert-secondary">
							Select a grade, subject, and term to view and enter SBA scores.
						</div>
					<?php endif; ?>
				</div>
			</div>
		</main>

<script src="../cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
