<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	http_response_code(401);
	echo json_encode(["error" => "unauthorized"]);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$counts = [];
	$counts['students'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
	$counts['teachers'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn();
	$counts['staff'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_staff")->fetchColumn();
	$counts['classes'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_classes")->fetchColumn();
	$counts['subjects'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_subjects")->fetchColumn();
	$counts['terms_active'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_terms WHERE status = 1")->fetchColumn();

	$stmt = $conn->prepare("SELECT c.id, c.name, COUNT(s.id) AS count
		FROM tbl_classes c
		LEFT JOIN tbl_students s ON s.class = c.id
		GROUP BY c.id, c.name
		ORDER BY c.id");
	$stmt->execute();
	$studentsByClass = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT t.id, t.name, COALESCE(AVG(r.score), 0) AS avg_score
		FROM tbl_terms t
		LEFT JOIN tbl_exam_results r ON r.term = t.id
		GROUP BY t.id, t.name
		ORDER BY t.id");
	$stmt->execute();
	$avgScoreByTerm = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT gender, COUNT(*) AS count FROM tbl_students GROUP BY gender ORDER BY gender");
	$stmt->execute();
	$studentsByGender = $stmt->fetchAll(PDO::FETCH_ASSOC);

	echo json_encode([
		"counts" => $counts,
		"studentsByClass" => $studentsByClass,
		"avgScoreByTerm" => $avgScoreByTerm,
		"studentsByGender" => $studentsByGender,
	]);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(["error" => $e->getMessage()]);
}

