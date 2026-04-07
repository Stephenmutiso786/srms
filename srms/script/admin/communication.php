<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('communication.manage', 'admin');
app_require_unlocked('communication', 'admin');

$announcements = [];
$messages = [];
$smsLogs = [];
$emailLogs = [];
$students = [];
$parents = [];
$staff = [];
$smsSettings = ['provider' => 'custom', 'api_url' => '', 'api_key' => '', 'sender_id' => '', 'status' => 0];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_announcements')) {
		$stmt = $conn->prepare("SELECT id, title, announcement, create_date, level FROM tbl_announcements ORDER BY id DESC LIMIT 20");
		$stmt->execute();
		$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_messages')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_messages ORDER BY created_at DESC LIMIT 20");
		$stmt->execute();
		$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_sms_logs')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_sms_logs ORDER BY created_at DESC LIMIT 20");
		$stmt->execute();
		$smsLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_email_logs')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_email_logs ORDER BY created_at DESC LIMIT 20");
		$stmt->execute();
		$emailLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_sms_settings')) {
		$stmt = $conn->prepare("SELECT provider, api_url, api_key, sender_id, status FROM tbl_sms_settings ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) { $smsSettings = $row; }
	}

	$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, mname, lname) AS name FROM tbl_students ORDER BY id");
	$stmt->execute();
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (app_table_exists($conn, 'tbl_parents')) {
		$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, lname) AS name FROM tbl_parents ORDER BY id");
		$stmt->execute();
		$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, lname) AS name, level FROM tbl_staff ORDER BY id");
	$stmt->execute();
	$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load communication data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Communication</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
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
<li><a class="app-menu__item active" href="admin/communication"><i class="app-menu__icon feather icon-message-circle"></i><span class="app-menu__label">Communication</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Communication Module</h1>
<p>Announcements, internal messaging, and SMS/email hooks.</p>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Create Announcement</h3>
<form class="app_frm" action="admin/core/new_announcement" method="POST">
<div class="mb-3">
<label class="form-label">Title</label>
<input class="form-control" name="title" required>
</div>
<div class="mb-3">
<label class="form-label">Message</label>
<textarea class="form-control" name="message" rows="4" required></textarea>
</div>
<div class="mb-3">
<label class="form-label">Audience</label>
<select class="form-control" name="audience" required>
<option value="students">Students</option>
<option value="staff">Staff</option>
<option value="both">Students + Staff</option>
</select>
</div>
<button class="btn btn-primary">Publish</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Internal Message</h3>
<form class="app_frm" action="admin/core/send_message" method="POST">
<div class="mb-3">
<label class="form-label">Recipient Type</label>
<select class="form-control" name="recipient_type" required>
<option value="student">Student</option>
<option value="parent">Parent</option>
<option value="staff">Staff</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Recipient</label>
<select class="form-control" name="recipient_id" required>
<optgroup label="Students">
<?php foreach ($students as $s): ?>
<option value="student:<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name'].' ('.$s['id'].')'); ?></option>
<?php endforeach; ?>
</optgroup>
<optgroup label="Parents">
<?php foreach ($parents as $p): ?>
<option value="parent:<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name'].' ('.$p['id'].')'); ?></option>
<?php endforeach; ?>
</optgroup>
<optgroup label="Staff">
<?php foreach ($staff as $st): ?>
<option value="staff:<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['name'].' ('.$st['id'].')'); ?></option>
<?php endforeach; ?>
</optgroup>
</select>
</div>
<div class="mb-3">
<label class="form-label">Subject</label>
<input class="form-control" name="subject" placeholder="Message subject">
</div>
<div class="mb-3">
<label class="form-label">Message</label>
<textarea class="form-control" name="body" rows="4" required></textarea>
</div>
<button class="btn btn-primary">Send Message</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">SMS Gateway Settings</h3>
<form class="app_frm" action="admin/core/save_sms_settings" method="POST">
<div class="mb-3">
<label class="form-label">Provider Name</label>
<input class="form-control" name="provider" value="<?php echo htmlspecialchars((string)$smsSettings['provider']); ?>" placeholder="Africa's Talking / Twilio / Custom">
</div>
<div class="mb-3">
<label class="form-label">API URL (POST)</label>
<input class="form-control" name="api_url" value="<?php echo htmlspecialchars((string)$smsSettings['api_url']); ?>" placeholder="https://api.provider.com/send">
</div>
<div class="mb-3">
<label class="form-label">API Key</label>
<input class="form-control" name="api_key" value="<?php echo htmlspecialchars((string)$smsSettings['api_key']); ?>" placeholder="Bearer token">
</div>
<div class="mb-3">
<label class="form-label">Sender ID</label>
<input class="form-control" name="sender_id" value="<?php echo htmlspecialchars((string)$smsSettings['sender_id']); ?>" placeholder="SchoolName">
</div>
<div class="mb-3">
<label class="form-label">Enabled</label>
<select class="form-control" name="status">
<option value="1" <?php echo ((int)$smsSettings['status'] === 1) ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo ((int)$smsSettings['status'] === 0) ? 'selected' : ''; ?>>No</option>
</select>
</div>
<button class="btn btn-primary">Save SMS Settings</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">SMS Hook</h3>
<form class="app_frm" action="admin/core/send_sms" method="POST">
<div class="mb-3">
<label class="form-label">Recipient (Phone)</label>
<input class="form-control" name="recipient" required placeholder="+2547xxxxxxx">
</div>
<div class="mb-3">
<label class="form-label">Message</label>
<textarea class="form-control" name="message" rows="3" required></textarea>
</div>
<button class="btn btn-outline-primary">Queue SMS</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Email Hook</h3>
<form class="app_frm" action="admin/core/send_email" method="POST">
<div class="mb-3">
<label class="form-label">Recipient (Email)</label>
<input class="form-control" type="email" name="recipient" required>
</div>
<div class="mb-3">
<label class="form-label">Subject</label>
<input class="form-control" name="subject" required>
</div>
<div class="mb-3">
<label class="form-label">Message</label>
<textarea class="form-control" name="message" rows="3" required></textarea>
</div>
<button class="btn btn-outline-primary">Queue Email</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Recent Announcements</h3>
<div class="table-responsive">
<form id="bulkAnnouncementsForm" method="POST" action="admin/core/bulk_delete_announcements" onsubmit="return confirmBulkDelete('announcement');">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
  <div class="form-check ms-2">
	<input class="form-check-input" type="checkbox" id="selectAllAnnouncements">
	<label class="form-check-label" for="selectAllAnnouncements">Select all</label>
  </div>
</div>
<table class="table table-hover">
<thead><tr><th width="40"><input class="form-check-input" type="checkbox" id="selectAllAnnouncementsHead"></th><th>Title</th><th>Audience</th><th>Date</th></tr></thead>
<tbody>
<?php foreach ($announcements as $a): ?>
<tr>
<td><input class="form-check-input announcement-checkbox" type="checkbox" name="announcement_ids[]" value="<?php echo (int)$a['id']; ?>"></td>
<td><?php echo htmlspecialchars($a['title']); ?></td>
<td><?php echo ((int)$a['level'] === 2) ? 'Both' : (((int)$a['level'] === 1) ? 'Students' : 'Staff'); ?></td>
<td><?php echo htmlspecialchars($a['create_date']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</form>
</div>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Recent Messages</h3>
<div class="table-responsive">
<form id="bulkMessagesForm" method="POST" action="admin/core/bulk_delete_messages" onsubmit="return confirmBulkDelete('message');">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
  <div class="form-check ms-2">
	<input class="form-check-input" type="checkbox" id="selectAllMessages">
	<label class="form-check-label" for="selectAllMessages">Select all</label>
  </div>
</div>
<table class="table table-hover">
<thead><tr><th width="40"><input class="form-check-input" type="checkbox" id="selectAllMessagesHead"></th><th>From</th><th>To</th><th>Subject</th><th>Date</th></tr></thead>
<tbody>
<?php foreach ($messages as $m): ?>
<tr>
<td><input class="form-check-input message-checkbox" type="checkbox" name="message_ids[]" value="<?php echo (int)$m['id']; ?>"></td>
<td><?php echo htmlspecialchars($m['sender_type'].'#'.$m['sender_id']); ?></td>
<td><?php echo htmlspecialchars($m['recipient_type'].'#'.$m['recipient_id']); ?></td>
<td><?php echo htmlspecialchars($m['subject']); ?></td>
<td><?php echo htmlspecialchars($m['created_at']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</form>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">SMS Log</h3>
<div class="table-responsive">
<form id="bulkSmsLogsForm" method="POST" action="admin/core/bulk_delete_sms_logs" onsubmit="return confirmBulkDelete('SMS log');">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
  <div class="form-check ms-2">
	<input class="form-check-input" type="checkbox" id="selectAllSmsLogs">
	<label class="form-check-label" for="selectAllSmsLogs">Select all</label>
  </div>
</div>
<table class="table table-hover">
<thead><tr><th width="40"><input class="form-check-input" type="checkbox" id="selectAllSmsLogsHead"></th><th>Recipient</th><th>Status</th><th>Date</th></tr></thead>
<tbody>
<?php foreach ($smsLogs as $s): ?>
<tr>
<td><input class="form-check-input smslog-checkbox" type="checkbox" name="sms_log_ids[]" value="<?php echo (int)$s['id']; ?>"></td>
<td><?php echo htmlspecialchars($s['recipient']); ?></td>
<td><?php echo htmlspecialchars($s['status']); ?></td>
<td><?php echo htmlspecialchars($s['created_at']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</form>
</div>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Email Log</h3>
<div class="table-responsive">
<form id="bulkEmailLogsForm" method="POST" action="admin/core/bulk_delete_email_logs" onsubmit="return confirmBulkDelete('email log');">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
  <div class="form-check ms-2">
	<input class="form-check-input" type="checkbox" id="selectAllEmailLogs">
	<label class="form-check-label" for="selectAllEmailLogs">Select all</label>
  </div>
</div>
<table class="table table-hover">
<thead><tr><th width="40"><input class="form-check-input" type="checkbox" id="selectAllEmailLogsHead"></th><th>Recipient</th><th>Status</th><th>Date</th></tr></thead>
<tbody>
<?php foreach ($emailLogs as $e): ?>
<tr>
<td><input class="form-check-input emaillog-checkbox" type="checkbox" name="email_log_ids[]" value="<?php echo (int)$e['id']; ?>"></td>
<td><?php echo htmlspecialchars($e['recipient']); ?></td>
<td><?php echo htmlspecialchars($e['status']); ?></td>
<td><?php echo htmlspecialchars($e['created_at']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</form>
</div>
</div>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
function confirmBulkDelete(label){
  var map = {
    announcement: '.announcement-checkbox:checked',
    message: '.message-checkbox:checked',
    'SMS log': '.smslog-checkbox:checked',
    'email log': '.emaillog-checkbox:checked'
  };
  var selector = map[label] || 'input[type="checkbox"]:checked';
  if (!document.querySelectorAll(selector).length) {
    alert('Please select at least one ' + label + ' to delete.');
    return false;
  }
  return confirm('Delete selected ' + label + ' records? This action cannot be undone.');
}
function bindSelectAll(sourceId, targetClass) {
  var source = document.getElementById(sourceId);
  if (!source) return;
  source.addEventListener('change', function(){
    document.querySelectorAll(targetClass).forEach(function(cb){
      cb.checked = source.checked;
    });
  });
}
bindSelectAll('selectAllAnnouncements', '.announcement-checkbox');
bindSelectAll('selectAllAnnouncementsHead', '.announcement-checkbox');
bindSelectAll('selectAllMessages', '.message-checkbox');
bindSelectAll('selectAllMessagesHead', '.message-checkbox');
bindSelectAll('selectAllSmsLogs', '.smslog-checkbox');
bindSelectAll('selectAllSmsLogsHead', '.smslog-checkbox');
bindSelectAll('selectAllEmailLogs', '.emaillog-checkbox');
bindSelectAll('selectAllEmailLogsHead', '.emaillog-checkbox');
</script>
</body>
</html>
