<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== '1' || !isset($level) || $level !== '0') {
    header('location:../../');
    exit;
}
app_require_permission('academic.manage', '../school_timetable');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location:../school_timetable');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$dayName = trim((string)($_POST['day_name'] ?? ''));
$sessionLabel = trim((string)($_POST['session_label'] ?? ''));
$startTime = trim((string)($_POST['start_time'] ?? ''));
$endTime = trim((string)($_POST['end_time'] ?? ''));
$room = trim((string)($_POST['room'] ?? ''));
$swapWithId = (int)($_POST['swap_with_id'] ?? 0);

if ($id < 1 || $classId < 1 || $termId < 1 || $dayName === '' || $sessionLabel === '' || $startTime === '' || $endTime === '') {
    app_reply_redirect('danger', 'Missing timetable slot details.', '../school_timetable?class_id=' . $classId . '&term_id=' . $termId);
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
    app_reply_redirect('danger', 'Invalid time format.', '../school_timetable?class_id=' . $classId . '&term_id=' . $termId);
}

if (strlen($startTime) === 5) {
    $startTime .= ':00';
}
if (strlen($endTime) === 5) {
    $endTime .= ':00';
}
if ($startTime >= $endTime) {
    app_reply_redirect('danger', 'End time must be after start time.', '../school_timetable?class_id=' . $classId . '&term_id=' . $termId);
}

function app_slot_has_conflict(PDO $conn, int $termId, array $excludeIds, string $dayName, string $startTime, string $endTime, int $classId, int $teacherId, string $room): bool
{
    $excludeIds = array_values(array_filter(array_map('intval', $excludeIds), function ($value) {
        return $value > 0;
    }));

    $where = 'term_id = ? AND day_name = ? AND start_time < ? AND end_time > ?';
    $params = [$termId, $dayName, $endTime, $startTime];

    if (!empty($excludeIds)) {
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $where .= ' AND id NOT IN (' . $placeholders . ')';
        $params = array_merge($params, $excludeIds);
    }

    $where .= ' AND (class_id = ? OR teacher_id = ?';
    $params[] = $classId;
    $params[] = $teacherId;
    if ($room !== '') {
        $where .= ' OR room = ?';
        $params[] = $room;
    }
    $where .= ')';

    $stmt = $conn->prepare('SELECT COUNT(*) FROM tbl_school_timetable WHERE ' . $where);
    $stmt->execute($params);
    return ((int)$stmt->fetchColumn()) > 0;
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_school_timetable_table($conn);

    $stmt = $conn->prepare('SELECT id, teacher_id, day_name, session_label, start_time, end_time, room FROM tbl_school_timetable WHERE id = ? AND class_id = ? AND term_id = ? LIMIT 1');
    $stmt->execute([$id, $classId, $termId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Timetable entry not found.');
    }
    $teacherId = (int)$row['teacher_id'];

    if ($swapWithId > 0 && $swapWithId === $id) {
        $swapWithId = 0;
    }

    if ($swapWithId > 0) {
        $stmt = $conn->prepare('SELECT id, teacher_id, day_name, session_label, start_time, end_time, room FROM tbl_school_timetable WHERE id = ? AND class_id = ? AND term_id = ? LIMIT 1');
        $stmt->execute([$swapWithId, $classId, $termId]);
        $swapRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$swapRow) {
            throw new RuntimeException('Target slot was not found for swap.');
        }

        $swapTeacherId = (int)$swapRow['teacher_id'];
        $swapRoom = trim((string)($swapRow['room'] ?? ''));
        $sourceOriginalDay = (string)$row['day_name'];
        $sourceOriginalSession = (string)$row['session_label'];
        $sourceOriginalStart = substr((string)$row['start_time'], 0, 8);
        $sourceOriginalEnd = substr((string)$row['end_time'], 0, 8);

        $exclude = [$id, $swapWithId];
        if (app_slot_has_conflict($conn, $termId, $exclude, $dayName, $startTime, $endTime, $classId, $teacherId, $room)) {
            throw new RuntimeException('Slot conflict detected while moving the selected lesson.');
        }
        if (app_slot_has_conflict($conn, $termId, $exclude, $sourceOriginalDay, $sourceOriginalStart, $sourceOriginalEnd, $classId, $swapTeacherId, $swapRoom)) {
            throw new RuntimeException('Slot conflict detected while placing the swapped lesson.');
        }

        $conn->beginTransaction();
        $update = $conn->prepare('UPDATE tbl_school_timetable SET day_name = ?, session_label = ?, start_time = ?, end_time = ?, room = ? WHERE id = ?');
        $update->execute([$dayName, $sessionLabel, $startTime, $endTime, $room, $id]);
        $update->execute([$sourceOriginalDay, $sourceOriginalSession, $sourceOriginalStart, $sourceOriginalEnd, $swapRoom, $swapWithId]);
        $conn->commit();
        app_reply_redirect('success', 'Timetable slots swapped successfully.', '../school_timetable?class_id=' . $classId . '&term_id=' . $termId);
    }

    if (app_slot_has_conflict($conn, $termId, [$id], $dayName, $startTime, $endTime, $classId, $teacherId, $room)) {
        throw new RuntimeException('Slot conflict detected for class, teacher, or room.');
    }

    $update = $conn->prepare('UPDATE tbl_school_timetable SET day_name = ?, session_label = ?, start_time = ?, end_time = ?, room = ? WHERE id = ?');
    $update->execute([$dayName, $sessionLabel, $startTime, $endTime, $room, $id]);

    app_reply_redirect('success', 'Timetable slot moved successfully.', '../school_timetable?class_id=' . $classId . '&term_id=' . $termId);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    app_reply_redirect('danger', 'Failed to move slot: ' . $e->getMessage(), '../school_timetable?class_id=' . $classId . '&term_id=' . $termId);
}
