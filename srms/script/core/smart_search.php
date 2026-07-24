<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

header('Content-Type: application/json; charset=utf-8');

if ($res !== '1') {
	http_response_code(401);
	echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
	exit;
}

function app_search_portal_from_level(int $level): string
{
	if ($level === 3) return 'student';
	if ($level === 4) return 'parent';
	if ($level === 2) return 'teacher';
	if ($level === 1) return 'academic';
	if ($level === 5) return 'accountant';
	if ($level === 10) return 'bom';
	return 'admin';
}

$query = trim((string)($_GET['q'] ?? ''));
$portal = app_search_portal_from_level((int)$level);
$results = [];

try {
	$modules = app_current_user_visible_portal_modules($portal);
	foreach ($modules as $module) {
		$label = (string)($module['label'] ?? '');
		$desc = (string)($module['description'] ?? '');
		$href = (string)($module['href'] ?? '');
		if ($query === '' || stripos($label . ' ' . $desc . ' ' . $href, $query) !== false) {
			$results[] = [
				'type' => 'module',
				'title' => $label,
				'description' => $desc,
				'url' => $href,
			];
		}
	}

	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if ($query !== '' && app_table_exists($conn, 'tbl_classes') && app_current_user_has_any_permission(['academic.manage', 'report.view', 'attendance.manage', 'finance.view'])) {
		$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE name LIKE ? ORDER BY name LIMIT 5");
		$stmt->execute(['%' . $query . '%']);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$results[] = [
				'type' => 'class',
				'title' => (string)$row['name'],
				'description' => 'Class record',
				'url' => $portal === 'teacher' ? 'teacher/students' : ($portal === 'student' ? 'student/subjects' : ($portal === 'parent' ? 'parent/report_card' : 'admin/classes')),
			];
		}
	}

	if ($query !== '' && app_table_exists($conn, 'tbl_students') && app_current_user_has_any_permission(['students.manage', 'report.view', 'attendance.manage'])) {
		$stmt = $conn->prepare("SELECT id, fname, lname FROM tbl_students WHERE fname LIKE ? OR lname LIKE ? ORDER BY fname, lname LIMIT 5");
		$stmt->execute(['%' . $query . '%', '%' . $query . '%']);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$results[] = [
				'type' => 'student',
				'title' => trim((string)$row['fname'] . ' ' . (string)$row['lname']),
				'description' => 'Learner record',
				'url' => $portal === 'teacher' ? 'teacher/students' : ($portal === 'academic' ? 'academic/promote_students' : 'admin/manage_students'),
			];
		}
	}

	if ($query !== '' && app_table_exists($conn, 'tbl_staff') && app_current_user_has_any_permission(['staff.manage', 'academic.manage'])) {
		$stmt = $conn->prepare("SELECT id, fname, lname FROM tbl_staff WHERE fname LIKE ? OR lname LIKE ? ORDER BY fname, lname LIMIT 5");
		$stmt->execute(['%' . $query . '%', '%' . $query . '%']);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$results[] = [
				'type' => 'staff',
				'title' => trim((string)$row['fname'] . ' ' . (string)$row['lname']),
				'description' => 'Staff record',
				'url' => $portal === 'academic' ? 'admin/teachers' : 'admin/teachers',
			];
		}
	}

	echo json_encode([
		'ok' => true,
		'portal' => $portal,
		'results' => array_slice($results, 0, 12),
	]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'message' => 'Search unavailable right now.', 'details' => $e->getMessage()]);
}
