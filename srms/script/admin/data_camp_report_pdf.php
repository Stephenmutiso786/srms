<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('tcpdf/tcpdf.php');

if ($res !== '1' || !in_array((int)$level, [0, 1, 9], true)) {
  header('location:../');
  exit;
}

$archiveId = (int)($_GET['id'] ?? 0);
if ($archiveId < 1) {
  header('location:data_camp');
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  app_ensure_data_camp_schema($conn);

  $stmt = $conn->prepare("SELECT * FROM tbl_data_camp_records WHERE id = ? AND record_type = 'report_card' LIMIT 1");
  $stmt->execute([$archiveId]);
  $archive = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$archive) {
    $_SESSION['reply'] = array(array('warning', 'Archived report record not found.'));
    header('location:data_camp');
    exit;
  }

  $payload = app_data_camp_payload_array($archive);

  $studentId = trim((string)($payload['student_id'] ?? $archive['student_id'] ?? ''));
  $classId = (int)($payload['class_id'] ?? $archive['class_id'] ?? 0);
  $termId = (int)($payload['term_id'] ?? 0);
  $examId = (int)($payload['exam_id'] ?? 0);
  $reportId = (int)($payload['report_id'] ?? $archive['entity_id'] ?? 0);

  if ($studentId === '' || $classId < 1 || $termId < 1) {
    $_SESSION['reply'] = array(array('warning', 'This archived report is missing key data needed to rebuild the PDF.'));
    header('location:data_camp');
    exit;
  }

  $student = report_get_student_identity($conn, $studentId);
  if (!$student) {
    $student = [
      'id' => $studentId,
      'name' => trim((string)($archive['title'] ?? 'Archived Student Report')),
      'school_id' => $studentId,
      'class_id' => $classId,
      'class_name' => (string)($payload['class_name'] ?? ''),
    ];
  }

  if ((int)($student['class_id'] ?? 0) < 1) {
    $student['class_id'] = $classId;
  }
  if (trim((string)($student['class_name'] ?? '')) === '' && $classId > 0) {
    $stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
    $stmt->execute([$classId]);
    $student['class_name'] = (string)($stmt->fetchColumn() ?: (string)($payload['class_name'] ?? ''));
  }

  $termName = trim((string)($payload['term_name'] ?? ''));
  if ($termName === '') {
    $stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
    $stmt->execute([$termId]);
    $termName = (string)$stmt->fetchColumn();
  }

  $liveCard = $reportId > 0 ? report_load_card($conn, $reportId) : null;
  $card = is_array($liveCard) ? $liveCard : [
    'id' => $reportId,
    'student_id' => $studentId,
    'class_id' => $classId,
    'term_id' => $termId,
    'exam_id' => $examId,
    'verification_code' => (string)($payload['verification_code'] ?? ''),
    'grade' => (string)($payload['grade'] ?? 'N/A'),
    'total' => (float)($payload['total'] ?? 0),
    'mean_points' => (float)($payload['mean'] ?? 0),
    'mean' => (float)($payload['mean'] ?? 0),
    'remark' => (string)($payload['remark'] ?? ''),
    'trend' => (string)($payload['trend'] ?? ''),
    'position' => (int)($payload['position'] ?? 0),
    'total_students' => (int)($payload['total_students'] ?? 0),
    'assessment_mode' => 'normal',
    'subjects' => array_values(array_filter(array_map(static function ($subject): array {
      return [
        'subject_id' => (int)($subject['subject_id'] ?? 0),
        'subject_name' => (string)($subject['subject_name'] ?? ''),
        'score' => array_key_exists('score', $subject) && $subject['score'] !== null ? (float)$subject['score'] : null,
        'grade' => (string)($subject['grade'] ?? ''),
        'grade_points' => isset($subject['grade_points']) && $subject['grade_points'] !== null ? (float)$subject['grade_points'] : null,
        'points' => isset($subject['points']) && $subject['points'] !== null ? (float)$subject['points'] : null,
        'teacher_name' => (string)($subject['teacher_name'] ?? ''),
        'weight' => isset($subject['weight']) && $subject['weight'] !== null ? (float)$subject['weight'] : null,
        'rank' => (string)($subject['rank'] ?? ''),
        'position' => (string)($subject['position'] ?? ''),
        'class_mean' => isset($subject['class_mean']) && $subject['class_mean'] !== null ? (float)$subject['class_mean'] : null,
        'deviation' => isset($subject['deviation']) && $subject['deviation'] !== null ? (float)$subject['deviation'] : null,
        'remark' => (string)($subject['remark'] ?? ''),
        'ai_comment' => (string)($subject['ai_comment'] ?? ''),
      ];
    }, (array)($payload['subjects'] ?? [])), static function (array $subject): bool {
      return trim((string)($subject['subject_name'] ?? '')) !== '';
    })),
  ];

  $attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
  $feesBalance = report_fees_balance($conn, $studentId, $termId);

  $examSummary = null;
  $examBreakdown = [];
  if ($examId > 0) {
    $examSummary = report_exam_summary($conn, $studentId, $classId, $termId, $examId);
    $examBreakdown = report_exam_subject_breakdown($conn, $studentId, $classId, $termId, $examId);
  }

  if (!$examSummary) {
    $examSummary = [
      'exam_name' => (string)($payload['exam_name'] ?? 'Archived Results'),
      'grade' => (string)($payload['grade'] ?? ($card['grade'] ?? 'N/A')),
      'mean_points' => (float)($payload['mean'] ?? ($card['mean_points'] ?? $card['mean'] ?? 0)),
      'assessment_mode' => 'normal',
    ];
  }

  if (empty($examBreakdown) && !empty($card['subjects']) && is_array($card['subjects'])) {
    $examBreakdown = $card['subjects'];
  }

  if (empty($card['subjects']) && !empty($examBreakdown) && is_array($examBreakdown)) {
    $card['subjects'] = $examBreakdown;
  }

  if (empty($examBreakdown) && empty($card['subjects'])) {
    $archiveNotice = 'Detailed subject scores were not retained in this older archive record. Summary information is still preserved.';
    $card['subjects'] = [[
      'subject_id' => 0,
      'subject_name' => 'Archive note',
      'score' => null,
      'grade' => 'N/A',
      'grade_points' => null,
      'points' => null,
      'teacher_name' => '',
      'weight' => null,
      'rank' => '',
      'position' => '',
      'class_mean' => null,
      'deviation' => null,
      'remark' => $archiveNotice,
      'ai_comment' => $archiveNotice,
    ]];
    $examBreakdown = $card['subjects'];
  }

  $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
  app_output_single_page_report_pdf($conn, $pdf, [
    'student_id' => $studentId,
    'student_name' => (string)($student['name'] ?? $studentId),
    'school_id' => ((string)($student['school_id'] ?? '') !== '' ? (string)$student['school_id'] : $studentId),
    'class_name' => (string)($student['class_name'] ?? (string)($payload['class_name'] ?? '')),
    'term_name' => $termName,
    'attendance' => $attendance,
    'fees_balance' => $feesBalance,
    'card' => $card,
    'exam_summary' => $examSummary,
    'exam_breakdown' => $examBreakdown,
  ]);

  $studentToken = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string)($student['name'] ?? 'student')));
  $classToken = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($student['class_name'] ?? 'class'));
  $termToken = preg_replace('/[^A-Za-z0-9_-]+/', '_', $termName !== '' ? $termName : 'term');
  $fileName = $studentToken . '_' . $classToken . '_' . $termToken . '_Archived_Report.pdf';
  $pdf->Output($fileName, 'I');
  exit;
} catch (Throwable $e) {
  error_log('[admin/data_camp_report_pdf] ' . $e->getMessage());
  $_SESSION['reply'] = array(array('danger', 'Failed to open the archived report PDF.'));
  header('location:data_camp');
  exit;
}
