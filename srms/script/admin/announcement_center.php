<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/school.php');

if ($res !== '1' || $level !== '0') { header('location:../'); exit; }
app_require_permission('communication.manage', 'admin');

$announcements = [];
$notifications = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (app_table_exists($conn, 'tbl_announcements')) {
		$stmt = $conn->prepare("SELECT id, title, announcement, create_date, level FROM tbl_announcements ORDER BY id DESC LIMIT 12");
		$stmt->execute();
		$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT title, message, audience, created_at FROM tbl_notifications ORDER BY created_at DESC LIMIT 12");
		$stmt->execute();
		$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Announcement Center</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a><ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
	<div class="app-title">
		<div>
			<h1>Announcement Center</h1>
			<p>Draft, publish, and review school-wide announcements and notification traffic from one place.</p>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-5">
			<div class="tile">
				<h3 class="tile-title">Publish Announcement</h3>
				<form class="app_frm" action="admin/core/new_announcement" method="POST">
					<div class="mb-3">
						<label class="form-label">Title</label>
						<input class="form-control" name="title" id="announcementTitle" required>
					</div>
					<div class="mb-3">
						<label class="form-label">Message</label>
						<textarea class="form-control" name="message" id="announcementMessage" rows="7" required></textarea>
					</div>
					<div class="mb-3">
						<label class="form-label">Audience</label>
						<select class="form-control" name="audience" id="announcementAudience" required>
							<option value="students">Students</option>
							<option value="staff">Staff</option>
							<option value="both">Students + Staff</option>
						</select>
					</div>
					<div class="d-flex gap-2 flex-wrap">
						<button class="btn btn-primary">Publish</button>
						<button type="button" class="btn btn-outline-primary" id="announcementAiDraftBtn">Draft with Edu AI</button>
					</div>
				</form>
			</div>
		</div>
		<div class="col-lg-7">
			<div class="tile">
				<h3 class="tile-title">Recent Announcements</h3>
				<div class="note-list">
					<?php if (!$announcements): ?>
					<div class="note-item text-muted">No announcements found.</div>
					<?php endif; ?>
					<?php foreach ($announcements as $item): ?>
					<div class="note-item">
						<div class="fw-bold"><?php echo htmlspecialchars((string)$item['title']); ?></div>
						<div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$item['announcement']); ?></div>
						<div class="small text-muted mt-2"><?php echo htmlspecialchars((string)$item['create_date']); ?></div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>

	<div class="tile mt-3">
		<h3 class="tile-title">Recent Notification Feed</h3>
		<div class="row g-3">
			<?php if (!$notifications): ?>
			<div class="col-12 text-muted">No notifications found.</div>
			<?php endif; ?>
			<?php foreach ($notifications as $item): ?>
			<div class="col-lg-4 col-md-6">
				<div class="border rounded-4 p-3 h-100">
					<div class="fw-bold"><?php echo htmlspecialchars((string)$item['title']); ?></div>
					<div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$item['message']); ?></div>
					<div class="small text-muted mt-2"><?php echo htmlspecialchars((string)($item['audience'] ?? '')); ?> • <?php echo htmlspecialchars((string)$item['created_at']); ?></div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
	var title = document.getElementById('announcementTitle');
	var message = document.getElementById('announcementMessage');
	var audience = document.getElementById('announcementAudience');
	document.getElementById('announcementAiDraftBtn').addEventListener('click', function () {
		var prompt = 'Draft a school announcement for ' + (audience.value || 'students') + '. Title: ' + (title.value || 'Important School Update') + '. Context: ' + (message.value || 'Write a clear, professional, short school notice with action points and dates if needed.');
		message.value = 'Generating announcement draft...';
		fetch('core/ai_feedback.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				category: 'ai',
				tool: 'general',
				language: 'English',
				message: prompt,
				module: 'announcement_center',
				page: 'announcement_center',
				title: 'Announcement Center'
			})
		}).then(function (r) {
			return r.ok ? r.json() : null;
		}).then(function (data) {
			message.value = data && data.ok ? String(data.response || '') : '';
		}).catch(function () {
			message.value = '';
		});
	});
})();
</script>
</body>
</html>
