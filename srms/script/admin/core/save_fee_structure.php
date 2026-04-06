<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!isset($res) || $res !== "1" || !isset($level) || ($level !== "0" && $level !== "5")) {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../fee_structure");
	exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$amounts = $_POST['amount'] ?? [];

if ($classId < 1 || $termId < 1 || !is_array($amounts)) {
	$_SESSION['reply'] = array(array("error", "Invalid request."));
	header("location:../fee_structure");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$conn->beginTransaction();

	foreach ($amounts as $itemIdRaw => $amountRaw) {
		$itemId = (int)$itemIdRaw;
		$amount = (float)$amountRaw;
		if ($itemId < 1) continue;

		if ($amount <= 0) {
			$stmt = $conn->prepare("DELETE FROM tbl_fee_structures WHERE class_id = ? AND term_id = ? AND item_id = ?");
			$stmt->execute([$classId, $termId, $itemId]);
			continue;
		}

		if ($isPgsql) {
			$stmt = $conn->prepare("INSERT INTO tbl_fee_structures (class_id, term_id, item_id, amount) VALUES (?,?,?,?)
				ON CONFLICT (class_id, term_id, item_id) DO UPDATE SET amount = EXCLUDED.amount");
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_fee_structures (class_id, term_id, item_id, amount) VALUES (?,?,?,?)
				ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
		}
		$stmt->execute([$classId, $termId, $itemId, $amount]);
	}

	$conn->commit();
	app_audit_log($conn, 'staff', (string)$account_id, 'fee_structure.save', 'fee_structure', $classId . ':' . $termId);

	$_SESSION['reply'] = array(array("success", "Fee structure saved."));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/fee_structure?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../fee_structure?class_id=".$classId."&term_id=".$termId);
	}
	exit;
} catch (PDOException $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/fee_structure?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../fee_structure?class_id=".$classId."&term_id=".$termId);
	}
	exit;
}
