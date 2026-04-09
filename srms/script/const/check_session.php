<?php
// Session checker used across all role dashboards.
// Populates: $res, $level, $account_id (+ user fields).

$res = "0";

if (!isset($_COOKIE["__SRMS__logged"]) || !isset($_COOKIE["__SRMS__key"])) {
	return;
}

$session_key = (string)$_COOKIE["__SRMS__key"];
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$level = (string)$_COOKIE["__SRMS__logged"];
$levelInt = (int)$level;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	// Staff roles: admin(0), academic(1), teacher(2), accountant(5), etc.
	if ($levelInt !== 3 && $levelInt !== 4) {
		$stmt = $conn->prepare("SELECT ls.session_key, ls.ip_address, s.*
			FROM tbl_login_sessions ls
			JOIN tbl_staff s ON s.id = ls.staff
			WHERE ls.session_key = ?
			LIMIT 1");
		$stmt->execute([$session_key]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$res = "0";
			return;
		}

		if (app_session_enforce_ip() && ($row['ip_address'] ?? '') !== $current_ip) {
			$res = "3";
			return;
		}

		$status = (string)($row['status'] ?? '0');
		if ($status !== "1") {
			$res = "2";
			return;
		}

		$account_id = (string)$row['id'];
		$fname = (string)$row['fname'];
		$lname = (string)$row['lname'];
		$gender = (string)$row['gender'];
		$email = (string)$row['email'];
		$login = (string)$row['password'];
		$level = (string)$row['level'];
		if ($level === "9") {
			$super_admin = true;
			$level = "0";
		}
		$res = "1";
		return;
	}

	if ($levelInt === 3) {
		$stmt = $conn->prepare("SELECT ls.session_key, ls.ip_address, st.*
			FROM tbl_login_sessions ls
			JOIN tbl_students st ON st.id = ls.student
			WHERE ls.session_key = ?
			LIMIT 1");
		$stmt->execute([$session_key]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$res = "0";
			return;
		}

		if (app_session_enforce_ip() && ($row['ip_address'] ?? '') !== $current_ip) {
			$res = "3";
			return;
		}

		$status = (string)($row['status'] ?? '0');
		if ($status !== "1") {
			$res = "2";
			return;
		}

		$account_id = (string)$row['id'];
		$fname = (string)$row['fname'];
		$mname = (string)$row['mname'];
		$lname = (string)$row['lname'];
		$gender = (string)$row['gender'];
		$email = (string)$row['email'];
		$class = (string)$row['class'];
		$login = (string)$row['password'];
		$level = (string)$row['level'];
		$img = (string)$row['display_image'];

		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$class]);
		$act_class = (string)($stmt->fetchColumn() ?: '');

		$res = "1";
		return;
	}

	// Parent portal (level=4). Requires migration adding tbl_parents and tbl_login_sessions.parent
	if ($levelInt === 4 && app_table_exists($conn, 'tbl_parents') && app_column_exists($conn, 'tbl_login_sessions', 'parent')) {
		$stmt = $conn->prepare("SELECT ls.session_key, ls.ip_address, p.*
			FROM tbl_login_sessions ls
			JOIN tbl_parents p ON p.id = ls.parent
			WHERE ls.session_key = ?
			LIMIT 1");
		$stmt->execute([$session_key]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$res = "0";
			return;
		}

		if (app_session_enforce_ip() && ($row['ip_address'] ?? '') !== $current_ip) {
			$res = "3";
			return;
		}

		$status = (string)($row['status'] ?? '0');
		if ($status !== "1") {
			$res = "2";
			return;
		}

		$account_id = (string)$row['id'];
		$fname = (string)$row['fname'];
		$lname = (string)$row['lname'];
		$phone = (string)($row['phone'] ?? '');
		$email = (string)$row['email'];
		$login = (string)$row['password'];
		$level = "4";
		$res = "1";
		return;
	}
} catch (PDOException $e) {
	// Keep $res=0 (treat as not logged in).
}
