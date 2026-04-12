<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

header('Content-Type: application/json');

if ($res != "1") {
	echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
	exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
	$payload = $_POST;
}

$message = trim((string)($payload['message'] ?? ''));
$category = trim((string)($payload['category'] ?? 'feedback'));
if ($message === '') {
	echo json_encode(['ok' => false, 'message' => 'Message required']);
	exit;
}

function app_ai_suffix(string $response): string
{
	$response = trim($response);
	if ($response === '') {
		return 'ofx_steve';
	}
	if (preg_match('/\s+ofx_steve$/i', $response)) {
		return $response;
	}
	return $response . ' ofx_steve';
}

function app_feedback_subject_from_message(string $message): string
{
	$message = trim(preg_replace('/\s+/', ' ', $message));
	if ($message === '') {
		return 'General feedback';
	}
	if (function_exists('mb_substr')) {
		return mb_substr($message, 0, 80);
	}
	return substr($message, 0, 80);
}

function app_store_ai_feedback(PDO $conn, string $actorType, string $actorId, string $category, string $subject, string $message, string $aiResponse, string $status = 'open', string $replyMessage = ''): void
{
	if (!app_table_exists($conn, 'tbl_ai_feedback')) {
		return;
	}

	$columns = ['actor_type', 'actor_id', 'category', 'message', 'ai_response'];
	$values = [$actorType, $actorId, $category, $message, $aiResponse];
	$placeholders = ['?', '?', '?', '?', '?'];

	if (app_column_exists($conn, 'tbl_ai_feedback', 'subject')) {
		$columns[] = 'subject';
		$values[] = $subject;
		$placeholders[] = '?';
	}
	if (app_column_exists($conn, 'tbl_ai_feedback', 'status')) {
		$columns[] = 'status';
		$values[] = $status;
		$placeholders[] = '?';
	}
	if (app_column_exists($conn, 'tbl_ai_feedback', 'reply_message')) {
		$columns[] = 'reply_message';
		$values[] = $replyMessage;
		$placeholders[] = '?';
	}
	if (app_column_exists($conn, 'tbl_ai_feedback', 'replied_by')) {
		$columns[] = 'replied_by';
		$values[] = null;
		$placeholders[] = '?';
	}

	$sql = "INSERT INTO tbl_ai_feedback (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
	$stmt = $conn->prepare($sql);
	$stmt->execute($values);
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$actorType = 'staff';
	if ((int)$level === 3) { $actorType = 'student'; }
	if ((int)$level === 4) { $actorType = 'parent'; }
	$actorId = (string)$account_id;

	$role = app_role_from_level((int)$level);
	$intent = app_detect_intent($message);

	if ($category !== 'ai') {
		$response = 'Thanks! We received your message.';
		app_store_ai_feedback($conn, $actorType, $actorId, $category, app_feedback_subject_from_message($message), $message, $response, 'open', '');
		echo json_encode(['ok' => true, 'response' => $response]);
		exit;
	}

	if (app_is_restricted_question($message, $role)) {
		$response = 'You are not allowed to access that information in this portal.';
		app_ai_log($conn, $actorType, $actorId, $role, $message, $response, $intent);
		echo json_encode(['ok' => true, 'response' => $response]);
		exit;
	}

	$scope = app_ai_scope($conn, $role, $actorId);
	$response = app_generate_ai_reply($message, $scope, $role, $intent);
	$openai = app_openai_reply($message, $scope, $role, $intent);
	if ($openai !== '') {
		$response = $openai;
	}
	$response = app_ai_suffix($response);

	app_ai_log($conn, $actorType, $actorId, $role, $message, $response, $intent);
	app_store_ai_feedback($conn, $actorType, $actorId, 'ai', $intent, $message, $response, 'answered', '');
	echo json_encode(['ok' => true, 'response' => $response]);
} catch (Throwable $e) {
	echo json_encode(['ok' => false, 'message' => 'Failed to save message']);
}

function app_role_from_level(int $level): string
{
	if ($level === 3) { return 'student'; }
	if ($level === 4) { return 'parent'; }
	if ($level === 2) { return 'teacher'; }
	if ($level === 0) { return 'admin'; }
	if ($level === 1) { return 'admin'; }
	if ($level === 5) { return 'accountant'; }
	return 'staff';
}

function app_detect_intent(string $message): string
{
	$lower = strtolower($message);
	if (strpos($lower, 'fee') !== false || strpos($lower, 'payment') !== false || strpos($lower, 'balance') !== false) {
		return 'fees';
	}
	if (strpos($lower, 'attendance') !== false || strpos($lower, 'absent') !== false) {
		return 'attendance';
	}
	if (strpos($lower, 'result') !== false || strpos($lower, 'mark') !== false || strpos($lower, 'score') !== false || strpos($lower, 'performance') !== false) {
		return 'performance';
	}
	if (strpos($lower, 'assignment') !== false || strpos($lower, 'quiz') !== false) {
		return 'assignments';
	}
	if (strpos($lower, 'report card') !== false || strpos($lower, 'report') !== false || strpos($lower, 'publish') !== false) {
		return 'report';
	}
	if (strpos($lower, 'timetable') !== false || strpos($lower, 'schedule') !== false || strpos($lower, 'drag') !== false) {
		return 'timetable';
	}
	if (strpos($lower, 'class') !== false && (strpos($lower, 'best') !== false || strpos($lower, 'top') !== false)) {
		return 'analytics';
	}
	if (strpos($lower, 'explain') !== false || strpos($lower, 'define') !== false || strpos($lower, 'lesson') !== false) {
		return 'learning';
	}
	if (strpos($lower, 'live class') !== false || strpos($lower, 'meeting') !== false) {
		return 'live';
	}
	return 'general';
}

function app_is_restricted_question(string $message, string $role): bool
{
	$lower = strtolower($message);
	$restrictedKeywords = [
		'all students', 'other students', 'top student', 'best student', 'ranking', 'rank', 'leaderboard'
	];
	foreach ($restrictedKeywords as $keyword) {
		if (strpos($lower, $keyword) !== false) {
			return in_array($role, ['student', 'parent'], true);
		}
	}
	return false;
}

function app_ai_scope(PDO $conn, string $role, string $actorId): array
{
	$scope = [
		'role' => $role,
		'profile' => [],
		'students' => [],
		'teacher' => [],
		'school' => [],
	];

	if ($role === 'student') {
		$scope['profile'] = app_student_profile($conn, $actorId);
		$scope['students'][] = app_student_summary($conn, $actorId, $scope['profile']['class_id'] ?? null);
		return $scope;
	}

	if ($role === 'parent') {
		$children = app_parent_children($conn, (int)$actorId);
		foreach ($children as $studentId) {
			$scope['students'][] = app_student_summary($conn, $studentId, null);
		}
		return $scope;
	}

	if ($role === 'teacher') {
		$scope['teacher'] = app_teacher_scope($conn, (int)$actorId);
		return $scope;
	}

	if ($role === 'accountant') {
		$scope['school'] = app_accountant_scope($conn);
		return $scope;
	}

	$scope['school'] = app_school_scope($conn);
	return $scope;
}

function app_student_profile(PDO $conn, string $studentId): array
{
	if (!app_table_exists($conn, 'tbl_students')) {
		return [];
	}
	$stmt = $conn->prepare("SELECT st.id, st.fname, st.lname, st.class, c.name as class_name FROM tbl_students st LEFT JOIN tbl_classes c ON c.id = st.class WHERE st.id = ? LIMIT 1");
	$stmt->execute([$studentId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return [];
	}
	return [
		'id' => (string)$row['id'],
		'name' => trim($row['fname'].' '.$row['lname']),
		'class_id' => (int)$row['class'],
		'class_name' => (string)($row['class_name'] ?? ''),
	];
}

function app_parent_children(PDO $conn, int $parentId): array
{
	if ($parentId < 1 || !app_table_exists($conn, 'tbl_parent_students')) {
		return [];
	}
	$stmt = $conn->prepare("SELECT student_id FROM tbl_parent_students WHERE parent_id = ?");
	$stmt->execute([$parentId]);
	$students = $stmt->fetchAll(PDO::FETCH_COLUMN);
	return array_slice(array_map('strval', $students), 0, 3);
}

function app_student_summary(PDO $conn, string $studentId, ?int $classId): array
{
	$profile = app_student_profile($conn, $studentId);
	$classId = $classId ?? ($profile['class_id'] ?? null);

	$summary = [
		'id' => $studentId,
		'name' => $profile['name'] ?? '',
		'class_name' => $profile['class_name'] ?? '',
		'attendance_rate' => null,
		'avg_score' => null,
		'top_subjects' => [],
		'cbc_levels' => [],
		'fees' => null,
		'assignments' => null,
		'live_classes' => [],
	];

	if (app_table_exists($conn, 'tbl_attendance_records')) {
		$stmt = $conn->prepare("SELECT SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count, COUNT(*) AS total_count FROM tbl_attendance_records WHERE student_id = ?");
		$stmt->execute([$studentId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
		$total = (int)($row['total_count'] ?? 0);
		if ($total > 0) {
			$summary['attendance_rate'] = round(((int)$row['present_count'] / $total) * 100, 1);
		}
	}

	if (app_table_exists($conn, 'tbl_exam_results')) {
		$stmt = $conn->prepare("SELECT AVG(score) AS avg_score FROM tbl_exam_results WHERE student = ?");
		$stmt->execute([$studentId]);
		$avg = $stmt->fetchColumn();
		if ($avg !== false) {
			$summary['avg_score'] = round((float)$avg, 2);
		}

		if (app_table_exists($conn, 'tbl_subject_combinations') && app_table_exists($conn, 'tbl_subjects')) {
			$stmt = $conn->prepare("SELECT s.name, AVG(er.score) AS avg_score
				FROM tbl_exam_results er
				JOIN tbl_subject_combinations sc ON sc.id = er.subject_combination
				JOIN tbl_subjects s ON s.id = sc.subject
				WHERE er.student = ?
				GROUP BY s.name
				ORDER BY avg_score DESC
				LIMIT 3");
			$stmt->execute([$studentId]);
			$summary['top_subjects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	}

	if (app_table_exists($conn, 'tbl_cbc_assessments')) {
		$stmt = $conn->prepare("SELECT learning_area, level, COUNT(*) AS entries
			FROM tbl_cbc_assessments
			WHERE student_id = ?
			GROUP BY learning_area, level
			ORDER BY learning_area");
		$stmt->execute([$studentId]);
		$summary['cbc_levels'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if ($classId && app_table_exists($conn, 'tbl_assignments') && app_table_exists($conn, 'tbl_courses')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_assignments a JOIN tbl_courses c ON c.id = a.course_id WHERE c.class_id = ?");
		$stmt->execute([$classId]);
		$totalAssignments = (int)$stmt->fetchColumn();

		if (app_table_exists($conn, 'tbl_assignment_submissions')) {
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_assignment_submissions WHERE student_id = ?");
			$stmt->execute([$studentId]);
			$submitted = (int)$stmt->fetchColumn();
			$summary['assignments'] = [
				'total' => $totalAssignments,
				'submitted' => $submitted,
			];
		}
	}

	if (app_table_exists($conn, 'tbl_invoices') && app_table_exists($conn, 'tbl_invoice_lines') && app_table_exists($conn, 'tbl_payments')) {
		$stmt = $conn->prepare("SELECT COALESCE(SUM(l.amount), 0) FROM tbl_invoice_lines l JOIN tbl_invoices i ON i.id = l.invoice_id WHERE i.student_id = ?");
		$stmt->execute([$studentId]);
		$total = (float)$stmt->fetchColumn();

		$stmt = $conn->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM tbl_payments p JOIN tbl_invoices i ON i.id = p.invoice_id WHERE i.student_id = ?");
		$stmt->execute([$studentId]);
		$paid = (float)$stmt->fetchColumn();
		$summary['fees'] = [
			'total' => round($total, 2),
			'paid' => round($paid, 2),
			'balance' => round($total - $paid, 2),
		];
	}

	if ($classId && app_table_exists($conn, 'tbl_live_classes') && app_table_exists($conn, 'tbl_courses')) {
		$stmt = $conn->prepare("SELECT lc.title, lc.start_time, lc.platform
			FROM tbl_live_classes lc
			JOIN tbl_courses c ON c.id = lc.course_id
			WHERE c.class_id = ? AND lc.start_time >= NOW()
			ORDER BY lc.start_time
			LIMIT 2");
		$stmt->execute([$classId]);
		$summary['live_classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	return $summary;
}

function app_teacher_scope(PDO $conn, int $teacherId): array
{
	$scope = [
		'classes' => [],
		'subjects' => [],
		'pending_marks' => 0,
	];

	if (!app_table_exists($conn, 'tbl_subject_combinations')) {
		return $scope;
	}

	$stmt = $conn->prepare("SELECT sc.id, sc.class, sc.subject, s.name AS subject_name
		FROM tbl_subject_combinations sc
		JOIN tbl_subjects s ON s.id = sc.subject
		WHERE sc.teacher = ?");
	$stmt->execute([$teacherId]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$classIds = [];
	foreach ($rows as $row) {
		$classes = @unserialize($row['class']);
		if (!is_array($classes)) {
			$classes = [];
		}
		foreach ($classes as $classId) {
			$classIds[] = (int)$classId;
		}
		$scope['subjects'][] = [
			'id' => (int)$row['subject'],
			'name' => (string)$row['subject_name'],
			'subject_combination_id' => (int)$row['id'],
		];
	}
	$classIds = array_values(array_unique($classIds));

	if (!empty($classIds) && app_table_exists($conn, 'tbl_classes')) {
		$placeholders = implode(',', array_fill(0, count($classIds), '?'));
		$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders)");
		$stmt->execute($classIds);
		$scope['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE teacher_id = ? AND status = 'draft'");
		$stmt->execute([$teacherId]);
		$scope['pending_marks'] = (int)$stmt->fetchColumn();
	}

	return $scope;
}

function app_school_scope(PDO $conn): array
{
	$stats = [
		'students' => 0,
		'staff' => 0,
		'attendance_rate' => null,
		'avg_score' => null,
		'report_cards' => 0,
		'published_terms' => 0,
		'timetable_slots' => 0,
	];

	if (app_table_exists($conn, 'tbl_students')) {
		$stmt = $conn->query("SELECT COUNT(*) FROM tbl_students");
		$stats['students'] = (int)$stmt->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_staff')) {
		$stmt = $conn->query("SELECT COUNT(*) FROM tbl_staff");
		$stats['staff'] = (int)$stmt->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_attendance_records')) {
		$stmt = $conn->query("SELECT SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count, COUNT(*) AS total_count FROM tbl_attendance_records");
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
		$total = (int)($row['total_count'] ?? 0);
		if ($total > 0) {
			$stats['attendance_rate'] = round(((int)$row['present_count'] / $total) * 100, 1);
		}
	}
	if (app_table_exists($conn, 'tbl_exam_results')) {
		$stmt = $conn->query("SELECT AVG(score) FROM tbl_exam_results");
		$avg = $stmt->fetchColumn();
		if ($avg !== false) {
			$stats['avg_score'] = round((float)$avg, 2);
		}
	}
	if (app_table_exists($conn, 'tbl_report_cards')) {
		$stmt = $conn->query("SELECT COUNT(*) FROM tbl_report_cards");
		$stats['report_cards'] = (int)$stmt->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_report_publication')) {
		$stmt = $conn->query("SELECT COUNT(*) FROM tbl_report_publication WHERE state = 'published'");
		$stats['published_terms'] = (int)$stmt->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_school_timetable')) {
		$stmt = $conn->query("SELECT COUNT(*) FROM tbl_school_timetable");
		$stats['timetable_slots'] = (int)$stmt->fetchColumn();
	}
	return $stats;
}

function app_accountant_scope(PDO $conn): array
{
	$stats = [
		'invoices' => 0,
		'collections' => 0,
		'outstanding' => 0,
	];

	if (app_table_exists($conn, 'tbl_invoices')) {
		$stmt = $conn->query("SELECT COUNT(*) FROM tbl_invoices");
		$stats['invoices'] = (int)$stmt->fetchColumn();
	}

	if (app_table_exists($conn, 'tbl_payments')) {
		$stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) FROM tbl_payments");
		$stats['collections'] = round((float)$stmt->fetchColumn(), 2);
	}

	if (app_table_exists($conn, 'tbl_invoice_lines') && app_table_exists($conn, 'tbl_invoices')) {
		$stmt = $conn->query("SELECT COALESCE(SUM(l.amount), 0) FROM tbl_invoice_lines l JOIN tbl_invoices i ON i.id = l.invoice_id");
		$total = (float)$stmt->fetchColumn();
		$stats['outstanding'] = round($total - $stats['collections'], 2);
	}

	return $stats;
}

function app_generate_ai_reply(string $message, array $scope, string $role, string $intent): string
{
	$lower = strtolower($message);

	if ($intent === 'fees') {
		$student = $scope['students'][0] ?? null;
		$fees = $student['fees'] ?? null;
		if ($fees) {
			return 'Fees summary: Total Ksh '.number_format($fees['total'], 2).', Paid Ksh '.number_format($fees['paid'], 2).', Balance Ksh '.number_format($fees['balance'], 2).'.';
		}
		if ($role === 'accountant' && !empty($scope['school'])) {
			return 'Collections: Ksh '.number_format((float)($scope['school']['collections'] ?? 0), 2).', Outstanding: Ksh '.number_format((float)($scope['school']['outstanding'] ?? 0), 2).'.';
		}
		return 'No fee records found yet for this account.';
	}

	if ($intent === 'attendance') {
		$student = $scope['students'][0] ?? null;
		$rate = $student['attendance_rate'] ?? null;
		if ($rate !== null) {
			return 'Attendance rate is '.$rate.'%.';
		}
		if (!empty($scope['school']['attendance_rate'])) {
			return 'School attendance rate is '.$scope['school']['attendance_rate'].'%.';
		}
		return 'Attendance data is not available yet.';
	}

	if ($intent === 'performance') {
		$student = $scope['students'][0] ?? null;
		if ($student) {
			$parts = [];
			if ($student['avg_score'] !== null) {
				$parts[] = 'Average score: '.$student['avg_score'];
			}
			if (!empty($student['top_subjects'])) {
				$tops = [];
				foreach ($student['top_subjects'] as $row) {
					$tops[] = $row['name'].' ('.round((float)$row['avg_score'], 1).')';
				}
				$parts[] = 'Top subjects: '.implode(', ', $tops);
			}
			if (!empty($parts)) {
				return implode('. ', $parts).'.';
			}
		}
		if (!empty($scope['school']['avg_score'])) {
			return 'School average score: '.$scope['school']['avg_score'].'.';
		}
		return 'No exam performance data yet.';
	}

	if ($intent === 'assignments') {
		$student = $scope['students'][0] ?? null;
		$assign = $student['assignments'] ?? null;
		if ($assign) {
			return 'Assignments: '.$assign['submitted'].' submitted out of '.$assign['total'].'.';
		}
		return 'No assignment data available yet.';
	}

	if ($intent === 'report') {
		$student = $scope['students'][0] ?? null;
		if ($student && $student['avg_score'] !== null) {
			$response = 'Report automation summary: current average score is '.$student['avg_score'].'.';
			if (!empty($student['top_subjects'])) {
				$tops = [];
				foreach ($student['top_subjects'] as $row) {
					$tops[] = $row['name'].' ('.round((float)$row['avg_score'], 1).')';
				}
				$response .= ' Strongest areas: '.implode(', ', $tops).'.';
			}
			$response .= ' Use Report Card to view AI summary and teacher/headteacher remarks.';
			return $response;
		}
		if (!empty($scope['school'])) {
			return 'Report automation status: '.(int)($scope['school']['report_cards'] ?? 0).' report cards generated, '.(int)($scope['school']['published_terms'] ?? 0).' published term releases. Admin can regenerate from Report > Generate Report Cards.';
		}
		return 'Report automation is available after marks are saved and results are locked.';
	}

	if ($intent === 'timetable') {
		if ($role === 'admin' || $role === 'teacher') {
			$slots = (int)($scope['school']['timetable_slots'] ?? 0);
			return 'Timetable automation is active. Current stored lesson slots: '.$slots.'. Use School Timetable to auto-generate conflict-free slots, then drag and drop lessons to swap safely.';
		}
		return 'Timetable updates are managed by school administrators. Ask for the latest class schedule from your portal timetable page.';
	}

	if ($intent === 'live') {
		$student = $scope['students'][0] ?? null;
		if (!empty($student['live_classes'])) {
			$next = $student['live_classes'][0];
			return 'Next live class: '.$next['title'].' on '.date('Y-m-d H:i', strtotime($next['start_time'])).' via '.$next['platform'].'.';
		}
		return 'No upcoming live classes found.';
	}

	if ($intent === 'analytics') {
		if ($role === 'teacher') {
			$pending = $scope['teacher']['pending_marks'] ?? 0;
			return 'You have '.$pending.' pending marks submissions. You can review from Marks Entry.';
		}
		if ($role === 'accountant') {
			$stats = $scope['school'] ?? [];
			return 'Finance snapshot: '.$stats['invoices'].' invoices, collections Ksh '.number_format((float)($stats['collections'] ?? 0), 2).', outstanding Ksh '.number_format((float)($stats['outstanding'] ?? 0), 2).'.';
		}
		if (!empty($scope['school'])) {
			return 'School snapshot: '.$scope['school']['students'].' students, '.$scope['school']['staff'].' staff.';
		}
	}

	if ($intent === 'learning') {
		return 'Tell me the topic you want explained and I will guide you step-by-step.';
	}

	if (strpos($lower, 'assignment') !== false) {
		return 'Assignments are under E-Learning. Teachers create them; students submit in their portal.';
	}
	if (strpos($lower, 'results') !== false || strpos($lower, 'marks') !== false) {
		return 'Marks entry is under Exam Marks Entry (teachers) and Marks Review (admin). Results unlock only by admin.';
	}
	if (strpos($lower, 'report') !== false) {
		return 'Report cards use marks, attendance, fees status, ranking, and AI-generated comments. Admin can generate and publish them from the Report module.';
	}
	if (strpos($lower, 'timetable') !== false || strpos($lower, 'schedule') !== false) {
		return 'School timetable supports smart auto-generation and drag-and-drop slot swaps with conflict checks for class, teacher, and room.';
	}
	if (strpos($lower, 'attendance') !== false) {
		return 'Attendance is available under Attendance and Staff Attendance. Reports are on the dashboard.';
	}
	return 'Thanks for the message. Ask about performance, attendance, fees, or assignments to get a data-based answer.';
}

function app_openai_reply(string $message, array $scope, string $role, string $intent): string
{
	$mode = strtolower(trim((string)getenv('AI_MODE')));
	if ($mode !== 'external') {
		return '';
	}
	$apiKey = trim((string)getenv('OPENAI_API_KEY'));
	if ($apiKey === '') {
		return '';
	}
	$model = trim((string)getenv('OPENAI_MODEL'));
	if ($model === '') {
		$model = 'gpt-4o-mini';
	}

	$systemPrompt = "You are the school's AI assistant. Follow role-based access. Role: ".$role.". ".
		"Never reveal data outside the allowed scope. Do not invent names or statistics. ".
		"Use CBC levels (EE/ME/AE/BE) when relevant. If data is missing, say so.";

	$context = json_encode($scope, JSON_UNESCAPED_SLASHES);
	$userPrompt = "User question: ".$message."\nIntent: ".$intent."\nAllowed data (JSON): ".$context;

	$payload = json_encode([
		'model' => $model,
		'input' => [
			['role' => 'system', 'content' => $systemPrompt],
			['role' => 'user', 'content' => $userPrompt],
		],
		'temperature' => 0.2,
		'max_output_tokens' => 300,
	]);

	$ch = curl_init('https://api.openai.com/v1/responses');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Authorization: Bearer '.$apiKey,
		'Content-Type: application/json',
	]);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);

	$result = curl_exec($ch);
	if ($result === false) {
		curl_close($ch);
		return '';
	}
	$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($httpCode < 200 || $httpCode >= 300) {
		return '';
	}

	$data = json_decode($result, true);
	if (!is_array($data)) {
		return '';
	}

	if (isset($data['output_text']) && is_string($data['output_text'])) {
		return trim($data['output_text']);
	}

	if (isset($data['output']) && is_array($data['output'])) {
		foreach ($data['output'] as $item) {
			if (!empty($item['content']) && is_array($item['content'])) {
				foreach ($item['content'] as $content) {
					if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
						return trim((string)$content['text']);
					}
				}
			}
		}
	}

	return '';
}

function app_ai_log(PDO $conn, string $actorType, string $actorId, string $role, string $question, string $response, string $intent): void
{
	try {
		if (app_table_exists($conn, 'tbl_ai_logs')) {
			$stmt = $conn->prepare("INSERT INTO tbl_ai_logs (actor_type, actor_id, role, question, response) VALUES (?,?,?,?,?)");
			$stmt->execute([$actorType, $actorId, $role, $question, $response]);
		}
		if (app_table_exists($conn, 'tbl_ai_queries')) {
			$stmt = $conn->prepare("INSERT INTO tbl_ai_queries (actor_type, actor_id, role, question, intent) VALUES (?,?,?,?,?)");
			$stmt->execute([$actorType, $actorId, $role, $question, $intent]);
		}
	} catch (Throwable $e) {
		// best-effort only
	}
}
