<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../'); exit; }
app_require_permission('bom.manage', '../admin');

$members = [];
$meetings = [];
$approvals = [];
$documents = [];
$bomRoles = app_bom_role_catalog();

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_bom_tables($conn);

	$stmt = $conn->prepare('SELECT * FROM tbl_bom_members ORDER BY status DESC, term_end DESC, id DESC');
	$stmt->execute();
	$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare('SELECT m.*, s.fname AS creator_fname, s.lname AS creator_lname FROM tbl_bom_meetings m LEFT JOIN tbl_staff s ON s.id = m.created_by ORDER BY m.meeting_date DESC, m.id DESC');
	$stmt->execute();
	$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare('SELECT a.*, b.full_name AS approver_name FROM tbl_bom_financial_approvals a LEFT JOIN tbl_bom_members b ON b.id = a.approved_by_member_id ORDER BY a.approval_date DESC, a.id DESC');
	$stmt->execute();
	$approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare('SELECT d.*, s.fname, s.lname FROM tbl_bom_documents d LEFT JOIN tbl_staff s ON s.id = d.uploaded_by ORDER BY d.id DESC');
	$stmt->execute();
	$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Failed to load BOM module.'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - BOM Management</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>BOM Management</h1><p>Manage Board of Management members, meetings, financial approvals, and governance documents.</p></div></div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Add BOM Member</h3>
<form method="POST" action="admin/core/bom_save" class="app_frm">
<input type="hidden" name="entity" value="member">
<div class="mb-2"><label class="form-label">Full Name</label><input class="form-control" name="full_name" required></div>
<div class="mb-2"><label class="form-label">Role</label><select class="form-control" name="role_code" required><option value="">Select</option><?php foreach ($bomRoles as $code => $label): ?><option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">Representing</label><input class="form-control" name="representing" placeholder="Parents/Teacher/Sponsor/Community"></div>
<div class="mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
<div class="mb-2"><label class="form-label">Email</label><input class="form-control" name="email" type="email"></div>
<div class="row"><div class="col-md-6 mb-2"><label class="form-label">Term Start</label><input class="form-control" type="date" name="term_start"></div><div class="col-md-6 mb-2"><label class="form-label">Term End</label><input class="form-control" type="date" name="term_end"></div></div>
<button class="btn btn-primary" type="submit">Save Member</button>
</form>
</div>
</div>
<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">BOM Members</h3>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Name</th><th>Role</th><th>Term</th><th>Contacts</th><th></th></tr></thead><tbody>
<?php foreach ($members as $m): ?>
<tr>
<td><?php echo htmlspecialchars((string)$m['full_name']); ?><br><small><?php echo htmlspecialchars((string)$m['representing']); ?></small></td>
<td><?php echo htmlspecialchars((string)($bomRoles[$m['role_code']] ?? $m['role_code'])); ?></td>
<td><?php echo htmlspecialchars((string)($m['term_start'] ?? '').' to '.(string)($m['term_end'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)$m['phone']); ?><br><?php echo htmlspecialchars((string)$m['email']); ?></td>
<td><a class="btn btn-sm btn-danger" href="admin/core/bom_delete?entity=member&id=<?php echo (int)$m['id']; ?>" onclick="return confirm('Delete member?');">Delete</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$members): ?><tr><td colspan="5" class="text-center text-muted">No BOM members saved.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Meeting Management</h3>
<form method="POST" action="admin/core/bom_save" class="app_frm">
<input type="hidden" name="entity" value="meeting">
<div class="mb-2"><label class="form-label">Meeting Date</label><input class="form-control" type="date" name="meeting_date" required></div>
<div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required></div>
<div class="mb-2"><label class="form-label">Agenda</label><textarea class="form-control" name="agenda" rows="2" required></textarea></div>
<div class="mb-2"><label class="form-label">Minutes</label><textarea class="form-control" name="minutes" rows="2"></textarea></div>
<div class="mb-2"><label class="form-label">Decisions</label><textarea class="form-control" name="decisions" rows="2"></textarea></div>
<div class="mb-2"><label class="form-label">Status</label><select class="form-control" name="status"><option value="planned">Planned</option><option value="held">Held</option><option value="closed">Closed</option></select></div>
<button class="btn btn-primary" type="submit">Save Meeting</button>
</form>
<hr>
<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Title</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($meetings as $mt): ?>
<tr><td><?php echo htmlspecialchars((string)$mt['meeting_date']); ?></td><td><?php echo htmlspecialchars((string)$mt['title']); ?></td><td><?php echo htmlspecialchars((string)$mt['status']); ?></td><td><a class="btn btn-sm btn-danger" href="admin/core/bom_delete?entity=meeting&id=<?php echo (int)$mt['id']; ?>" onclick="return confirm('Delete meeting?');">Delete</a></td></tr>
<?php endforeach; ?>
<?php if (!$meetings): ?><tr><td colspan="4" class="text-center text-muted">No meetings recorded.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Financial Approvals</h3>
<form method="POST" action="admin/core/bom_save" class="app_frm">
<input type="hidden" name="entity" value="approval">
<div class="mb-2"><label class="form-label">Approval Date</label><input class="form-control" type="date" name="approval_date" required></div>
<div class="mb-2"><label class="form-label">Item / Budget Line</label><input class="form-control" name="item_title" required></div>
<div class="mb-2"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" name="amount" required></div>
<div class="mb-2"><label class="form-label">Status</label><select class="form-control" name="status"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></div>
<div class="mb-2"><label class="form-label">Approver</label><select class="form-control" name="approved_by_member_id"><option value="">Select</option><?php foreach ($members as $m): ?><option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars((string)$m['full_name']); ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
<button class="btn btn-primary" type="submit">Save Approval</button>
</form>
<hr>
<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Item</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($approvals as $a): ?>
<tr><td><?php echo htmlspecialchars((string)$a['approval_date']); ?></td><td><?php echo htmlspecialchars((string)$a['item_title']); ?></td><td><?php echo number_format((float)$a['amount'], 2); ?></td><td><?php echo htmlspecialchars((string)$a['status']); ?></td><td><a class="btn btn-sm btn-danger" href="admin/core/bom_delete?entity=approval&id=<?php echo (int)$a['id']; ?>" onclick="return confirm('Delete approval?');">Delete</a></td></tr>
<?php endforeach; ?>
<?php if (!$approvals): ?><tr><td colspan="5" class="text-center text-muted">No approvals captured.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
</div>
</div>

<div class="tile">
<h3 class="tile-title">BOM Documents</h3>
<form method="POST" action="admin/core/bom_upload_document" enctype="multipart/form-data" class="row g-2 app_frm">
<div class="col-md-4"><input class="form-control" name="title" placeholder="Document title" required></div>
<div class="col-md-3"><select class="form-control" name="document_type"><option value="policy">Policy</option><option value="report">Report</option><option value="minutes">Minutes</option><option value="budget">Budget</option><option value="other">Other</option></select></div>
<div class="col-md-3"><input class="form-control" type="file" name="document" required></div>
<div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Upload</button></div>
</form>
<hr>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Title</th><th>Type</th><th>File</th><th>Uploaded</th><th></th></tr></thead><tbody>
<?php foreach ($documents as $d): ?>
<tr><td><?php echo htmlspecialchars((string)$d['title']); ?></td><td><?php echo htmlspecialchars((string)$d['document_type']); ?></td><td><a href="<?php echo htmlspecialchars((string)$d['file_path']); ?>" target="_blank">Open</a></td><td><?php echo htmlspecialchars((string)$d['uploaded_at']); ?></td><td><a class="btn btn-sm btn-danger" href="admin/core/bom_delete?entity=document&id=<?php echo (int)$d['id']; ?>" onclick="return confirm('Delete document?');">Delete</a></td></tr>
<?php endforeach; ?>
<?php if (!$documents): ?><tr><td colspan="5" class="text-center text-muted">No BOM documents uploaded.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
