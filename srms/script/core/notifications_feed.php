<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/system_notifications.php');

header('Content-Type: application/json');

if ($res != "1") {
	echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
	exit;
}

function app_notifications_role_from_level(int $level): string
{
	if ($level === 3) { return 'student'; }
	if ($level === 4) { return 'parent'; }
	if ($level === 2) { return 'teacher'; }
	if ($level === 1) { return 'academic'; }
	if ($level === 5) { return 'accountant'; }
	if ($level === 10) { return 'bom'; }
	if ($level === 0 || $level === 9) { return 'admin'; }
	return 'staff';
}

function app_notifications_portal_from_role(string $role): string
{
	$role = strtolower(trim($role));
	if (in_array($role, ['admin', 'academic', 'teacher', 'accountant', 'bom', 'student', 'parent'], true)) {
		return $role;
	}
	return 'admin';
}

function app_notifications_link(string $portal, string $module): string
{
	$map = [
		'admin' => [
			'notifications' => 'admin/notifications',
			'attendance' => 'admin/attendance',
			'performance' => 'admin/results_analytics',
			'finance' => 'admin/fees',
			'discipline' => 'admin/discipline',
			'marks' => 'admin/exams',
		],
		'academic' => [
			'notifications' => 'academic/index',
			'attendance' => 'academic/index',
			'performance' => 'academic/index',
			'finance' => 'academic/index',
			'discipline' => 'academic/discipline',
			'marks' => 'academic/index',
		],
		'teacher' => [
			'notifications' => 'teacher/index',
			'attendance' => 'teacher/attendance',
			'performance' => 'teacher/class_report',
			'finance' => 'teacher/index',
			'discipline' => 'teacher/discipline',
			'marks' => 'teacher/exam_marks_entry',
		],
		'accountant' => [
			'notifications' => 'accountant/index',
			'attendance' => 'accountant/index',
			'performance' => 'accountant/index',
			'finance' => 'accountant/fees',
			'discipline' => 'accountant/index',
			'marks' => 'accountant/index',
		],
		'bom' => [
			'notifications' => 'bom/index',
			'attendance' => 'bom/index',
			'performance' => 'bom/index',
			'finance' => 'bom/index',
			'discipline' => 'bom/index',
			'marks' => 'bom/index',
		],
		'student' => [
			'notifications' => 'student/index',
			'attendance' => 'student/attendance',
			'performance' => 'student/results',
			'finance' => 'student/fees',
			'discipline' => 'student/discipline',
			'marks' => 'student/results',
		],
		'parent' => [
			'notifications' => 'parent/index',
			'attendance' => 'parent/attendance',
			'performance' => 'parent/report_card',
			'finance' => 'parent/fees',
			'discipline' => 'parent/discipline',
			'marks' => 'parent/report_card',
		],
	];

	return $map[$portal][$module] ?? ($map[$portal]['notifications'] ?? 'index.php');
}

function app_notifications_dynamic_item(string $id, string $title, string $message, string $type, int $priority, string $module, string $link): array
{
	return [
		'id' => $id,
		'title' => $title,
		'message' => $message,
		'type' => $type,
		'priority' => $priority,
		'module' => $module,
		'link' => $link,
		'created_at' => date('Y-m-d H:i:s'),
		'persistent' => false,
	];
}

function app_notifications_generate_system_alerts(PDO $conn): void
{
	$today = date('Y-m-d');

	if (app_table_exists($conn, 'tbl_classes') && app_table_exists($conn, 'tbl_attendance_sessions')) {
		try {
			$stmt = $conn->prepare("SELECT c.id, c.name
				FROM tbl_classes c
				LEFT JOIN tbl_attendance_sessions s
					ON s.class_id = c.id
					AND s.session_date = ?
					AND COALESCE(s.session_type, 'daily') = 'daily'
				WHERE s.id IS NULL
				ORDER BY c.id ASC
				LIMIT 5");
			$stmt->execute([$today]);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$classId = (int)($row['id'] ?? 0);
				$className = trim((string)($row['name'] ?? 'Unknown class'));
				if ($classId < 1 || $className === '') {
					continue;
				}
				app_system_notify_unique(
					$conn,
					'Attendance not submitted',
					$className . ' does not have a daily attendance session for ' . $today . '.',
					'attendance-missing-' . $today . '-' . $classId,
					[
						'audience' => 'staff',
						'user_role' => 'teacher',
						'module_name' => 'attendance',
						'type' => 'warning',
						'priority' => 80,
						'class_id' => $classId,
						'link' => 'attendance',
					]
				);
			}
		} catch (Throwable $e) {
			// best effort only
		}
	}

	if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		try {
			$stmt = $conn->query("SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE status IN ('draft','submitted','rejected')");
			$pending = (int)$stmt->fetchColumn();
			if ($pending > 0) {
				app_system_notify_unique(
					$conn,
					'Marks workflow requires action',
					$pending . ' mark submission(s) are still pending review or final posting.',
					'marks-pending-global',
					[
						'audience' => 'staff',
						'module_name' => 'marks',
						'user_role' => 'academic',
						'type' => 'warning',
						'priority' => 72,
						'link' => 'exams',
					]
				);
			}
		} catch (Throwable $e) {
			// best effort only
		}
	}

	if (app_table_exists($conn, 'tbl_student_invoices')) {
		try {
			$stmt = $conn->query("SELECT COUNT(*) FROM tbl_student_invoices WHERE status IN ('draft','sent','partial','overdue')");
			$openInvoices = (int)$stmt->fetchColumn();
			if ($openInvoices > 0) {
				app_system_notify_unique(
					$conn,
					'Outstanding fee balances detected',
					$openInvoices . ' invoice(s) are still open, overdue, or partially paid.',
					'fees-open-global',
					[
						'audience' => 'staff',
						'user_role' => 'accountant',
						'module_name' => 'finance',
						'type' => 'warning',
						'priority' => 68,
						'link' => 'fees',
					]
				);
			}
		} catch (Throwable $e) {
			// best effort only
		}
	}

	if (app_table_exists($conn, 'tbl_discipline_cases')) {
		try {
			$statusColumn = app_column_exists($conn, 'tbl_discipline_cases', 'case_status') ? 'case_status' : (app_column_exists($conn, 'tbl_discipline_cases', 'status') ? 'status' : '');
			if ($statusColumn !== '') {
				$stmt = $conn->query("SELECT COUNT(*) FROM tbl_discipline_cases WHERE COALESCE({$statusColumn}, 'Reported') IN ('Reported','Under Investigation','Hearing Scheduled','Open','Pending')");
				$openCases = (int)$stmt->fetchColumn();
				if ($openCases > 0) {
					app_system_notify_unique(
						$conn,
						'Unresolved discipline cases',
						$openCases . ' discipline case(s) still need action or closure.',
						'discipline-open-global',
						[
							'audience' => 'staff',
							'user_role' => 'academic',
							'module_name' => 'discipline',
							'type' => 'warning',
							'priority' => 65,
							'link' => 'discipline',
						]
					);
				}
			}
		} catch (Throwable $e) {
			// best effort only
		}
	}
}

function app_notifications_personal_items(PDO $conn, string $role, string $actorId, string $portal): array
{
	$items = [];

	if ($role === 'teacher' && $actorId !== '' && app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		try {
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE teacher_id = ? AND status IN ('draft','submitted','rejected')");
			$stmt->execute([(int)$actorId]);
			$pending = (int)$stmt->fetchColumn();
			if ($pending > 0) {
				$items[] = app_notifications_dynamic_item(
					'teacher-pending-marks-' . $actorId,
					'Your marks still need action',
					'You have ' . $pending . ' mark submission(s) still in draft, submitted, or rejected status.',
					'warning',
					86,
					'marks',
					app_notifications_link($portal, 'marks')
				);
			}
		} catch (Throwable $e) {
			// ignore
		}
	}

	if ($role === 'student' && $actorId !== '') {
		if (app_table_exists($conn, 'tbl_attendance_records')) {
			try {
				$stmt = $conn->prepare("SELECT SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count, COUNT(*) AS total_count FROM tbl_attendance_records WHERE student_id = ?");
				$stmt->execute([(int)$actorId]);
				$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
				$total = (int)($row['total_count'] ?? 0);
				if ($total > 0) {
					$rate = round((((int)($row['present_count'] ?? 0)) / $total) * 100, 1);
					if ($rate < 80) {
						$items[] = app_notifications_dynamic_item(
							'student-attendance-' . $actorId,
							'Attendance needs improvement',
							'Your attendance rate is ' . $rate . '%. Follow up on missed days early to avoid performance drop.',
							'warning',
							82,
							'attendance',
							app_notifications_link($portal, 'attendance')
						);
					}
				}
			} catch (Throwable $e) {
				// ignore
			}
		}

		if (app_table_exists($conn, 'tbl_discipline_cases')) {
			try {
				$statusColumn = app_column_exists($conn, 'tbl_discipline_cases', 'case_status') ? 'case_status' : (app_column_exists($conn, 'tbl_discipline_cases', 'status') ? 'status' : '');
				if ($statusColumn !== '') {
					$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_discipline_cases WHERE student_id = ? AND COALESCE({$statusColumn}, 'Reported') IN ('Reported','Under Investigation','Hearing Scheduled','Open','Pending')");
					$stmt->execute([(int)$actorId]);
					$count = (int)$stmt->fetchColumn();
					if ($count > 0) {
						$items[] = app_notifications_dynamic_item(
							'student-discipline-' . $actorId,
							'Discipline follow-up pending',
							'You currently have ' . $count . ' discipline case(s) that still require follow-up.',
							'warning',
							70,
							'discipline',
							app_notifications_link($portal, 'discipline')
						);
					}
				}
			} catch (Throwable $e) {
				// ignore
			}
		}
	}

	if ($role === 'parent' && $actorId !== '' && app_table_exists($conn, 'tbl_parent_students')) {
		try {
			$stmt = $conn->prepare("SELECT student_id FROM tbl_parent_students WHERE parent_id = ?");
			$stmt->execute([(int)$actorId]);
			$studentIds = array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
			if (!empty($studentIds) && app_table_exists($conn, 'tbl_student_invoices')) {
				$placeholders = implode(',', array_fill(0, count($studentIds), '?'));
				$invoiceSql = "SELECT COUNT(*) FROM tbl_student_invoices WHERE student_id IN ($placeholders) AND status IN ('draft','sent','partial','overdue')";
				$invoiceStmt = $conn->prepare($invoiceSql);
				$invoiceStmt->execute($studentIds);
				$openInvoices = (int)$invoiceStmt->fetchColumn();
				if ($openInvoices > 0) {
					$items[] = app_notifications_dynamic_item(
						'parent-fees-' . $actorId,
						'Fee follow-up needed',
						'Your linked learners currently have ' . $openInvoices . ' open or overdue invoice(s).',
						'warning',
						80,
						'finance',
						app_notifications_link($portal, 'finance')
					);
				}
			}
		} catch (Throwable $e) {
			// ignore
		}
	}

	return $items;
}

function app_notifications_fetch_persistent(PDO $conn, string $role, string $actorType, string $actorId, string $portal): array
{
	if (!app_table_exists($conn, 'tbl_notifications')) {
		return [];
	}

	$audiences = app_notification_audiences_for_role($role);
	$conditions = [];
	$params = [];
	$readParams = [];
	foreach ($audiences as $audience) {
		$conditions[] = 'n.audience = ?';
		$params[] = $audience;
	}
	if (empty($conditions)) {
		$conditions[] = '1=1';
	}

	$roleClause = '';
	if ($role !== 'admin' && app_column_exists($conn, 'tbl_notifications', 'user_role')) {
		$roleClause = " AND (n.user_role IS NULL OR n.user_role = '' OR n.user_role = ?)";
		$params[] = $role;
	}

	$readJoin = '';
	$readSelect = '0 AS is_read';
	if (app_table_exists($conn, 'tbl_notification_reads')) {
		$readJoin = " LEFT JOIN tbl_notification_reads nr ON nr.notification_id = n.id AND nr.actor_type = ? AND nr.actor_id = ? ";
		$readParams[] = $actorType;
		$readParams[] = $actorId;
		$readSelect = "CASE WHEN nr.id IS NULL THEN 0 ELSE 1 END AS is_read";
	}

	$sql = "SELECT n.id, n.title, n.message,
			" . (app_column_exists($conn, 'tbl_notifications', 'type') ? "COALESCE(n.type, 'info')" : "'info'") . " AS type,
			" . (app_column_exists($conn, 'tbl_notifications', 'priority') ? "COALESCE(n.priority, 0)" : "0") . " AS priority,
			" . (app_column_exists($conn, 'tbl_notifications', 'module_name') ? "COALESCE(n.module_name, '')" : "''") . " AS module_name,
			" . (app_column_exists($conn, 'tbl_notifications', 'link') ? "COALESCE(n.link, '')" : "''") . " AS raw_link,
			n.created_at,
			{$readSelect}
		FROM tbl_notifications n
		{$readJoin}
		WHERE (" . implode(' OR ', $conditions) . "){$roleClause}
		ORDER BY " . (app_column_exists($conn, 'tbl_notifications', 'priority') ? 'COALESCE(n.priority, 0) DESC, ' : '') . "n.created_at DESC
		LIMIT 12";

	$stmt = $conn->prepare($sql);
	$stmt->execute(array_merge($readParams, $params));
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

	$items = [];
	foreach ($rows as $row) {
		$module = trim((string)($row['module_name'] ?? ''));
		if ($module === '') {
			$module = 'notifications';
		}
		$link = trim((string)($row['raw_link'] ?? ''));
		if ($link === '' || strpos($link, '/') === false) {
			$link = app_notifications_link($portal, $module);
		}
		$items[] = [
			'id' => (int)($row['id'] ?? 0),
			'title' => trim((string)($row['title'] ?? 'Notification')),
			'message' => trim((string)($row['message'] ?? '')),
			'type' => trim((string)($row['type'] ?? 'info')),
			'priority' => (int)($row['priority'] ?? 0),
			'module' => $module,
			'link' => $link,
			'created_at' => (string)($row['created_at'] ?? date('Y-m-d H:i:s')),
			'is_read' => (int)($row['is_read'] ?? 0) === 1,
			'persistent' => true,
		];
	}

	return $items;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));
$role = app_notifications_role_from_level((int)$level);
$portal = app_notifications_portal_from_role($role);
$actorType = app_notification_actor_type_for_role($role);
$actorId = (string)($account_id ?? '');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_notifications_ai_schema($conn);
	app_notifications_generate_system_alerts($conn);

	if ($action === 'read_all') {
		$persistentItems = app_notifications_fetch_persistent($conn, $role, $actorType, $actorId, $portal);
		$ids = [];
		foreach ($persistentItems as $item) {
			if (!empty($item['id']) && empty($item['is_read'])) {
				$ids[] = (int)$item['id'];
			}
		}
		app_notification_mark_all_read($conn, $actorType, $actorId, $ids);
		echo json_encode(['ok' => true, 'count_marked' => count($ids)]);
		exit;
	}

	$persistent = app_notifications_fetch_persistent($conn, $role, $actorType, $actorId, $portal);
	$dynamic = app_notifications_personal_items($conn, $role, $actorId, $portal);
	$items = array_merge($dynamic, $persistent);

	usort($items, static function (array $a, array $b): int {
		$priorityCompare = ((int)($b['priority'] ?? 0)) <=> ((int)($a['priority'] ?? 0));
		if ($priorityCompare !== 0) {
			return $priorityCompare;
		}
		return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
	});

	$countUnread = 0;
	foreach ($items as $item) {
		if (empty($item['is_read'])) {
			$countUnread++;
		}
	}

	echo json_encode([
		'ok' => true,
		'role' => $role,
		'portal' => $portal,
		'count_unread' => $countUnread,
		'view_all_url' => app_notifications_link($portal, 'notifications'),
		'items' => array_slice($items, 0, 8),
	]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'ok' => false,
		'message' => 'Unable to load notifications right now.',
		'details' => $e->getMessage(),
	]);
}
