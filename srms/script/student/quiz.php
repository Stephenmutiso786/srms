<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}

$quizId = (int)($_GET['id'] ?? 0);
$quiz = null;
$questions = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT q.*, c.class_id
		FROM tbl_quizzes q
		JOIN tbl_courses c ON c.id = q.course_id
		WHERE q.id = ? LIMIT 1");
	$stmt->execute([$quizId]);
	$quiz = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$quiz) {
		throw new RuntimeException("Quiz not found.");
	}

	$stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ? LIMIT 1");
	$stmt->execute([$account_id]);
	if ((int)$stmt->fetchColumn() !== (int)$quiz['class_id']) {
		throw new RuntimeException("Not allowed to take this quiz.");
	}

	$stmt = $conn->prepare("SELECT * FROM tbl_quiz_questions WHERE quiz_id = ? ORDER BY id");
	$stmt->execute([$quizId]);
	$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Quiz</title>
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
<li><a class="dropdown-item" href="student/elearning"><i class="bi bi-arrow-left me-2 fs-5"></i> Back</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<main class="app-content">
<div class="app-title">
<div>
<h1><?php echo htmlspecialchars($quiz['title'] ?? 'Quiz'); ?></h1>
</div>
</div>

<?php if ($error !== '') { ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<?php if (count($questions) < 1): ?>
<div class="tile"><div class="alert alert-warning mb-0">No questions available for this quiz yet.</div></div>
<?php else: ?>
<form class="app_frm" method="POST" action="student/core/submit_quiz" id="quizForm">
<input type="hidden" name="quiz_id" value="<?php echo (int)$quizId; ?>">
<div class="tile mb-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <strong id="quizProgressLabel">Question 1 of <?php echo (int)count($questions); ?></strong>
    <span class="badge bg-info text-dark" id="quizQuestionTypeBadge">MCQ</span>
  </div>
  <div class="progress mt-2" role="progressbar" aria-label="Quiz progress" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar" id="quizProgressBar" style="width: 0%;"></div>
  </div>
</div>
<?php foreach ($questions as $index => $q): ?>
  <?php
    $qType = strtolower(trim((string)($q['qtype'] ?? 'mcq')));
    $opts = array_values(array_filter(array_map('trim', explode(',', $q['options'] ?? '')), function ($v) {
      return $v !== '';
    }));
  ?>
  <div class="tile quiz-step" data-step="<?php echo (int)$index; ?>" data-type="<?php echo htmlspecialchars($qType); ?>" style="display:none;">
    <h3 class="tile-title"><?php echo htmlspecialchars($q['question']); ?></h3>

    <?php if (($qType === 'mcq' || $qType === 'true_false') && count($opts) > 0): ?>
      <?php foreach ($opts as $opt): ?>
        <div class="form-check mb-2">
          <input class="form-check-input quiz-answer" type="radio" name="answers[<?php echo (int)$q['id']; ?>]" value="<?php echo htmlspecialchars($opt); ?>" id="q<?php echo (int)$q['id']; ?>_<?php echo md5($opt); ?>">
          <label class="form-check-label" for="q<?php echo (int)$q['id']; ?>_<?php echo md5($opt); ?>"><?php echo htmlspecialchars($opt); ?></label>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <input class="form-control quiz-answer" name="answers[<?php echo (int)$q['id']; ?>]" placeholder="Type your answer here">
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="tile">
  <div class="d-flex justify-content-between flex-wrap gap-2">
    <button class="btn btn-outline-secondary" type="button" id="quizPrevBtn">Previous</button>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-primary" type="button" id="quizNextBtn">Next</button>
      <button class="btn btn-primary" type="submit" id="quizSubmitBtn" style="display:none;">Submit Quiz</button>
    </div>
  </div>
</div>
</form>
<?php endif; ?>
<?php } ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
  var form = document.getElementById('quizForm');
  if (!form) {
    return;
  }

  var steps = Array.prototype.slice.call(document.querySelectorAll('.quiz-step'));
  var prevBtn = document.getElementById('quizPrevBtn');
  var nextBtn = document.getElementById('quizNextBtn');
  var submitBtn = document.getElementById('quizSubmitBtn');
  var progressLabel = document.getElementById('quizProgressLabel');
  var progressBar = document.getElementById('quizProgressBar');
  var typeBadge = document.getElementById('quizQuestionTypeBadge');
  var total = steps.length;
  var current = 0;
  var storageKey = 'quiz_answers_<?php echo (int)$quizId; ?>';

  function friendlyType(type) {
    if (type === 'true_false') return 'True/False';
    if (type === 'fill_blank') return 'Fill Blank';
    if (type === 'short_answer') return 'Short Answer';
    return 'MCQ';
  }

  function readAnswers() {
    var all = {};
    var fields = form.querySelectorAll('.quiz-answer');
    fields.forEach(function (el) {
      var name = String(el.name || '');
      var match = name.match(/answers\[(\d+)\]/);
      if (!match) {
        return;
      }
      var id = match[1];
      if (el.type === 'radio') {
        if (el.checked) {
          all[id] = String(el.value || '');
        }
      } else {
        all[id] = String(el.value || '').trim();
      }
    });
    return all;
  }

  function persistAnswers() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(readAnswers()));
    } catch (e) {
      // Ignore storage errors in private/restricted mode.
    }
  }

  function restoreAnswers() {
    var raw = null;
    try {
      raw = localStorage.getItem(storageKey);
    } catch (e) {
      raw = null;
    }
    if (!raw) {
      return;
    }
    var saved = {};
    try {
      saved = JSON.parse(raw) || {};
    } catch (e) {
      saved = {};
    }

    Object.keys(saved).forEach(function (id) {
      var val = String(saved[id] || '');
      var radios = form.querySelectorAll('input[type="radio"][name="answers[' + id + ']"]');
      var checked = false;
      radios.forEach(function (radio) {
        if (String(radio.value || '') === val) {
          radio.checked = true;
          checked = true;
        }
      });
      if (checked) {
        return;
      }
      var text = form.querySelector('input[name="answers[' + id + ']"]');
      if (text) {
        text.value = val;
      }
    });
  }

  function render() {
    steps.forEach(function (step, idx) {
      step.style.display = (idx === current) ? '' : 'none';
    });
    if (progressLabel) {
      progressLabel.textContent = 'Question ' + (current + 1) + ' of ' + total;
    }
    if (progressBar) {
      var pct = Math.round(((current + 1) / total) * 100);
      progressBar.style.width = pct + '%';
      progressBar.setAttribute('aria-valuenow', String(pct));
    }
    if (typeBadge && steps[current]) {
      typeBadge.textContent = friendlyType(String(steps[current].getAttribute('data-type') || 'mcq'));
    }
    prevBtn.disabled = current <= 0;
    nextBtn.style.display = current >= total - 1 ? 'none' : '';
    submitBtn.style.display = current >= total - 1 ? '' : 'none';
  }

  prevBtn.addEventListener('click', function () {
    persistAnswers();
    if (current > 0) {
      current -= 1;
      render();
    }
  });

  nextBtn.addEventListener('click', function () {
    persistAnswers();
    if (current < total - 1) {
      current += 1;
      render();
    }
  });

  form.querySelectorAll('.quiz-answer').forEach(function (el) {
    el.addEventListener('change', persistAnswers);
    el.addEventListener('input', persistAnswers);
  });

  form.addEventListener('submit', function () {
    try {
      localStorage.removeItem(storageKey);
    } catch (e) {
      // Ignore.
    }
  });

  restoreAnswers();
  render();
})();
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
