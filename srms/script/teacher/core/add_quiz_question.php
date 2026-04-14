<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:../elearning");
  exit;
}

$quizId = (int)($_POST['quiz_id'] ?? 0);
$entryMode = strtolower(trim((string)($_POST['entry_mode'] ?? 'single')));
$question = trim($_POST['question'] ?? '');
$qtypeRaw = strtolower(trim((string)($_POST['qtype'] ?? 'mcq')));
$options = trim($_POST['options'] ?? '');
$correct = trim($_POST['correct_answer'] ?? '');
$marks = (float)($_POST['marks'] ?? 1);
$bulkRaw = trim((string)($_POST['bulk_questions'] ?? ''));
$bulkQuestions = isset($_POST['bulk_question']) && is_array($_POST['bulk_question']) ? $_POST['bulk_question'] : [];
$bulkTypes = isset($_POST['bulk_qtype']) && is_array($_POST['bulk_qtype']) ? $_POST['bulk_qtype'] : [];
$bulkOptions = isset($_POST['bulk_options']) && is_array($_POST['bulk_options']) ? $_POST['bulk_options'] : [];
$bulkCorrect = isset($_POST['bulk_correct_answer']) && is_array($_POST['bulk_correct_answer']) ? $_POST['bulk_correct_answer'] : [];
$bulkMarks = isset($_POST['bulk_marks']) && is_array($_POST['bulk_marks']) ? $_POST['bulk_marks'] : [];

$allowedTypes = ['mcq', 'true_false', 'fill_blank', 'short_answer'];
if ($marks <= 0) {
  $marks = 1;
}

function normalize_question_payload($question, $qtypeRaw, $options, $correct, $marks, $allowedTypes) {
  $qtype = in_array($qtypeRaw, $allowedTypes, true) ? $qtypeRaw : 'mcq';
  if ($marks <= 0) {
    $marks = 1;
  }

  if ($question === '') {
    throw new InvalidArgumentException('Question is required.');
  }

  if (in_array($qtype, ['mcq', 'true_false', 'fill_blank'], true) && $correct === '') {
    throw new InvalidArgumentException('Correct answer is required for this question type.');
  }

  if ($qtype === 'true_false') {
    $options = 'True,False';
    $normalized = strtolower($correct);
    if ($normalized === 'true' || $normalized === 't') {
      $correct = 'True';
    } elseif ($normalized === 'false' || $normalized === 'f') {
      $correct = 'False';
    } else {
      throw new InvalidArgumentException('Correct answer for True/False must be True or False.');
    }
  }

  if ($qtype === 'mcq') {
    $opts = array_values(array_filter(array_map('trim', explode(',', $options)), function ($v) {
      return $v !== '';
    }));
    if (count($opts) < 2) {
      throw new InvalidArgumentException('MCQ requires at least two options.');
    }
    $options = implode(',', $opts);
  }

  if ($qtype !== 'mcq' && $qtype !== 'true_false') {
    $options = '';
  }

  return [
    'question' => $question,
    'qtype' => $qtype,
    'options' => $options,
    'correct_answer' => $correct,
    'marks' => $marks
  ];
}

if ($quizId < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing question details."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $stmt = $conn->prepare("SELECT c.teacher_id FROM tbl_quizzes q JOIN tbl_courses c ON c.id = q.course_id WHERE q.id = ? LIMIT 1");
  $stmt->execute([$quizId]);
  if ((int)$stmt->fetchColumn() !== (int)$account_id) {
    throw new RuntimeException("Not allowed to edit this quiz.");
  }

  $toInsert = [];
  if ($entryMode === 'bulk') {
    if (!empty($bulkQuestions)) {
      foreach ($bulkQuestions as $i => $rawQuestion) {
        $lineQuestion = trim((string)$rawQuestion);
        if ($lineQuestion === '') {
          continue;
        }
        $lineType = strtolower(trim((string)($bulkTypes[$i] ?? 'mcq')));
        $lineOptions = trim((string)($bulkOptions[$i] ?? ''));
        $lineCorrect = trim((string)($bulkCorrect[$i] ?? ''));
        $lineMarks = (float)($bulkMarks[$i] ?? 1);

        try {
          $toInsert[] = normalize_question_payload($lineQuestion, $lineType, $lineOptions, $lineCorrect, $lineMarks, $allowedTypes);
        } catch (InvalidArgumentException $e) {
          throw new InvalidArgumentException('Row '.($i + 1).': '.$e->getMessage());
        }
      }
    }

    if (empty($toInsert) && $bulkRaw !== '') {
      $lines = preg_split('/\r\n|\r|\n/', $bulkRaw);
      foreach ($lines as $i => $line) {
        $line = trim((string)$line);
        if ($line === '') {
          continue;
        }
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 2) {
          throw new InvalidArgumentException('Invalid bulk format at line '.($i + 1).'. Use: Question | qtype | options | correct_answer | marks');
        }

        $lineQuestion = (string)($parts[0] ?? '');
        $lineType = strtolower((string)($parts[1] ?? 'mcq'));
        $lineOptions = (string)($parts[2] ?? '');
        $lineCorrect = (string)($parts[3] ?? '');
        $lineMarks = (float)($parts[4] ?? 1);

        try {
          $toInsert[] = normalize_question_payload($lineQuestion, $lineType, $lineOptions, $lineCorrect, $lineMarks, $allowedTypes);
        } catch (InvalidArgumentException $e) {
          throw new InvalidArgumentException('Line '.($i + 1).': '.$e->getMessage());
        }
      }
    }

    if (empty($toInsert)) {
      throw new InvalidArgumentException('Bulk mode has no valid question rows.');
    }
  } else {
    $toInsert[] = normalize_question_payload($question, $qtypeRaw, $options, $correct, $marks, $allowedTypes);
  }

  $conn->beginTransaction();
  $stmt = $conn->prepare("INSERT INTO tbl_quiz_questions (quiz_id, question, qtype, options, correct_answer, marks) VALUES (?,?,?,?,?,?)");
  foreach ($toInsert as $item) {
    $stmt->execute([$quizId, $item['question'], $item['qtype'], $item['options'], $item['correct_answer'], $item['marks']]);
  }
  $conn->commit();

  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.quiz.question', 'quiz', (string)$quizId);
  $countAdded = count($toInsert);
  $_SESSION['reply'] = array (array("success", $countAdded > 1 ? ($countAdded." questions added.") : "Question added."));
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", $e instanceof InvalidArgumentException ? $e->getMessage() : "Operation failed. Please try again."));
}
header("location:../elearning");
exit;
