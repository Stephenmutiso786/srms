<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');

if ($res !== '1' || !in_array((string)$level, ['0', '1', '2'], true)) { header('location:../'); exit; }

$classes = [];
$terms = [];
$selectedClass = (int)($_GET['class'] ?? 0);
$selectedTerm = (int)($_GET['term'] ?? 0);
$selectedExam = (int)($_GET['exam'] ?? 0);
$selectedType = (string)($_GET['type'] ?? 'all');
$portal = ((string)$level === '2') ? 'teacher' : 'admin';
$isTeacherPortal = $portal === 'teacher';
$pageTitle = $isTeacherPortal ? 'Teacher Downloads Hub' : 'Downloads Center';
$pageSubtitle = $isTeacherPortal
    ? 'Download class-based reports and print tools for your assigned classes'
    : 'Access all your reports, summaries, and exports in one place';
$backUrl = $isTeacherPortal ? 'teacher' : 'report';
$canReportView = false;
$canReportGenerate = false;
$canMarksEnter = false;
$canAttendanceManage = false;
$termExamMap = [];

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $canReportView = app_has_permission($conn, (string)$account_id, (string)$level, 'report.view');
    $canReportGenerate = app_has_permission($conn, (string)$account_id, (string)$level, 'report.generate');
    $canMarksEnter = app_has_permission($conn, (string)$account_id, (string)$level, 'marks.enter');
    $canAttendanceManage = app_has_permission($conn, (string)$account_id, (string)$level, 'attendance.manage');

    if (!$isTeacherPortal && !$canReportView && !$canReportGenerate) {
        header('location:../');
        exit;
    }
    if ($isTeacherPortal && !$canReportView && !$canMarksEnter && !$canAttendanceManage) {
        header('location:../');
        exit;
    }

    if ($isTeacherPortal) {
        $assignmentYear = (int)date('Y');
        if (app_table_exists($conn, 'tbl_teacher_assignments')) {
            $stmt = $conn->prepare("SELECT DISTINCT c.id, c.name
                FROM tbl_teacher_assignments ta
                JOIN tbl_classes c ON c.id = ta.class_id
                WHERE ta.teacher_id = ? AND ta.status = 1 AND ta.year = ?
                ORDER BY c.name");
            $stmt->execute([(int)$account_id, $assignmentYear]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $conn->prepare("SELECT DISTINCT t.id, t.name
                FROM tbl_teacher_assignments ta
                JOIN tbl_terms t ON t.id = ta.term_id
                WHERE ta.teacher_id = ? AND ta.status = 1 AND ta.year = ?
                ORDER BY t.id DESC");
            $stmt->execute([(int)$account_id, $assignmentYear]);
            $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
        $stmt->execute();
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
        $stmt->execute();
        $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (app_table_exists($conn, 'tbl_exams')) {
            $hasCreatedAt = app_column_exists($conn, 'tbl_exams', 'created_at');
            foreach ($classes as $classRow) {
                $classKey = (int)($classRow['id'] ?? 0);
                if ($classKey < 1) {
                    continue;
                }
                $termExamMap[$classKey] = [];
                foreach ($terms as $termRow) {
                    $termKey = (int)($termRow['id'] ?? 0);
                    if ($termKey < 1) {
                        continue;
                    }
                    $stmt = $conn->prepare("SELECT id, name, COALESCE(status, 'draft') AS status
                        FROM tbl_exams
                        WHERE class_id = ? AND term_id = ?
                        ORDER BY " . ($hasCreatedAt ? "created_at DESC, " : "") . "id DESC");
                    $stmt->execute([$classKey, $termKey]);
                    $termExamMap[$classKey][$termKey] = array_map(static function ($row): array {
                        return [
                            'id' => (int)($row['id'] ?? 0),
                            'name' => (string)($row['name'] ?? ''),
                            'status' => strtolower(trim((string)($row['status'] ?? 'draft'))),
                        ];
                    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
                }
            }
        }
    }

    if ($selectedClass < 1 && !empty($classes)) {
        $selectedClass = (int)$classes[0]['id'];
    }
    if ($selectedTerm < 1 && !empty($terms)) {
        $selectedTerm = (int)$terms[0]['id'];
    }
} catch (Throwable $e) {
    error_log('[admin/downloads_center] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Downloads Center</title>
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .download-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 5px solid #1a7ab8;
        }
        .download-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .download-card.account { border-left-color: #0d6efd; }
        .download-card.results { border-left-color: #198754; }
        .download-card.merit { border-left-color: #ffc107; }
        .download-card.financial { border-left-color: #dc3545; }
        .download-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        .badge-status {
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
        }
    </style>
</head>
<body class="app sidebar-mini">
    <header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
    <?php include($isTeacherPortal ? 'teacher/partials/sidebar.php' : 'admin/partials/sidebar.php'); ?>
    <main class="app-content">
    <div class="container-fluid mt-4 mb-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="mb-1"><i class="bi bi-download"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="text-muted"><?php echo htmlspecialchars($pageSubtitle); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form class="row g-2" method="GET">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($selectedType); ?>">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Select Class</label>
                    <select name="class" class="form-select" id="filterClass">
                        <option value="0">-- All Classes --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Select Term</label>
                    <select name="term" class="form-select" id="filterTerm">
                        <option value="0">-- All Terms --</option>
                        <?php foreach ($terms as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>" <?php echo $selectedTerm === (int)$t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Select Exam</label>
                    <select name="exam" class="form-select" id="filterExam">
                        <option value="0">-- All / Latest Published --</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                </div>
            </form>
        </div>

        <!-- Download Options Grid -->
        <div class="row g-4">
            
            <!-- Individual Report Cards -->
            <?php if (!$isTeacherPortal && ($canReportView || $canReportGenerate)): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card download-card account h-100">
                    <div class="card-body">
                        <div class="download-icon text-primary"><i class="bi bi-file-earmark-pdf"></i></div>
                        <h5 class="card-title">Individual Report Cards</h5>
                        <p class="card-text text-muted small">Download PDF report cards for individual students with full academic details, grades, and comments.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">Per Student</span>
                            <span class="badge bg-light text-dark badge-status">Full Details</span>
                        </div>
                        <a href="report<?php echo ($selectedClass > 0 || $selectedTerm > 0) ? '?list_class_id=' . $selectedClass . '&list_term_id=' . $selectedTerm : ''; ?>" 
                           class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-download me-1"></i> Go to Reports
                        </a>
                    </div>
                </div>
            </div>
            <?php elseif ($isTeacherPortal && $canReportView): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card download-card account h-100">
                    <div class="card-body">
                        <div class="download-icon text-primary"><i class="bi bi-file-earmark-text"></i></div>
                        <h5 class="card-title">Learner Report Cards</h5>
                        <p class="card-text text-muted small">Open your results workspace and view report cards for learners in your assigned classes.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">Assigned Classes</span>
                            <span class="badge bg-light text-dark badge-status">Per Learner</span>
                        </div>
                        <a href="teacher/manage_results" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-arrow-right me-1"></i> Open Results Workspace
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Class Reports (All Students) -->
            <?php if (!$isTeacherPortal && $canReportGenerate): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card download-card results h-100">
                    <div class="card-body">
                        <div class="download-icon text-success"><i class="bi bi-file-pdf"></i></div>
                        <h5 class="card-title">Class Reports (Bulk PDF)</h5>
                        <p class="card-text text-muted small">Download all student report cards for an entire class as a single merged PDF document.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">All Students</span>
                            <span class="badge bg-light text-dark badge-status">Single PDF</span>
                        </div>
                        <a href="/srms/script/admin/class_report_pdf" target="_blank" class="btn btn-success btn-sm w-100 requires-filter">
                            <i class="bi bi-download me-1"></i> Download PDF
                        </a>
                    </div>
                </div>
            </div>
            <?php elseif ($isTeacherPortal && $canReportView): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card download-card results h-100">
                    <div class="card-body">
                        <div class="download-icon text-success"><i class="bi bi-file-spreadsheet"></i></div>
                        <h5 class="card-title">Class Results Summary</h5>
                        <p class="card-text text-muted small">Open a whole-class summary for the selected class and term, based on the classes assigned to you.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">Assigned Class</span>
                            <span class="badge bg-light text-dark badge-status">Print Ready</span>
                        </div>
                        <a href="teacher/class_report" target="_blank" class="btn btn-success btn-sm w-100 requires-filter" data-filter-kind="teacher-class-report">
                            <i class="bi bi-download me-1"></i> Open Class Summary
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Merit Lists -->
            <?php if (!$isTeacherPortal && $canReportGenerate): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card download-card merit h-100">
                    <div class="card-body">
                        <div class="download-icon text-warning"><i class="bi bi-medal"></i></div>
                        <h5 class="card-title">Class Merit Lists</h5>
                        <p class="card-text text-muted small">Download ranked merit lists with learner positions, grade summaries, and subject analysis for the selected class, term, and exam.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">Rankings</span>
                            <span class="badge bg-light text-dark badge-status">Performance</span>
                        </div>
                        <a href="/srms/script/admin/merit_list_pdf" target="_blank" class="btn btn-warning btn-sm w-100 requires-filter">
                            <i class="bi bi-download me-1"></i> Download Merit List
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Bulk Report Download (ZIP) -->
            <?php if (!$isTeacherPortal && $canReportGenerate): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card download-card results h-100">
                    <div class="card-body">
                        <div class="download-icon text-info"><i class="bi bi-archive"></i></div>
                        <h5 class="card-title">Download All Reports (ZIP)</h5>
                        <p class="card-text text-muted small">Download all student reports as individual PDFs in a compressed ZIP archive for batch processing.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">ZIP Archive</span>
                            <span class="badge bg-light text-dark badge-status">Individual Files</span>
                        </div>
                        <a href="/srms/script/admin/core/download_all_reports" class="btn btn-info btn-sm w-100 requires-filter">
                            <i class="bi bi-download me-1"></i> Download ZIP
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Class Results/Bulk Results -->
            <div class="col-md-6 col-lg-4">
                <div class="card download-card results h-100">
                    <div class="card-body">
                        <div class="download-icon text-success"><i class="bi bi-table"></i></div>
                        <h5 class="card-title">Class Results Overview</h5>
                        <p class="card-text text-muted small">View detailed class results with subject breakdowns, print sheets, or export bulk results data.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">Detailed View</span>
                            <span class="badge bg-light text-dark badge-status">Print Ready</span>
                        </div>
                        <a href="<?php echo $isTeacherPortal ? 'teacher/manage_results' : 'bulk_results' . (($selectedClass > 0 || $selectedTerm > 0) ? '?class=' . $selectedClass . '&term=' . $selectedTerm : ''); ?>" 
                           class="btn btn-success btn-sm w-100">
                            <i class="bi bi-arrow-right me-1"></i> Open Results View
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!$isTeacherPortal): ?>
            <!-- Financial Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="card download-card financial h-100">
                    <div class="card-body">
                        <div class="download-icon text-danger"><i class="bi bi-cash-coin"></i></div>
                        <h5 class="card-title">Financial Reports</h5>
                        <p class="card-text text-muted small">Export financial summaries, fee collections, payment methods, aging analysis, and defaulters reports.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">CSV Export</span>
                            <span class="badge bg-light text-dark badge-status">Analytics</span>
                        </div>
                        <a href="fees" class="btn btn-danger btn-sm w-100">
                            <i class="bi bi-arrow-right me-1"></i> View Financial
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Analytics & CSV Exports -->
            <?php if (!$isTeacherPortal): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card download-card results h-100">
                    <div class="card-body">
                        <div class="download-icon text-secondary"><i class="bi bi-graph-up"></i></div>
                        <h5 class="card-title">Results Analytics</h5>
                        <p class="card-text text-muted small">View detailed performance analytics by subject and class, with CSV export capabilities.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">Analytics</span>
                            <span class="badge bg-light text-dark badge-status">CSV Export</span>
                        </div>
                        <a href="results_analytics" class="btn btn-secondary btn-sm w-100">
                            <i class="bi bi-arrow-right me-1"></i> View Analytics
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Printable Class Lists -->
            <div class="col-md-6 col-lg-4">
                <div class="card download-card account h-100">
                    <div class="card-body">
                        <div class="download-icon text-primary"><i class="bi bi-layout-text-window-reverse"></i></div>
                        <h5 class="card-title">Printable Class Lists</h5>
                        <p class="card-text text-muted small">Open a clean class list with at least six working columns for attendance, marks entry, and classroom notes.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">Class Roster</span>
                            <span class="badge bg-light text-dark badge-status">Print Ready</span>
                        </div>
                        <a href="teacher/print_class_list<?php echo ($selectedClass > 0) ? '?origin_portal=' . $portal . '&class_id=' . $selectedClass : '?origin_portal=' . $portal; ?>" target="_blank" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-arrow-right me-1"></i> Open Class List Tool
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($isTeacherPortal && $canMarksEnter): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card download-card results h-100">
                    <div class="card-body">
                        <div class="download-icon text-secondary"><i class="bi bi-journal-medical"></i></div>
                        <h5 class="card-title">Printable Mark Sheets</h5>
                        <p class="card-text text-muted small">Open the mark sheet tool for the selected class and term, then choose the subject and exam you want to print.</p>
                        <div class="mt-3 mb-3">
                            <span class="badge bg-light text-dark badge-status">Marks Entry</span>
                            <span class="badge bg-light text-dark badge-status">Teacher Tool</span>
                        </div>
                        <a href="teacher/print_mark_sheet" target="_blank" class="btn btn-secondary btn-sm w-100 requires-filter" data-filter-kind="teacher-mark-sheet">
                            <i class="bi bi-arrow-right me-1"></i> Open Mark Sheet Tool
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Quick Summary -->
        <div class="row mt-5 mb-4">
            <div class="col-md-12">
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>How to use Downloads Center:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Select a <strong>Class</strong> and <strong>Term</strong> to filter available downloads</li>
                        <li><strong>Individual Reports</strong>: Download single student PDFs from the Reports page</li>
                        <?php if (!$isTeacherPortal): ?>
                        <li><strong>Bulk Downloads</strong>: Download entire class as one merged PDF or ZIP of individual files</li>
                        <li><strong>Merit Lists</strong>: Download ranked class PDFs with grade summaries and per-subject analysis</li>
                        <li><strong>Financial</strong>: Export fee collections, aging, and payment data</li>
                        <?php else: ?>
                        <li><strong>Teacher Access</strong>: Only your assigned classes and relevant report tools are shown here</li>
                        <li><strong>Mark Sheets & Class Lists</strong>: Open the printable tools with the selected class preloaded</li>
                        <?php endif; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function getSelections() {
            var c = document.getElementById('filterClass');
            var t = document.getElementById('filterTerm');
            var e = document.getElementById('filterExam');
            return { cls: c ? c.value : '0', term: t ? t.value : '0', exam: e ? e.value : '0' };
        }

        function loadExamOptions() {
            var classSelect = document.getElementById('filterClass');
            var termSelect = document.getElementById('filterTerm');
            var examSelect = document.getElementById('filterExam');
            if (!classSelect || !termSelect || !examSelect) {
                return;
            }

            var classId = classSelect.value || '0';
            var termId = termSelect.value || '0';
            var examMap = <?php echo json_encode($termExamMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
            var selectedExam = <?php echo (int)$selectedExam; ?>;
            var exams = (((examMap[classId] || {})[termId]) || []);
            var html = '<option value="0">-- All / Latest Published --</option>';
            exams.forEach(function (exam) {
                var isSelected = Number(exam.id) === Number(selectedExam) ? ' selected' : '';
                var status = String(exam.status || 'draft').toUpperCase();
                html += '<option value="' + exam.id + '"' + isSelected + '>' + String(exam.name || 'Exam') + ' [' + status + ']</option>';
            });
            examSelect.innerHTML = html;
        }

        function buildAndOpen(a) {
            var href = a.getAttribute('href') || '';
            var sel = getSelections();
            var cls = parseInt(sel.cls, 10) || 0;
            var term = parseInt(sel.term, 10) || 0;
            var exam = parseInt(sel.exam, 10) || 0;
            var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
            var adminBase = origin + '/srms/script/admin/';
            var teacherBase = origin + '/srms/script/teacher/';
            var portal = <?php echo json_encode($portal); ?>;

            if (href.indexOf('class_report_pdf') !== -1) {
                if (!cls || !term) { alert('Please select Class and Term first.'); return; }
                var url = adminBase + 'class_report_pdf?class=' + cls + '&term=' + term + '&download=1';
                window.open(url, '_blank');
                return;
            }

            if (href.indexOf('merit_list_pdf') !== -1) {
                if (!cls || !term) { alert('Please select Class and Term first.'); return; }
                var url = adminBase + 'merit_list_pdf?class_id=' + cls + '&term_id=' + term;
                if (exam > 0) { url += '&exam_id=' + exam; }
                window.open(url, '_blank');
                return;
            }

            if (href.indexOf('download_all_reports') !== -1) {
                if (!cls && !term) { alert('Please select at least Class or Term before downloading.'); return; }
                var url = adminBase + 'core/download_all_reports?list_class_id=' + cls + '&list_term_id=' + term;
                window.open(url, '_blank');
                return;
            }

            if (a.dataset.filterKind === 'teacher-class-report') {
                if (!cls || !term) { alert('Please select Class and Term first.'); return; }
                window.open(teacherBase + 'class_report?class=' + cls + '&term=' + term, '_blank');
                return;
            }

            if (a.dataset.filterKind === 'teacher-mark-sheet') {
                var url = teacherBase + 'print_mark_sheet?origin_portal=teacher';
                if (cls) { url += '&class_id=' + cls; }
                if (term) { url += '&term_id=' + term; }
                window.open(url, '_blank');
                return;
            }

            // fallback: follow link
            var base = portal === 'teacher' ? teacherBase : adminBase;
            window.open(href.indexOf('http') === 0 ? href : base + href, '_blank');
        }

        var els = document.querySelectorAll('a.requires-filter');
        els.forEach(function (a) {
            a.addEventListener('click', function (ev) {
                ev.preventDefault();
                buildAndOpen(a);
            });
        });

        loadExamOptions();
        var classSelect = document.getElementById('filterClass');
        var termSelect = document.getElementById('filterTerm');
        if (classSelect) { classSelect.addEventListener('change', loadExamOptions); }
        if (termSelect) { termSelect.addEventListener('change', loadExamOptions); }
    });
    </script>
    <script src="js/jquery-3.7.0.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
