<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Online Users</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.online-pill { display:inline-flex; align-items:center; gap:6px; font-weight:700; }
.online-dot { width:9px; height:9px; border-radius:999px; background:#20b65d; }
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
<h1>Online Users</h1>
<p>Live presence monitor for all portal users.</p>
</div>
</div>

<div class="tile mb-3">
<div class="row g-2">
<div class="col-md-3">
<label class="form-label">Role</label>
<select class="form-control" id="roleFilter">
<option value="">All</option>
<option value="staff">Staff</option>
<option value="student">Students</option>
<option value="parent">Parents</option>
</select>
</div>
<div class="col-md-3">
<label class="form-label">Class</label>
<select class="form-control" id="classFilter">
<option value="">All Classes</option>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Search</label>
<input type="text" class="form-control" id="searchFilter" placeholder="Search by name">
</div>
<div class="col-md-2 d-flex align-items-end">
<button class="btn btn-primary w-100" id="refreshBtn" type="button"><i class="bi bi-arrow-repeat me-1"></i>Refresh</button>
</div>
</div>
<div class="mt-2 text-muted small" id="onlineMeta">Loading...</div>
</div>

<div class="tile">
<div class="table-responsive">
<table class="table table-hover table-striped">
<thead>
<tr>
<th>Status</th>
<th>Name</th>
<th>Role</th>
<th>Class</th>
<th>Last Seen</th>
<th>ID</th>
</tr>
</thead>
<tbody id="onlineUsersBody">
<tr><td colspan="6" class="text-muted">Loading online users...</td></tr>
</tbody>
</table>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
  var body = document.getElementById('onlineUsersBody');
  var roleFilter = document.getElementById('roleFilter');
  var classFilter = document.getElementById('classFilter');
  var searchFilter = document.getElementById('searchFilter');
  var refreshBtn = document.getElementById('refreshBtn');
  var meta = document.getElementById('onlineMeta');
  var allUsers = [];

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatTimestamp(value) {
    if (!value) return '-';
    var normalized = String(value).replace(' ', 'T');
    var d = new Date(normalized);
    if (isNaN(d.getTime())) {
      return escapeHtml(value);
    }
    return escapeHtml(d.toLocaleString());
  }

  function buildClassOptions(users) {
    var seen = {};
    var options = ['<option value="">All Classes</option>'];
    users.forEach(function (u) {
      var className = (u.class_name || '').trim();
      if (!className) return;
      if (seen[className]) return;
      seen[className] = true;
      options.push('<option value="' + escapeHtml(className) + '">' + escapeHtml(className) + '</option>');
    });
    classFilter.innerHTML = options.join('');
  }

  function applyFilters() {
    var roleVal = roleFilter.value;
    var classVal = classFilter.value;
    var text = (searchFilter.value || '').toLowerCase().trim();

    var filtered = allUsers.filter(function (u) {
      if (roleVal && (u.scope || '') !== roleVal) return false;
      if (classVal && (u.class_name || '') !== classVal) return false;
      if (text && (String(u.name || '').toLowerCase().indexOf(text) === -1)) return false;
      return true;
    });

    if (!filtered.length) {
      body.innerHTML = '<tr><td colspan="6" class="text-muted">No online users match the current filters.</td></tr>';
      return;
    }

    body.innerHTML = filtered.map(function (u) {
      var className = u.class_name ? escapeHtml(u.class_name) : '-';
      var lastSeen = formatTimestamp(u.last_seen || '');
      return '' +
        '<tr>' +
          '<td><span class="online-pill"><span class="online-dot"></span>Online</span></td>' +
          '<td>' + escapeHtml(u.name) + '</td>' +
          '<td>' + escapeHtml(u.role) + '</td>' +
          '<td>' + className + '</td>' +
          '<td>' + lastSeen + '</td>' +
          '<td>' + escapeHtml(u.id) + '</td>' +
        '</tr>';
    }).join('');
  }

  function refreshOnline() {
    fetch('core/online_users.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) {
          body.innerHTML = '<tr><td colspan="6" class="text-danger">Failed to load online users.</td></tr>';
          meta.textContent = 'Refresh failed';
          return;
        }

        allUsers = Array.isArray(data.users) ? data.users : [];
        buildClassOptions(allUsers);
        applyFilters();

        var now = new Date();
        meta.textContent = 'Online now: ' + (data.count || allUsers.length || 0) + ' | Last updated: ' + now.toLocaleTimeString();
      })
      .catch(function () {
        body.innerHTML = '<tr><td colspan="6" class="text-danger">Failed to load online users.</td></tr>';
        meta.textContent = 'Refresh failed';
      });
  }

  roleFilter.addEventListener('change', applyFilters);
  classFilter.addEventListener('change', applyFilters);
  searchFilter.addEventListener('input', applyFilters);
  refreshBtn.addEventListener('click', refreshOnline);

  refreshOnline();
  window.setInterval(refreshOnline, 10000);
})();
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
