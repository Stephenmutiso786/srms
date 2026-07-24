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
$edubotStats = ['messages' => 0, 'actors' => 0];
$aiMeta = ['provider' => 'Internal Edu AI', 'model' => 'Built-in fallback', 'enabled' => false];

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

  if (app_table_exists($conn, 'tbl_edubot_memory')) {
    $stmt = $conn->query('SELECT COUNT(*) FROM tbl_edubot_memory');
    $edubotStats['messages'] = (int)$stmt->fetchColumn();
    if (app_column_exists($conn, 'tbl_edubot_memory', 'actor_id')) {
      $stmt = $conn->query("SELECT COUNT(DISTINCT CONCAT(actor_type, ':', actor_id)) FROM tbl_edubot_memory");
      $edubotStats['actors'] = (int)$stmt->fetchColumn();
    }
  }
	$provider = strtolower(trim(app_setting_get($conn, 'ai_provider', 'gemini')));
	$model = trim(app_setting_get($conn, 'ai_model', $provider === 'gemini' ? 'gemini-2.0-flash' : 'gpt-4o-mini'));
	$key = trim(app_setting_get($conn, 'ai_api_key', ''));
	$aiMeta = [
		'provider' => $provider === 'gemini' ? 'Google Gemini' : ($provider === 'openai' ? 'OpenAI' : 'Internal Edu AI'),
		'model' => $model !== '' ? $model : 'Built-in fallback',
		'enabled' => app_setting_get($conn, 'ai_enabled', '1') === '1' && $key !== '',
	];
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Edu Bot & Feedback</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.feedback-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:18px}
.bot-shell{background:linear-gradient(180deg,#0f2f4a 0%,#123d5f 100%);border-radius:24px;padding:18px;box-shadow:0 18px 40px rgba(9,30,66,.14);color:#fff;margin-bottom:18px}
.bot-shell__header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.bot-shell__title{font-size:1.35rem;font-weight:800;line-height:1.1;margin:0}
.bot-shell__meta{font-size:.88rem;color:rgba(255,255,255,.78);margin-top:6px}
.bot-shell__pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:rgba(255,255,255,.12);backdrop-filter:blur(12px);font-size:.82rem;font-weight:700}
.bot-chat{background:#f8fbff;border-radius:20px;padding:14px;border:1px solid rgba(255,255,255,.12);box-shadow:inset 0 1px 0 rgba(255,255,255,.45)}
.bot-history{height:380px;overflow:auto;padding:6px 4px 10px 4px;display:flex;flex-direction:column;gap:10px}
.bot-msg{max-width:min(78%,760px);padding:12px 14px;border-radius:18px;line-height:1.45;white-space:pre-wrap;word-break:break-word}
.bot-msg.user{align-self:flex-end;background:linear-gradient(135deg,#1db14b,#0f8a3c);color:#fff;border-bottom-right-radius:6px}
.bot-msg.bot{align-self:flex-start;background:#fff;color:#173042;border:1px solid #d9e4ee;border-bottom-left-radius:6px}
.bot-msg .meta{display:block;font-size:.72rem;opacity:.72;margin-top:6px}
.bot-composer{display:flex;gap:10px;align-items:center;margin-top:12px}
.bot-composer textarea{flex:1;min-height:54px;resize:vertical;border-radius:16px;border:1px solid #cbd7e2;padding:12px 14px;font-size:.95rem;background:#fff}
.bot-composer button{border:0;border-radius:16px;padding:13px 18px;font-weight:800;background:#0f2f4a;color:#fff;box-shadow:0 10px 24px rgba(15,47,74,.22)}
.bot-quick{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.bot-quick button{border:1px solid #d5e0ea;background:#fff;color:#173042;border-radius:999px;padding:8px 12px;font-size:.82rem;font-weight:700}
.bot-typing{display:inline-flex;align-items:center;gap:6px;color:#6b7280;font-style:italic;padding:2px 4px}
.bot-dots{display:inline-flex;gap:4px}
.bot-dots span{width:6px;height:6px;border-radius:999px;background:#6b7280;animation:botPulse 1.2s infinite ease-in-out}
.bot-dots span:nth-child(2){animation-delay:.15s}
.bot-dots span:nth-child(3){animation-delay:.3s}
@keyframes botPulse{0%,80%,100%{opacity:.25;transform:translateY(0)}40%{opacity:1;transform:translateY(-2px)}}
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
<h1>Edu AI & Feedback</h1>
<p class="mb-0 text-muted">Chat with the upgraded Edu AI workbench, review memory-backed conversations, and manage feedback in one place.</p>
</div>
</div>

<div class="bot-shell">
  <div class="bot-shell__header">
    <div>
      <div class="bot-shell__title">Edu AI Workbench</div>
      <div class="bot-shell__meta">Gemini-ready school assistant for report comments, exams, lesson plans, attendance, fees, discipline, translation, and analytics.</div>
    </div>
    <div class="bot-shell__pill"><i class="bi bi-chat-dots"></i> <?php echo (int)$edubotStats['messages']; ?> stored messages • <?php echo (int)$edubotStats['actors']; ?> users • <?php echo htmlspecialchars($aiMeta['provider']); ?><?php echo $aiMeta['enabled'] ? ' • ' . htmlspecialchars($aiMeta['model']) : ' • fallback only'; ?></div>
  </div>
  <div class="bot-chat">
    <div id="botHistory" class="bot-history" aria-live="polite"></div>
    <div id="botTyping" class="bot-typing d-none"><span>Edu Bot is typing</span><span class="bot-dots"><span></span><span></span><span></span></span></div>
    <div class="bot-quick">
      <button type="button" data-bot-tool="report_comments" data-bot-prompt="Generate CBC report comments for a learner who is strong in Science but inconsistent in Languages.">Report comments</button>
      <button type="button" data-bot-tool="exam_generator" data-bot-prompt="Generate Grade 7 integrated science CBC questions on the digestive system.">Exam generator</button>
      <button type="button" data-bot-tool="lesson_plan" data-bot-prompt="Create a Grade 3 CBC lesson plan for reading comprehension.">Lesson plan</button>
      <button type="button" data-bot-tool="attendance_analysis" data-bot-prompt="Analyse attendance trends and suggest interventions for recurring absences.">Attendance analysis</button>
      <button type="button" data-bot-tool="discipline_letter" data-bot-prompt="Draft a discipline letter for a learner repeatedly coming late to school.">Discipline letter</button>
      <button type="button" data-bot-tool="translation" data-bot-prompt="Translate this into Swahili: Dear parent, your child has shown great improvement this term.">Translation</button>
    </div>
    <div class="row g-2 mt-2">
      <div class="col-md-6">
        <label class="form-label">AI Tool</label>
        <select id="botTool" class="form-control">
          <option value="general">General Assistant</option>
          <option value="report_comments">Report Comments</option>
          <option value="exam_generator">Exam Generator</option>
          <option value="lesson_plan">Lesson Plan</option>
          <option value="performance_analysis">Performance Analysis</option>
          <option value="attendance_analysis">Attendance Analysis</option>
          <option value="discipline_letter">Discipline Letter</option>
          <option value="fee_reminder">Fee Reminder</option>
          <option value="translation">Translate EN/Sw</option>
          <option value="assignment_generator">Assignment Generator</option>
          <option value="grading_assistance">Grading Help</option>
          <option value="timetable_suggestions">Timetable Suggestions</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Response Language</label>
        <select id="botLanguage" class="form-control">
          <option value="English">English</option>
          <option value="Swahili">Swahili</option>
        </select>
      </div>
    </div>
    <div class="bot-composer">
      <textarea id="botMessage" placeholder="Ask Edu AI to analyse, draft, translate, or generate content for the school..."></textarea>
      <button type="button" id="botSendBtn"><i class="bi bi-send me-2"></i>Send</button>
    </div>
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
<script>
(function () {
  const historyEl = document.getElementById('botHistory');
  const typingEl = document.getElementById('botTyping');
  const messageEl = document.getElementById('botMessage');
  const sendBtn = document.getElementById('botSendBtn');
  const toolEl = document.getElementById('botTool');
  const languageEl = document.getElementById('botLanguage');
  const quickButtons = document.querySelectorAll('[data-bot-prompt]');

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function scrollBottom() {
    historyEl.scrollTop = historyEl.scrollHeight;
  }

  function renderMessage(role, text, timeLabel) {
    const bubble = document.createElement('div');
    bubble.className = 'bot-msg ' + (role === 'user' ? 'user' : 'bot');
    bubble.innerHTML = escapeHtml(text) + (timeLabel ? '<span class="meta">' + escapeHtml(timeLabel) + '</span>' : '');
    historyEl.appendChild(bubble);
    scrollBottom();
  }

  function setTyping(active) {
    typingEl.classList.toggle('d-none', !active);
  }

  function normalizeHistory(items) {
    historyEl.innerHTML = '';
    (items || []).forEach(function (item) {
      const role = item.role === 'edu' ? 'bot' : 'user';
      renderMessage(role, item.text || '', item.created_at || '');
    });
    if (!historyEl.children.length) {
      renderMessage('bot', 'Hello, I am Edu AI. Use the tool selector for report comments, exam questions, lesson plans, attendance analysis, fee reminders, translation, discipline letters, and more.', 'ready');
    }
  }

  function loadHistory() {
    fetch('core/ai_feedback.php?action=history', { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        normalizeHistory(data && data.history ? data.history : []);
      })
      .catch(function () {
        if (!historyEl.children.length) {
          renderMessage('bot', 'Edu Bot memory is not available right now.', 'offline');
        }
      });
  }

  function sendMessage(message) {
    const text = String(message || messageEl.value || '').trim();
    if (!text) {
      return;
    }

    renderMessage('user', text, 'you');
    messageEl.value = '';
    setTyping(true);
    sendBtn.disabled = true;

    fetch('core/ai_feedback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      credentials: 'same-origin',
      body: new URLSearchParams({ action: 'chat', category: 'ai', message: text, tool: toolEl.value, language: languageEl.value }).toString()
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        setTyping(false);
        sendBtn.disabled = false;
        if (data && data.ok && data.response) {
          renderMessage('bot', data.response, 'Edu Bot');
          return;
        }
        renderMessage('bot', (data && data.message) ? data.message : 'Unable to generate a response right now.', 'error');
      })
      .catch(function () {
        setTyping(false);
        sendBtn.disabled = false;
        renderMessage('bot', 'Request failed. Please try again.', 'error');
      });
  }

  sendBtn.addEventListener('click', function () { sendMessage(); });
  messageEl.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      sendMessage();
    }
  });

  quickButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      if (button.getAttribute('data-bot-tool')) {
        toolEl.value = button.getAttribute('data-bot-tool');
      }
      sendMessage(button.getAttribute('data-bot-prompt'));
    });
  });

  loadHistory();
  setInterval(loadHistory, 10000);
})();
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
