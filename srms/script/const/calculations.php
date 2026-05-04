<?php

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	// CBC-ONLY: Remove legacy tbl_grade_system. Use only CBC/new grading tables.
	$grades = app_default_marks_grading_rows($conn);

	// Load division system if available; otherwise keep empty.
	if (app_table_exists($conn, 'tbl_division_system')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_division_system");
		$stmt->execute();
		$divisions = $stmt->fetchAll();
	} else {
		$divisions = [];
	}

} catch (PDOException $e) {
	error_log("[" . __FILE__ . ":" . __LINE__ . " PDO] " . $e->getMessage());
	echo "Connection failed.";
}


function get_points($marks) {
	global $conn;
	$points = 0;
	foreach ((array)$marks as $mark) {
		if ($mark === '' || $mark === null || !is_numeric($mark)) {
			continue;
		}
		list(, , $gradePoints) = report_grade_for_score($conn, (float)$mark, report_default_grading_system_id($conn, 'marks'));
		$points += (float)$gradePoints;
	}
	return $points;
}

function get_division($marks) {
	$the_points = get_points($marks);
	$divisions = $GLOBALS['divisions'];
	$division = '0';
	foreach ($divisions as $divisions_) {
		$min_point = intval($divisions_[3]);
		$max_point = intval($divisions_[4]);
		if ($the_points >= $min_point && $the_points <= $max_point) {
			$division = $divisions_[0];
		}
	}
	return $division;
}
?>
