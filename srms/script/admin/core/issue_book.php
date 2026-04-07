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

$bookId = (int)($_POST['book_id'] ?? 0);
$borrower = trim($_POST['borrower'] ?? '');
$dueAt = $_POST['due_at'] ?? null;

if ($bookId < 1 || $borrower === '') {
	$_SESSION['reply'] = array (array("danger", "Book and borrower are required."));
	header("location:../library");
	exit;
}

$parts = explode(':', $borrower);
$borrowerType = $parts[0] ?? '';
$borrowerId = $parts[1] ?? '';

if ($borrowerType === '' || $borrowerId === '') {
	$_SESSION['reply'] = array (array("danger", "Invalid borrower."));
	header("location:../library");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_library_books') || !app_table_exists($conn, 'tbl_library_loans')) {
		$_SESSION['reply'] = array (array("danger", "Library tables missing. Run migration 010."));
		header("location:../library");
		exit;
	}

	$stmt = $conn->prepare("SELECT available FROM tbl_library_books WHERE id = ? LIMIT 1");
	$stmt->execute([$bookId]);
	$available = (int)$stmt->fetchColumn();

	if ($available < 1) {
		$_SESSION['reply'] = array (array("danger", "No available copies."));
		header("location:../library");
		exit;
	}

	$conn->beginTransaction();
	$stmt = $conn->prepare("INSERT INTO tbl_library_loans (book_id, borrower_type, borrower_id, due_at) VALUES (?,?,?,?)");
	$stmt->execute([$bookId, $borrowerType, $borrowerId, $dueAt ?: null]);
	$stmt = $conn->prepare("UPDATE tbl_library_books SET available = available - 1 WHERE id = ?");
	$stmt->execute([$bookId]);
	$conn->commit();

	$_SESSION['reply'] = array (array("success", "Book issued."));
	header("location:../library");
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array (array("danger", "Failed to issue: " . $e->getMessage()));
	header("location:../library");
}
