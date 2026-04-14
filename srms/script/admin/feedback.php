<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || !in_array((string)$level, ['0', '1'], true)) { header("location:../"); exit; }
app_require_permission('system.manage', 'admin');

$categoryFilter = trim((string)($_GET['category'] ?? 'all'));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$rows = [];
$summary = ['open' => 0, 'resolved' => 0, 'answered' => 0, 'total' => 0];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_ai_feedback')) {
		$where = [];
		$params = [];
		if ($categoryFilter !== '' && $categoryFilter !== 'all') {
			$where[] = 'category = ?';
			$params[] = $categoryFilter;
		}
		if ($statusFilter !== '' && $statusFilter !== 'all' && app_column_exists($conn, 'tbl_ai_feedback', 'status')) {
			$where[] = 'status = ?';
			$params[] = $statusFilter;
		}
		$sql = 'SELECT * FROM tbl_ai_feedback';
		if ($where) {
			$sql .= ' WHERE ' . implode(' AND ', $where);
		}
		$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 120';
		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rows as $row) {
			$status = (string)($row['status'] ?? ($row['category'] === 'ai' ? 'answered' : 'open'));
			if (!isset($summary[$status])) {
				$summary[$status] = 0;
			}
			$summary[$status]++;
			$summary['total']++;
		}
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - AI & Feedback</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.feedback-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:18px}
.feedback-stat{background:#fff;border-radius:18px;padding:16px;box-shadow:0 12px 32px rgba(9,30,66,.08)}
.feedback-stat .label{font-size:.75rem;text-transform:uppercase;color:#6b7280}
.feedback-stat .value{font-size:1.6rem;font-weight:800;color:#123}
.feedback-card{background:#fff;border-radius:20px;box-shadow:0 12px 32px rgba(9,30,66,.08);overflow:hidden}
.feedback-card .table td,.feedback-card .table th{vertical-align:top}
.feedback-message{white-space:pre-wrap;min-width:220px}
.feedback-response{white-space:pre-wrap;min-width:220px}
@media (max-width: 991px){.feedback-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width: 576px){.feedback-grid{grid-template-columns:1fr}}
</style>
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

<?php include('admin/partials/sidebar.php'); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>AI & Feedback</h1>
<p class="mb-0 text-muted">Review Edu Assist conversations and parent/student feedback in one inbox.</p>
</div>
</div>

<div class="feedback-grid">
  <div class="feedback-stat"><div class="label">Total</div><div class="value"><?php echo (int)$summary['total']; ?></div></div>
  <div class="feedback-stat"><div class="label">Open</div><div class="value"><?php echo (int)$summary['open']; ?></div></div>
  <div class="feedback-stat"><div class="label">Resolved</div><div class="value"><?php echo (int)$summary['resolved']; ?></div></div>
  <div class="feedback-stat"><div class="label">Answered</div><div class="value"><?php echo (int)$summary['answered']; ?></div></div>
</div>

<div class="feedback-card">
  <div class="p-3 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <form class="d-flex flex-wrap gap-2 align-items-end" method="get">
      <div>
        <label class="form-label">Category</label>
        <select class="form-control" name="category">
          <option value="all" <?php echo $categoryFilter === 'all' ? 'selected' : ''; ?>>All</option>
          <option value="ai" <?php echo $categoryFilter === 'ai' ? 'selected' : ''; ?>>AI Chat</option>
          <option value="feedback" <?php echo $categoryFilter === 'feedback' ? 'selected' : ''; ?>>Feedback</option>
        </select>
      </div>
      <div>
        <label class="form-label">Status</label>
        <select class="form-control" name="status">
          <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
          <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
          <option value="answered" <?php echo $statusFilter === 'answered' ? 'selected' : ''; ?>>Answered</option>
          <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
        </select>
      </div>
      <div>
        <button class="btn btn-primary">Filter</button>
      </div>
    </form>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="admin"><i class="bi bi-arrow-left me-2"></i>Dashboard</a>
      <button class="btn btn-outline-primary" onclick="window.print();"><i class="bi bi-printer me-2"></i>Print</button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Type</th>
          <th>Actor</th>
          <th>Subject</th>
          <th>Message</th>
          <th>AI / Reply</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows) { ?>
        <tr><td colspan="7" class="text-muted">No feedback or AI conversations found.</td></tr>
      <?php } ?>
      <?php foreach ($rows as $row): ?>
        <?php
          $status = (string)($row['status'] ?? ($row['category'] === 'ai' ? 'answered' : 'open'));
          $badge = $status === 'resolved' ? 'success' : ($status === 'answered' ? 'primary' : 'warning text-dark');
        ?>
        <tr>
          <td><span class="badge bg-secondary"><?php echo htmlspecialchars(strtoupper((string)$row['category'])); ?></span></td>
          <td>
            <div class="fw-semibold"><?php echo htmlspecialchars((string)$row['actor_type']); ?></div>
            <div class="text-muted small"><?php echo htmlspecialchars((string)$row['actor_id']); ?></div>
          </td>
          <td><?php echo htmlspecialchars((string)($row['subject'] ?? 'General')); ?></td>
          <td class="feedback-message"><?php echo htmlspecialchars((string)$row['message']); ?></td>
          <td class="feedback-response"><?php echo htmlspecialchars((string)($row['reply_message'] ?? $row['ai_response'] ?? '')); ?></td>
          <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
          <td style="min-width:260px;">
            <form action="admin/core/feedback_action" method="POST" class="d-grid gap-2">
              <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
              <select class="form-control form-control-sm" name="status">
                <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Open</option>
                <option value="answered" <?php echo $status === 'answered' ? 'selected' : ''; ?>>Answered</option>
                <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
              </select>
              <textarea class="form-control form-control-sm" name="reply_message" rows="2" placeholder="Reply or internal note"><?php echo htmlspecialchars((string)($row['reply_message'] ?? '')); ?></textarea>
              <button class="btn btn-sm btn-primary" type="submit">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>