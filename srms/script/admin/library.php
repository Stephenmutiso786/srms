<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('library.manage', 'admin');
app_require_unlocked('library', 'admin');

$books = [];
$loans = [];
$students = [];
$staff = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_library_books')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_library_books ORDER BY id DESC LIMIT 50");
		$stmt->execute();
		$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_library_loans')) {
		$stmt = $conn->prepare("SELECT l.id, l.book_id, b.title, l.borrower_type, l.borrower_id, l.issued_at, l.due_at, l.returned_at
			FROM tbl_library_loans l
			LEFT JOIN tbl_library_books b ON b.id = l.book_id
			ORDER BY l.issued_at DESC LIMIT 50");
		$stmt->execute();
		$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, mname, lname) AS name FROM tbl_students ORDER BY id");
	$stmt->execute();
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, lname) AS name FROM tbl_staff ORDER BY id");
	$stmt->execute();
	$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load library data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Library - Elimu Hub</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">Elimu Hub</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Administrator</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="admin/library"><i class="app-menu__icon feather icon-book"></i><span class="app-menu__label">Library</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Library Module</h1>
<p>Manage catalog, issue, and returns.</p>
</div>
</div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Add Book</h3>
<form class="app_frm" action="admin/core/new_book" method="POST">
<div class="mb-3">
<label class="form-label">ISBN</label>
<input class="form-control" name="isbn">
</div>
<div class="mb-3">
<label class="form-label">Title</label>
<input class="form-control" name="title" required>
</div>
<div class="mb-3">
<label class="form-label">Author</label>
<input class="form-control" name="author">
</div>
<div class="mb-3">
<label class="form-label">Category</label>
<input class="form-control" name="category">
</div>
<div class="mb-3">
<label class="form-label">Copies</label>
<input type="number" class="form-control" name="copies" min="1" value="1" required>
</div>
<button class="btn btn-primary">Save Book</button>
</form>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">Issue Book</h3>
<form class="app_frm" action="admin/core/issue_book" method="POST">
<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Book</label>
<select class="form-control" name="book_id" required>
<option value="">Select</option>
<?php foreach ($books as $b): ?>
<option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['title']); ?> (<?php echo (int)$b['available']; ?> available)</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Borrower</label>
<select class="form-control" name="borrower" required>
<optgroup label="Students">
<?php foreach ($students as $s): ?>
<option value="student:<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name'].' ('.$s['id'].')'); ?></option>
<?php endforeach; ?>
</optgroup>
<optgroup label="Staff">
<?php foreach ($staff as $st): ?>
<option value="staff:<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['name'].' ('.$st['id'].')'); ?></option>
<?php endforeach; ?>
</optgroup>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Due Date</label>
<input type="date" class="form-control" name="due_at">
</div>
</div>
<button class="btn btn-primary">Issue</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<h3 class="tile-title">Recent Loans</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Book</th><th>Borrower</th><th>Issued</th><th>Due</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($loans as $loan): ?>
<tr>
<td><?php echo htmlspecialchars($loan['title'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($loan['borrower_type'].'#'.$loan['borrower_id']); ?></td>
<td><?php echo htmlspecialchars($loan['issued_at']); ?></td>
<td><?php echo htmlspecialchars($loan['due_at'] ?? '-'); ?></td>
<td><?php echo $loan['returned_at'] ? 'Returned' : 'Active'; ?></td>
<td>
<?php if (!$loan['returned_at']) { ?>
<form class="d-inline" action="admin/core/return_book" method="POST">
<input type="hidden" name="loan_id" value="<?php echo (int)$loan['id']; ?>">
<button class="btn btn-sm btn-outline-success">Mark Returned</button>
</form>
<?php } ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
