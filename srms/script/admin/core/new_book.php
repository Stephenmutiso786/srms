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

$isbn = trim($_POST['isbn'] ?? '');
$title = trim($_POST['title'] ?? '');
$author = trim($_POST['author'] ?? '');
$category = trim($_POST['category'] ?? '');
$copies = (int)($_POST['copies'] ?? 1);

if ($title === '' || $copies < 1) {
	$_SESSION['reply'] = array (array("danger", "Title and copies are required."));
	header("location:../library");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_library_books')) {
		$_SESSION['reply'] = array (array("danger", "Library tables missing. Run migration 010."));
		header("location:../library");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_library_books (isbn, title, author, category, copies, available) VALUES (?,?,?,?,?,?)");
	$stmt->execute([$isbn, $title, $author, $category, $copies, $copies]);

	$_SESSION['reply'] = array (array("success", "Book added."));
	header("location:../library");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to add book: " . $e->getMessage()));
	header("location:../library");
}
