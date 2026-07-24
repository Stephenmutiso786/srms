<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/school.php');

if ($res !== '1' || $level !== '2') { header('location:../'); exit; }
app_require_any_permission(['marks.enter', 'academic.manage']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - AI Assignment Generator</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a><ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul></header>
<?php include('teacher/partials/sidebar.php'); ?>
<main class="app-content">
	<div class="app-title">
		<div>
			<h1>AI Assignment Generator</h1>
			<p>Create assignments, homework tasks, competency questions, and marking guides using Edu AI.</p>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-5">
			<div class="tile">
				<h3 class="tile-title">Assignment Brief</h3>
				<div class="mb-3">
					<label class="form-label">Class</label>
					<input class="form-control" id="asgClass" placeholder="Grade 6">
				</div>
				<div class="mb-3">
					<label class="form-label">Subject</label>
					<input class="form-control" id="asgSubject" placeholder="Mathematics">
				</div>
				<div class="mb-3">
					<label class="form-label">Topic</label>
					<input class="form-control" id="asgTopic" placeholder="Fractions">
				</div>
				<div class="mb-3">
					<label class="form-label">Instructions</label>
					<textarea class="form-control" id="asgPrompt" rows="8" placeholder="Create a CBC-aligned assignment with a mix of short answer and competency-based questions. Include a simple marking guide."></textarea>
				</div>
				<div class="d-flex gap-2 flex-wrap">
					<button type="button" class="btn btn-primary" id="asgGenerateBtn"><i class="bi bi-stars me-2"></i>Generate Assignment</button>
					<button type="button" class="btn btn-outline-secondary" id="asgTemplateBtn">Use sample brief</button>
				</div>
			</div>
		</div>
		<div class="col-lg-7">
			<div class="tile">
				<div class="d-flex align-items-center justify-content-between gap-2">
					<h3 class="tile-title mb-0">Assignment Output</h3>
					<div class="d-flex gap-2 flex-wrap">
						<button type="button" class="btn btn-outline-primary btn-sm" id="asgCopyBtn">Copy</button>
						<button type="button" class="btn btn-outline-secondary btn-sm" id="asgPrintBtn">Print / PDF</button>
					</div>
				</div>
				<div class="small text-muted mb-2">Edu AI can generate classwork, homework, structured questions, and simple marking schemes.</div>
				<div class="small text-muted mb-3" id="asgStatus">Generate an assignment, then print it or save it as PDF from the print dialog.</div>
				<pre id="asgOutput" style="min-height:420px;white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:16px;">Your assignment will appear here.</pre>
			</div>
		</div>
	</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
	var classBox = document.getElementById('asgClass');
	var subjectBox = document.getElementById('asgSubject');
	var topicBox = document.getElementById('asgTopic');
	var promptBox = document.getElementById('asgPrompt');
	var output = document.getElementById('asgOutput');
	var statusBox = document.getElementById('asgStatus');

	function buildPrompt() {
		return 'Generate a complete school assignment paper for ' + (classBox.value || 'the selected class') + ' in ' + (subjectBox.value || 'the selected subject') + ' on ' + (topicBox.value || 'the selected topic') + '. Include a title, clear learner instructions, numbered questions, competency/application tasks, and a marking guide. ' + (promptBox.value || 'Include questions and a marking guide.');
	}

	function escapeHtml(text) {
		return String(text || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function printOutput() {
		var content = String(output.textContent || '').trim();
		if (!content || content === 'Your assignment will appear here.' || content === 'Generating assignment...') {
			statusBox.textContent = 'Generate an assignment first before printing or saving as PDF.';
			return;
		}

		var popup = window.open('', '_blank', 'width=900,height=700');
		if (!popup) {
			statusBox.textContent = 'Allow pop-ups to print or save this assignment as PDF.';
			return;
		}

		popup.document.open();
		popup.document.write('<!DOCTYPE html><html><head><title>Assignment Print</title><style>body{font-family:Arial,sans-serif;padding:32px;line-height:1.6;color:#111827;}h1{font-size:24px;margin:0 0 16px;}pre{white-space:pre-wrap;font-family:Arial,sans-serif;font-size:14px;border:1px solid #d1d5db;border-radius:12px;padding:20px;background:#fff;}@media print{body{padding:0;}pre{border:none;padding:0;}}</style></head><body><h1>Generated Assignment</h1><pre>' + escapeHtml(content) + '</pre><script>window.onload=function(){window.print();};<\/script></body></html>');
		popup.document.close();
	}

	document.getElementById('asgGenerateBtn').addEventListener('click', function () {
		output.textContent = 'Generating assignment...';
		statusBox.textContent = 'Generating assignment...';
		fetch('core/ai_feedback.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				category: 'ai',
				tool: 'assignment_generator',
				language: 'English',
				message: buildPrompt(),
				module: 'assignment_generator',
				page: 'assignment_generator',
				title: 'AI Assignment Generator'
			})
		}).then(function (r) {
			return r.ok ? r.json() : null;
		}).then(function (data) {
			if (data && data.ok) {
				output.textContent = String(data.response || '');
				statusBox.textContent = 'Assignment generated. You can now copy it, print it, or save it as PDF.';
				return;
			}
			output.textContent = 'Assignment generation failed right now.';
			statusBox.textContent = 'Assignment generation failed. Please try again.';
		}).catch(function () {
			output.textContent = 'Assignment generation failed right now.';
			statusBox.textContent = 'Assignment generation failed. Please try again.';
		});
	});

	document.getElementById('asgTemplateBtn').addEventListener('click', function () {
		classBox.value = 'Grade 6';
		subjectBox.value = 'Mathematics';
		topicBox.value = 'Fractions';
		promptBox.value = 'Create a CBC-aligned homework assignment with 10 questions, 2 applied competency tasks, and a short marking guide.';
	});

	document.getElementById('asgCopyBtn').addEventListener('click', function () {
		navigator.clipboard.writeText(output.textContent || '');
	});

	document.getElementById('asgPrintBtn').addEventListener('click', printOutput);
})();
</script>
</body>
</html>
