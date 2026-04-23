<?php
session_start();
chdir('../../');
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$classId = (int)($_POST['id'] ?? 0);
	$termId = (int)($_POST['term_id'] ?? 0);

	?><option selected disabled value="">Select One</option><?php
	if ($classId < 1 || $termId < 1) {
		exit;
	}

	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$examOptions = report_term_exam_options($conn, $classId, $termId);
		if (empty($examOptions)) {
			?><option selected disabled value="">No published exam available</option><?php
			exit;
		}
		foreach ($examOptions as $exam) {
			?><option value="<?php echo (int)$exam['id']; ?>"><?php echo htmlspecialchars($exam['name'] . ' [' . strtoupper((string)$exam['status']) . ']'); ?></option><?php
		}
	} catch (Throwable $e) {
		error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
		?><option selected disabled value="">Unable to load exams</option><?php
	}
	return;
}

header('location:../');
