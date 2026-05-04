<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/notify.php');
require_once('const/rbac.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../../admin/report");
	exit;
}

if ($res != "1" || $level != "0") {
	$_SESSION['reply'] = array(array("danger", "Unauthorized"));
	header("location:../../admin/report");
	exit;
}

app_require_permission('report.generate', 'admin');
app_require_unlocked('reports', 'admin');

$class_id = (int)($_POST['class_id'] ?? 0);
$term_id = (int)($_POST['term_id'] ?? 0);
$recipient_type = trim((string)($_POST['recipient_type'] ?? 'students'));
$include_parents = (int)($_POST['include_parents'] ?? 0);

if ($class_id <= 0 || $term_id <= 0) {
	$_SESSION['reply'] = array(array("danger", "Please select class and term"));
	header("location:../../admin/report");
	exit;
}

$sent_count = 0;
$failed_count = 0;
$errors = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	app_ensure_school_roles($conn);
	
	// Fetch class and term info
	$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ?");
	$stmt->execute([$class_id]);
	$classRow = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$classRow) {
		throw new Exception("Class not found");
	}
	$className = $classRow['name'];
	
	$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ?");
	$stmt->execute([$term_id]);
	$termRow = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$termRow) {
		throw new Exception("Term not found");
	}
	$termName = $termRow['name'];
	
	// Fetch app settings
	$appSettings = definition_of_school();
	$schoolName = $appSettings['name'] ?? 'School';
	
	// Get students in this class with report cards
	$hasStudentEmail = app_column_exists($conn, 'tbl_students', 'email');
	$hasParentEmail = app_column_exists($conn, 'tbl_parents', 'email');
	
	$studentQuery = "SELECT DISTINCT s.id, s.fname, s.mname, s.lname" . ($hasStudentEmail ? ', s.email' : '') . "
		FROM tbl_students s
		INNER JOIN tbl_report_cards rc ON rc.student_id = s.id
		WHERE rc.class_id = ? AND rc.term_id = ?
		ORDER BY s.fname, s.lname";
	
	$stmt = $conn->prepare($studentQuery);
	$stmt->execute([$class_id, $term_id]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	if (empty($students)) {
		$_SESSION['reply'] = array(array("warning", "No students with report cards in this class for this term"));
		header("location:../../admin/report");
		exit;
	}
	
	// Send emails to students
	if ($recipient_type === 'students' || $recipient_type === 'both') {
		if ($hasStudentEmail) {
			foreach ($students as $student) {
				$studentEmail = trim((string)($student['email'] ?? ''));
				if ($studentEmail === '' || !filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
					$failed_count++;
					continue;
				}
				
				$studentName = trim(implode(' ', array_filter([
					$student['fname'] ?? '',
					$student['mname'] ?? '',
					$student['lname'] ?? ''
				])));
				
				$subject = "{$schoolName} - Results for {$termName}";
				$message = "<p>Dear {$studentName},</p>
<p>Your {$termName} results for {$className} are now available.</p>
<p><strong>{$termName} Results Summary</strong></p>
<ul>
	<li>Class: {$className}</li>
	<li>Term: {$termName}</li>
	<li>Date Generated: " . date('Y-m-d H:i') . "</li>
</ul>
<p>Please log in to your account to view your full report card.</p>
<p>If you have any questions about your results, please contact your class teacher or administration.</p>
<p>Best regards,<br>{$schoolName} Administration</p>";
				
				$result = app_send_email($conn, $studentEmail, $subject, $message, []);
				if ($result['ok']) {
					$sent_count++;
				} else {
					$failed_count++;
					$errors[] = "{$studentName} ({$studentEmail}): " . $result['error'];
				}
			}
		} else {
			$_SESSION['reply'] = array(array("warning", "Students table does not have email column"));
		}
	}
	
	// Send emails to parents
	if ($recipient_type === 'parents' || $recipient_type === 'both' || $include_parents) {
		if ($hasParentEmail) {
			// Fetch parents of students in this class
			$parentQuery = "SELECT DISTINCT p.id, p.fname, p.lname, p.email
				FROM tbl_parents p
				INNER JOIN tbl_students s ON (p.id = s.parent_id OR p.id = s.parent2_id)
				INNER JOIN tbl_report_cards rc ON rc.student_id = s.id
				WHERE rc.class_id = ? AND rc.term_id = ? AND p.email IS NOT NULL AND p.email != ''
				ORDER BY p.fname, p.lname";
			
			$stmt = $conn->prepare($parentQuery);
			$stmt->execute([$class_id, $term_id]);
			$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			foreach ($parents as $parent) {
				$parentEmail = trim((string)($parent['email'] ?? ''));
				if ($parentEmail === '' || !filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
					$failed_count++;
					continue;
				}
				
				$parentName = trim(implode(' ', array_filter([
					$parent['fname'] ?? '',
					$parent['lname'] ?? ''
				])));
				
				$subject = "{$schoolName} - Student Results for {$termName}";
				$message = "<p>Dear {$parentName},</p>
<p>Your child's {$termName} results for {$className} are now available.</p>
<p><strong>Results Information</strong></p>
<ul>
	<li>Class: {$className}</li>
	<li>Term: {$termName}</li>
	<li>Date Generated: " . date('Y-m-d H:i') . "</li>
</ul>
<p>Please log in to your parent account or request your student's report card to view the full details.</p>
<p>If you have any questions about your child's results, please contact the school administration.</p>
<p>Best regards,<br>{$schoolName} Administration</p>";
				
				$result = app_send_email($conn, $parentEmail, $subject, $message, []);
				if ($result['ok']) {
					$sent_count++;
				} else {
					$failed_count++;
					$errors[] = "{$parentName} ({$parentEmail}): " . $result['error'];
				}
			}
		}
	}
	
	// Log the operation
	if (app_table_exists($conn, 'tbl_audit_log') && isset($_SESSION['id'])) {
		$action = "{$recipient_type} emails for {$className}/{$termName}: {$sent_count} sent";
		app_audit_log($conn, 'staff', $_SESSION['id'], 'report.email_sent', 'report', "{$class_id}:{$term_id}", [
			'recipient_type' => $recipient_type,
			'class_name' => $className,
			'term_name' => $termName,
			'sent_count' => $sent_count,
			'failed_count' => $failed_count
		]);
	}
	
	// Build response message
	$message = "Sent {$sent_count} emails";
	if ($failed_count > 0) {
		$message .= ", {$failed_count} failed";
	}
	
	if (empty($errors)) {
		$_SESSION['reply'] = array(array("success", $message));
	} else {
		$_SESSION['reply'] = array(array("warning", $message));
		// Log first few errors
		error_log("[send_bulk_results_email] Errors: " . implode("; ", array_slice($errors, 0, 5)));
	}
	
	header("location:../../admin/report");
	
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Error sending emails: " . $e->getMessage()));
	header("location:../../admin/report");
}
?>
