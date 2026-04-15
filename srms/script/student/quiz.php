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
$quizDurationMinutes = 0;

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
  $quizDurationMinutes = isset($quiz['duration_minutes']) ? max(0, (int)$quiz['duration_minutes']) : 0;

  if (!empty($quiz['randomize_questions']) && count($questions) > 1) {
    shuffle($questions);
  }
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
<style>
.quiz-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 240px;
  gap: 1rem;
}

.quiz-palette {
  position: sticky;
  top: 88px;
}

.quiz-palette-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.4rem;
}

.quiz-palette-btn {
  border: 1px solid #cfd8d4;
  border-radius: 8px;
  background: #fff;
  font-weight: 700;
  color: #3a4b43;
  padding: 0.35rem 0;
}

.quiz-palette-btn.is-answered {
  background: #198754;
  border-color: #198754;
  color: #fff;
}

.quiz-palette-btn.is-current {
  box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.18);
  border-color: #0d6efd;
}

.quiz-timer {
  font-weight: 800;
}

.quiz-timer.is-danger {
  color: #b02a37;
}

@media (max-width: 991px) {
  .quiz-layout {
    grid-template-columns: 1fr;
  }

  .quiz-palette {
    position: static;
  }
}
</style>
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
<input type="hidden" name="auto_submit" id="quizAutoSubmit" value="0">
<div class="tile mb-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <strong id="quizProgressLabel">Question 1 of <?php echo (int)count($questions); ?></strong>
    <div class="d-flex align-items-center gap-2">
      <?php if ($quizDurationMinutes > 0): ?>
      <span class="badge bg-danger-subtle text-danger-emphasis quiz-timer" id="quizTimer">Time: --:--</span>
      <?php endif; ?>
      <span class="badge bg-info text-dark" id="quizQuestionTypeBadge">MCQ</span>
    </div>
  </div>
  <div class="progress mt-2" role="progressbar" aria-label="Quiz progress" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar" id="quizProgressBar" style="width: 0%;"></div>
  </div>
</div>
<div class="quiz-layout">
<div>
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
</div>
<aside class="tile quiz-palette">
  <h3 class="tile-title mb-3"><i class="bi bi-grid-3x3-gap me-2"></i>Question Palette</h3>
  <div class="quiz-palette-grid" id="quizPalette" aria-label="Question palette"></div>
  <div class="small text-muted mt-3">Green: answered. Blue ring: current question.</div>
</aside>
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
  var timerEl = document.getElementById('quizTimer');
  var paletteEl = document.getElementById('quizPalette');
  var autoSubmitEl = document.getElementById('quizAutoSubmit');
  var total = steps.length;
  var current = 0;
  var hasSubmitted = false;
  var storageKey = 'quiz_answers_<?php echo (int)$quizId; ?>';
  var durationSeconds = <?php echo (int)$quizDurationMinutes; ?> * 60;
  var timerStorageKey = storageKey + ':timer';
  var timerInterval = null;

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

  function answeredMap() {
    var map = {};
    var all = readAnswers();
    Object.keys(all).forEach(function (id) {
      if (String(all[id] || '').trim() !== '') {
        map[id] = true;
      }
    });
    return map;
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

  function renderPalette() {
    if (!paletteEl) {
      return;
    }

    var answerState = answeredMap();
    paletteEl.innerHTML = '';
    steps.forEach(function (step, idx) {
      var questionInput = step.querySelector('.quiz-answer');
      var qidMatch = questionInput && String(questionInput.name || '').match(/answers\[(\d+)\]/);
      var qid = qidMatch ? qidMatch[1] : String(idx + 1);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'quiz-palette-btn';
      if (answerState[qid]) {
        btn.classList.add('is-answered');
      }
      if (idx === current) {
        btn.classList.add('is-current');
      }
      btn.textContent = String(idx + 1);
      btn.addEventListener('click', function () {
        persistAnswers();
        current = idx;
        render();
      });
      paletteEl.appendChild(btn);
    });
  }

  function formatDuration(totalSeconds) {
    var safe = totalSeconds < 0 ? 0 : totalSeconds;
    var m = Math.floor(safe / 60);
    var s = safe % 60;
    return m + ':' + (s < 10 ? '0' + s : s);
  }

  function readRemainingSeconds() {
    if (durationSeconds < 1) {
      return 0;
    }
    var remaining = durationSeconds;
    try {
      var saved = parseInt(localStorage.getItem(timerStorageKey) || '', 10);
      if (!isNaN(saved) && saved >= 0 && saved <= durationSeconds) {
        remaining = saved;
      }
    } catch (e) {
      remaining = durationSeconds;
    }
    return remaining;
  }

  function writeRemainingSeconds(remaining) {
    if (durationSeconds < 1) {
      return;
    }
    try {
      localStorage.setItem(timerStorageKey, String(remaining));
    } catch (e) {
      // Ignore storage errors.
    }
  }

  function clearRemainingSeconds() {
    try {
      localStorage.removeItem(timerStorageKey);
    } catch (e) {
      // Ignore storage errors.
    }
  }

  function submitDueToTimeout() {
    if (hasSubmitted) {
      return;
    }
    hasSubmitted = true;
    if (autoSubmitEl) {
      autoSubmitEl.value = '1';
    }
    form.submit();
  }

  function initTimer() {
    if (!timerEl || durationSeconds < 1) {
      return;
    }

    var remaining = readRemainingSeconds();
    timerEl.textContent = 'Time: ' + formatDuration(remaining);
    timerEl.classList.toggle('is-danger', remaining <= 60);

    timerInterval = window.setInterval(function () {
      remaining -= 1;
      if (remaining < 0) {
        remaining = 0;
      }
      writeRemainingSeconds(remaining);
      timerEl.textContent = 'Time: ' + formatDuration(remaining);
      timerEl.classList.toggle('is-danger', remaining <= 60);

      if (remaining <= 0) {
        window.clearInterval(timerInterval);
        submitDueToTimeout();
      }
    }, 1000);
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
    renderPalette();
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
    el.addEventListener('change', function () {
      persistAnswers();
      renderPalette();
    });
    el.addEventListener('input', function () {
      persistAnswers();
      renderPalette();
    });
  });

  form.addEventListener('submit', function () {
    if (hasSubmitted) {
      return;
    }
    hasSubmitted = true;
    if (timerInterval) {
      window.clearInterval(timerInterval);
    }
    try {
      localStorage.removeItem(storageKey);
    } catch (e) {
      // Ignore.
    }
    clearRemainingSeconds();
  });

  restoreAnswers();
  render();
  initTimer();
})();
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
