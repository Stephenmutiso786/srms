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
	$conn->beginTransaction();
	$saveFailed = false;
	$saveError = '';

	foreach ($amounts as $itemIdRaw => $amountRaw) {
		$itemId = (int)$itemIdRaw;
		if ($itemId < 1) continue;

		if (is_string($amountRaw)) {
			$amountRaw = trim($amountRaw);
		}

		if ($amountRaw === '' || $amountRaw === null) {
			$amount = 0.0;
		} elseif (is_numeric($amountRaw)) {
			$amount = (float)$amountRaw;
		} else {
			$saveFailed = true;
			$saveError = 'Invalid fee amount submitted.';
			break;
		}

		$savepoint = app_tx_savepoint_begin($conn, 'fee_structure_item');

		try {
			if ($amount <= 0) {
				$stmt = $conn->prepare("DELETE FROM tbl_fee_structures WHERE class_id = ? AND term_id = ? AND item_id = ?");
				$stmt->execute([$classId, $termId, $itemId]);
				app_tx_savepoint_release($conn, $savepoint);
				continue;
			}

			// Use update-then-insert instead of database-specific upsert syntax so
			// fee structure saving still works on older or partially migrated schemas.
			$stmt = $conn->prepare("UPDATE tbl_fee_structures SET amount = ? WHERE class_id = ? AND term_id = ? AND item_id = ?");
			$stmt->execute([$amount, $classId, $termId, $itemId]);
			if ($stmt->rowCount() < 1) {
				$stmt = $conn->prepare("INSERT INTO tbl_fee_structures (class_id, term_id, item_id, amount) VALUES (?,?,?,?)");
				$stmt->execute([$classId, $termId, $itemId, $amount]);
			}
			app_tx_savepoint_release($conn, $savepoint);
		} catch (Throwable $e) {
			app_tx_savepoint_rollback($conn, $savepoint);
			$saveFailed = true;
			$saveError = $e->getMessage();
			break;
		}
	}

	if ($saveFailed) {
		if ($conn->inTransaction()) {
			$conn->rollBack();
		}
		throw new RuntimeException($saveError !== '' ? $saveError : 'Failed to save fee structure.');
	}

	$conn->commit();
	try {
		app_audit_log($conn, 'staff', (string)$account_id, 'fee_structure.save', 'fee_structure', $classId . ':' . $termId);
	} catch (Throwable $auditError) {
		error_log('[admin.save_fee_structure.audit] ' . $auditError->getMessage());
	}

	$_SESSION['reply'] = array(array("success", "Fee structure saved."));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/fee_structure?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../fee_structure?class_id=".$classId."&term_id=".$termId);
	}
	exit;
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log('[admin.save_fee_structure] ' . $e->getMessage());
	$_SESSION['reply'] = array(array("error", "Failed to save fee structure. Please try again."));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/fee_structure?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../fee_structure?class_id=".$classId."&term_id=".$termId);
	}
	exit;
}
