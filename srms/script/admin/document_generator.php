<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/school.php');

if ($res !== '1' || $level !== '0') { header('location:../'); exit; }
app_require_any_permission(['academic.manage', 'report.generate', 'communication.manage']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - AI Document Generator</title>
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
			<h1>AI Document Generator</h1>
			<p>Generate official school letters, report comments, reminders, plans, and translations from one workspace.</p>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-5">
			<div class="tile">
				<h3 class="tile-title">Generator Form</h3>
				<div class="mb-3">
					<label class="form-label">Document Type</label>
					<select class="form-control" id="docTool">
						<option value="discipline_letter">Discipline Letter</option>
						<option value="fee_reminder">Fee Reminder</option>
						<option value="report_comments">Report Comments</option>
						<option value="lesson_plan">Lesson Plan</option>
						<option value="translation">Translation</option>
						<option value="general">Official Letter</option>
					</select>
				</div>
				<div class="mb-3">
					<label class="form-label">Language</label>
					<select class="form-control" id="docLanguage">
						<option value="English">English</option>
						<option value="Swahili">Swahili</option>
					</select>
				</div>
				<div class="mb-3">
					<label class="form-label">Context / Instructions</label>
					<textarea class="form-control" id="docPrompt" rows="10" placeholder="Example: Draft an official warning letter to a parent for a Grade 7 learner involved in repeated bullying incidents. Include the incident summary, action taken, and parent meeting request."></textarea>
				</div>
				<div class="d-flex gap-2 flex-wrap">
					<button type="button" class="btn btn-primary" id="docGenerateBtn"><i class="bi bi-stars me-2"></i>Generate</button>
					<button type="button" class="btn btn-outline-secondary" id="docUseOfficialTemplateBtn">Official letter template</button>
					<button type="button" class="btn btn-outline-secondary" id="docUseFeeTemplateBtn">Fee reminder template</button>
				</div>
			</div>
		</div>
		<div class="col-lg-7">
			<div class="tile">
				<div class="d-flex align-items-center justify-content-between gap-2">
					<h3 class="tile-title mb-0">Generated Output</h3>
					<button type="button" class="btn btn-outline-primary btn-sm" id="docCopyBtn">Copy</button>
				</div>
				<div class="small text-muted mb-3">Edu AI drafts the document based on your selected type and school context.</div>
				<pre id="docOutput" style="min-height:420px;white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:16px;">Your generated document will appear here.</pre>
			</div>
		</div>
	</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
	var promptBox = document.getElementById('docPrompt');
	var toolBox = document.getElementById('docTool');
	var languageBox = document.getElementById('docLanguage');
	var output = document.getElementById('docOutput');

	function generate() {
		var message = String(promptBox.value || '').trim();
		if (!message) {
			output.textContent = 'Enter document instructions first.';
			return;
		}
		output.textContent = 'Generating...';
		fetch('core/ai_feedback.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				category: 'ai',
				tool: toolBox.value || 'general',
				language: languageBox.value || 'English',
				message: message,
				module: 'document_generator',
				page: 'document_generator',
				title: 'AI Document Generator'
			})
		}).then(function (r) {
			return r.ok ? r.json() : null;
		}).then(function (data) {
			output.textContent = data && data.ok ? String(data.response || '') : 'Document generation failed right now.';
		}).catch(function () {
			output.textContent = 'Document generation failed right now.';
		});
	}

	document.getElementById('docGenerateBtn').addEventListener('click', generate);
	document.getElementById('docUseOfficialTemplateBtn').addEventListener('click', function () {
		toolBox.value = 'general';
		promptBox.value = 'Draft an official school letter with a formal tone. Include subject line, greeting, body, clear action needed, and signature placeholder.';
	});
	document.getElementById('docUseFeeTemplateBtn').addEventListener('click', function () {
		toolBox.value = 'fee_reminder';
		promptBox.value = 'Draft a polite but firm fee reminder to a parent with overdue balance. Mention learner class, encouragement to clear balance, and contact the accounts office.';
	});
	document.getElementById('docCopyBtn').addEventListener('click', function () {
		navigator.clipboard.writeText(output.textContent || '');
	});
})();
</script>
</body>
</html>
