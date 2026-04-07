<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('library.manage', '../library');
app_require_unlocked('library', '../library');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../library");
	exit;
}

$loanId = (int)($_POST['loan_id'] ?? 0);

if ($loanId < 1) {
	$_SESSION['reply'] = array (array("danger", "Invalid request."));
	header("location:../library");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_library_loans')) {
		$_SESSION['reply'] = array (array("danger", "Library tables missing. Run migration 010."));
		header("location:../library");
		exit;
	}

	$stmt = $conn->prepare("SELECT book_id, returned_at FROM tbl_library_loans WHERE id = ? LIMIT 1");
	$stmt->execute([$loanId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		$_SESSION['reply'] = array (array("danger", "Loan not found."));
		header("location:../library");
		exit;
	}
	if (!empty($row['returned_at'])) {
		$_SESSION['reply'] = array (array("info", "Loan already returned."));
		header("location:../library");
		exit;
	}

	$conn->beginTransaction();
	$stmt = $conn->prepare("UPDATE tbl_library_loans SET returned_at = CURRENT_TIMESTAMP WHERE id = ?");
	$stmt->execute([$loanId]);
	$stmt = $conn->prepare("UPDATE tbl_library_books SET available = available + 1 WHERE id = ?");
	$stmt->execute([(int)$row['book_id']]);
	$conn->commit();

	$_SESSION['reply'] = array (array("success", "Book returned."));
	header("location:../library");
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array (array("danger", "Failed to return: " . $e->getMessage()));
	header("location:../library");
}
