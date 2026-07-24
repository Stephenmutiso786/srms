<?php
/**
 * Academics Module - API Controller
 * Handles exams, marks, attendance, timetables, and online learning
 */

require_once __DIR__ . '/base_controller.php';
require_once __DIR__ . '/../const/report_engine.php';

class AcademicsController extends BaseController {

    /**
     * Check whether a table exists in the current database.
     */
    private function tableExists($table) {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1"
            );
            $stmt->bind_param('s', $table);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_row();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Check whether a column exists in a table.
     */
    private function columnExists($table, $column) {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1"
            );
            $stmt->bind_param('ss', $table, $column);
            $stmt->execute();
            return (bool)$stmt->get_result()->fetch_row();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * List exams with filtering
     */
    protected function get_exams() {
        $this->requireAuth();
        
        $pagination = $this->getPagination();
        $year = $this->getInput('year');
        $term = $this->getInput('term');
        $exam_type = $this->getInput('exam_type');
        
        $where = [];
        if ($year) $where['year'] = $year;
        if ($term) $where['term'] = $term;
        if ($exam_type) $where['exam_type'] = $exam_type;
        
        $total = $this->db->count('tbl_exams', $where);
        $exams = $this->db->select(
            'tbl_exams',
            [],
            $where,
            'exam_date DESC',
            $pagination['per_page'],
            $pagination['offset']
        );

        $this->respondList($exams, $total, $pagination['page'], $pagination['per_page']);
    }

    /**
     * Create exam
     */
    protected function post_create_exam() {
        $this->requireAuth();
        $this->requirePermission('academics.manage_exams');
        
        $this->validateRequired(['name', 'exam_type']);
        
        $exam_id = $this->db->insert('tbl_exams', [
            'name' => $this->getInput('name', true),
            'exam_type' => $this->getInput('exam_type', true),
            'start_date' => $this->getInput('start_date'),
            'end_date' => $this->getInput('end_date'),
            'term' => $this->getInput('term'),
            'year' => date('Y'),
            'created_by' => $this->user_id
        ]);
        
        $this->log('create', 'academics', 'exams', $exam_id);
        $this->respond(['exam_id' => $exam_id], 201);
    }

    /**
     * Submit marks for class
     */
    protected function post_submit_marks() {
        $this->requireAuth();
        $this->requirePermission('academics.submit_marks');
        
        $this->validateRequired(['exam_id', 'class_id', 'subject_id', 'marks_data']);
        
        $exam_id = intval($this->getInput('exam_id', true));
        $class_id = intval($this->getInput('class_id', true));
        $subject_id = intval($this->getInput('subject_id', true));
        $section_id = $this->getInput('section_id');
        $marks_data = $this->getInput('marks_data', true);
        
        try {
            $this->db->beginTransaction();
            
            // Create submission record
            $submission_id = $this->db->insert('tbl_marks_submissions', [
                'exam_id' => $exam_id,
                'teacher_id' => $this->user_id,
                'class_id' => $class_id,
                'section_id' => $section_id,
                'subject_id' => $subject_id,
                'total_students' => count($marks_data),
                'status' => 'submitted',
                'submitted_by' => $this->user_id
            ]);
            
            // Insert individual marks
            foreach ($marks_data as $item) {
                // Update exam_results table
                $result_id = $this->db->getOne('tbl_exam_results', [
                    'student' => $item['student_id'],
                    'subject' => $subject_id,
                    'exam' => $exam_id
                ]);
                
                if ($result_id) {
                    $this->db->update('tbl_exam_results', [
                        'marks' => $item['marks'],
                        'grade' => $this->calculateGrade($item['marks'], $subject_id),
                        'timestamp' => date('Y-m-d H:i:s')
                    ], ['id' => $result_id['id']]);
                } else {
                    $this->db->insert('tbl_exam_results', [
                        'student' => $item['student_id'],
                        'subject' => $subject_id,
                        'marks' => $item['marks'],
                        'grade' => $this->calculateGrade($item['marks'], $subject_id),
                        'exam' => $exam_id,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            // Create audit log for marks submission
            $this->log('submit', 'academics', 'marks_submissions', $submission_id, null, [
                'exam_id' => $exam_id,
                'class_id' => $class_id,
                'subject_id' => $subject_id,
                'count' => count($marks_data)
            ]);
            
            $this->db->commit();
            
            $this->respond([
                'submission_id' => $submission_id,
                'message' => 'Marks submitted successfully and pending approval'
            ], 201);
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->respondError('Failed to submit marks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve submitted marks
     */
    protected function put_approve_marks() {
        $this->requireAuth();
        $this->requirePermission('academics.approve_marks');
        
        $this->validateRequired(['submission_id']);
        
        $submission_id = $this->getInput('submission_id', true);
        $approval_status = $this->getInput('status', false, 'approved');
        $comments = $this->getInput('comments', false, '');
        
        try {
            $this->db->beginTransaction();
            
            // Update submission status
            $this->db->update('tbl_marks_submissions', [
                'status' => $approval_status
            ], ['id' => $submission_id]);
            
            // Create approval record
            $this->db->insert('tbl_marks_approval', [
                'submission_id' => $submission_id,
                'approver_id' => $this->user_id,
                'status' => $approval_status,
                'comments' => $comments,
                'approved_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->log('approve', 'academics', 'marks_submissions', $submission_id);
            
            $this->db->commit();
            
            $this->respond([
                'submission_id' => $submission_id,
                'message' => 'Marks ' . $approval_status
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->respondError('Failed to approve marks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record attendance
     */
    protected function post_record_attendance() {
        $this->requireAuth();
        $this->requirePermission('academics.manage_attendance');
        
        $this->validateRequired(['class_id', 'attendance_date', 'attendance_data']);
        
        $class_id = $this->getInput('class_id', true);
        $attendance_date = $this->getInput('attendance_date', true);
        $attendance_data = $this->getInput('attendance_data', true);
        
        try {
            $this->db->beginTransaction();
            
            foreach ($attendance_data as $item) {
                $this->db->insert('tbl_attendance', [
                    'student_id' => $item['student_id'],
                    'class_id' => $class_id,
                    'attendance_date' => $attendance_date,
                    'status' => $item['status'],
                    'reason' => $item['reason'] ?? null,
                    'marked_by' => $this->user_id
                ]);
            }
            
            $this->log('create', 'academics', 'attendance', 0, null, [
                'class_id' => $class_id,
                'date' => $attendance_date,
                'count' => count($attendance_data)
            ]);
            
            $this->db->commit();
            
            $this->respond([
                'message' => count($attendance_data) . ' attendance records created'
            ], 201);
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->respondError('Failed to record attendance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get student report card
     */
    protected function get_report_card() {
        $this->requireAuth();
        
        $student_id = $this->getInput('student_id', true);
        $exam_id = $this->getInput('exam_id', true);
        
        $student = $this->db->getOne('tbl_students', ['id' => $student_id]);
        $exam = $this->db->getOne('tbl_exams', ['id' => $exam_id]);
        
        $results = $this->db->select('tbl_exam_results', [], [
            'student' => $student_id,
            'exam' => $exam_id
        ]);
        
        // Ensure report_card exists or create
        $report_card = $this->db->getOne('tbl_report_cards', [
            'student_id' => $student_id,
            'exam_id' => $exam_id
        ]);
        
        if (!$report_card) {
            $rc_id = $this->db->insert('tbl_report_cards', [
                'student_id' => $student_id,
                'class_id' => $student['class_id'],
                'exam_id' => $exam_id,
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => $this->user_id
            ]);
            $report_card = ['id' => $rc_id];
        }
        
        $this->respond([
            'student' => $student,
            'exam' => $exam,
            'results' => $results,
            'report_card' => $report_card
        ]);
    }

    /**
     * Online quiz management
     */
    protected function get_quizzes() {
        $this->requireAuth();
        
        $class_id = $this->getInput('class_id');
        $subject_id = $this->getInput('subject_id');
        
        $where = [];
        if ($class_id) $where['class_id'] = $class_id;
        if ($subject_id) $where['subject_id'] = $subject_id;
        $where['is_published'] = 1;
        
        $quizzes = $this->db->select('tbl_online_quizzes', [], $where);
        
        $this->respond($quizzes);
    }

    /**
     * Submit quiz answers
     */
    protected function post_submit_quiz() {
        $this->requireAuth();
        
        $this->validateRequired(['quiz_id', 'answers']);
        
        $quiz_id = $this->getInput('quiz_id', true);
        $answers = $this->getInput('answers', true);
        
        try {
            $this->db->beginTransaction();
            
            // Create submission
            $submission_id = $this->db->insert('tbl_quiz_submissions', [
                'quiz_id' => $quiz_id,
                'student_id' => $this->user_id,
                'started_at' => date('Y-m-d H:i:s'),
                'submitted_at' => date('Y-m-d H:i:s'),
                'status' => 'submitted'
            ]);
            
            $total_score = 0;
            $total_points = 0;
            
            // Record answers and score
            foreach ($answers as $answer) {
                $question = $this->db->getOne('tbl_quiz_questions', ['id' => $answer['question_id']]);
                $is_correct = false;
                $points = 0;
                
                if ($answer['answer'] == $question['correct_answer']) {
                    $is_correct = true;
                    $points = $question['points'];
                    $total_score += $points;
                }
                
                $total_points += $question['points'];
                
                $this->db->insert('tbl_quiz_answers', [
                    'submission_id' => $submission_id,
                    'question_id' => $answer['question_id'],
                    'answer_text' => $answer['answer'],
                    'is_correct' => $is_correct ? 1 : 0,
                    'points_earned' => $points
                ]);
            }
            
            // Update submission with score
            $percentage = $total_points > 0 ? round(($total_score / $total_points) * 100) : 0;
            $this->db->update('tbl_quiz_submissions', [
                'total_score' => $total_score,
                'percentage' => $percentage,
                'status' => 'graded'
            ], ['id' => $submission_id]);
            
            $this->db->commit();
            
            $this->respond([
                'submission_id' => $submission_id,
                'score' => $total_score,
                'total_points' => $total_points,
                'percentage' => $percentage
            ], 201);
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->respondError('Failed to submit quiz: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Timetable management
     */
    protected function get_timetable() {
        $this->requireAuth();
        
        $class_id = $this->getInput('class_id', true);
        $section_id = $this->getInput('section_id');
        
        $where = ['is_active' => 1];
        
        $timetables = $this->db->select('tbl_timetables', [], $where);
        
        if (!empty($timetables)) {
            $timetable = $timetables[0]; // Get active timetable
            $lessons = $this->db->select('tbl_timetable_lessons', [], [
                'timetable_id' => $timetable['id'],
                'class_id' => $class_id
            ]);
            
            if ($section_id) {
                $lessons = array_filter($lessons, fn($l) => $l['section_id'] == $section_id || !$l['section_id']);
            }
            
            // Organize by day and period
            $schedule = [];
            foreach ($lessons as $lesson) {
                $day = $lesson['day_of_week'];
                $period = $lesson['period_slot'];
                $schedule[$day][$period] = $lesson;
            }
            
            $timetable['schedule'] = $schedule;
        }
        
        $this->respond($timetable ?? []);
    }

    /**
     * Academics dashboard summary
     */
    protected function get_dashboard() {
        $this->requireAuth();

        $summary = [
            'classes' => 0,
            'subjects' => 0,
            'students' => 0,
            'teachers' => 0,
            'exams' => 0,
            'active_timetables' => 0,
            'assignments' => 0,
            'quizzes' => 0,
            'attendance_today' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0],
        ];

        if ($this->tableExists('tbl_classes')) {
            $summary['classes'] = (int)$this->db->count('tbl_classes');
        }
        if ($this->tableExists('tbl_subjects')) {
            $summary['subjects'] = (int)$this->db->count('tbl_subjects');
        }
        if ($this->tableExists('tbl_students')) {
            $summary['students'] = (int)$this->db->count('tbl_students');
        }
        if ($this->tableExists('tbl_staff')) {
            $summary['teachers'] = (int)$this->db->count('tbl_staff', ['level' => 2]);
        }
        if ($this->tableExists('tbl_exams')) {
            $summary['exams'] = (int)$this->db->count('tbl_exams');
        }
        if ($this->tableExists('tbl_timetables')) {
            $summary['active_timetables'] = (int)$this->db->count('tbl_timetables', ['is_active' => 1]);
        }
        if ($this->tableExists('tbl_assignments')) {
            $summary['assignments'] = (int)$this->db->count('tbl_assignments');
        }
        if ($this->tableExists('tbl_online_quizzes')) {
            $summary['quizzes'] = (int)$this->db->count('tbl_online_quizzes');
        }
        if ($this->tableExists('tbl_attendance')) {
            $rows = $this->db->select('tbl_attendance', ['status', 'COUNT(*) AS count'], ['attendance_date' => date('Y-m-d')], 'status');
            foreach ($rows as $row) {
                $status = $row['status'] ?? '';
                if ($status !== '' && isset($summary['attendance_today'][$status])) {
                    $summary['attendance_today'][$status] = (int)($row['count'] ?? 0);
                }
            }
        }

        $this->respond($summary);
    }

    /**
     * List classes and class head summary
     */
    protected function get_classes() {
        $this->requireAuth();

        if (!$this->tableExists('tbl_classes')) {
            $this->respond([]);
        }

        $classes = $this->db->select('tbl_classes', []);
        app_sort_class_rows($classes);
        foreach ($classes as &$classRow) {
            if ($this->columnExists('tbl_students', 'class_id')) {
                $classRow['student_count'] = $this->db->count('tbl_students', ['class_id' => $classRow['id']]);
            } elseif ($this->columnExists('tbl_students', 'class')) {
                $classRow['student_count'] = $this->db->count('tbl_students', ['class' => $classRow['id']]);
            } else {
                $classRow['student_count'] = 0;
            }
        }

        $this->respond($classes);
    }

    /**
     * List subjects with usage counts
     */
    protected function get_subjects() {
        $this->requireAuth();

        if (!$this->tableExists('tbl_subjects')) {
            $this->respond([]);
        }

        $subjects = $this->db->select('tbl_subjects', []);
        $this->respond($subjects);
    }

    /**
     * List active terms
     */
    protected function get_terms() {
        $this->requireAuth();

        if (!$this->tableExists('tbl_terms')) {
            $this->respond([]);
        }

        $terms = $this->db->select('tbl_terms', []);
        app_sort_term_rows($terms);
        $this->respond($terms);
    }

    /**
     * List students with academic filters
     */
    protected function get_students() {
        $this->requireAuth();

        if (!$this->tableExists('tbl_students')) {
            $this->respond([]);
        }

        $where = [];
        $classId = $this->getInput('class_id');
        if ($classId) {
            if ($this->columnExists('tbl_students', 'class_id')) {
                $where['class_id'] = $classId;
            } elseif ($this->columnExists('tbl_students', 'class')) {
                $where['class'] = $classId;
            }
        }

        $students = $this->db->select('tbl_students', [], $where, 'id DESC', 100);
        $this->respond($students);
    }

    /**
     * List assignments for a class/subject.
     */
    protected function get_assignments() {
        $this->requireAuth();

        if (!$this->tableExists('tbl_assignments')) {
            $this->respond([]);
        }

        $where = [];
        $classId = $this->getInput('class_id');
        $subjectId = $this->getInput('subject_id');
        if ($classId) $where['class_id'] = $classId;
        if ($subjectId) $where['subject_id'] = $subjectId;

        $assignments = $this->db->select('tbl_assignments', [], $where, 'due_date ASC');
        $this->respond($assignments);
    }

    /**
     * Create assignment.
     */
    protected function post_create_assignment() {
        $this->requireAuth();
        $this->requirePermission('academics.manage_assignments');

        $this->validateRequired(['title', 'due_date']);

        if (!$this->tableExists('tbl_assignments')) {
            $this->respondError('Assignments table is not available', 501);
        }

        $assignmentId = $this->db->insert('tbl_assignments', [
            'title' => $this->getInput('title', true),
            'description' => $this->getInput('description'),
            'subject_id' => $this->getInput('subject_id'),
            'class_id' => $this->getInput('class_id'),
            'teacher_id' => $this->user_id,
            'issued_date' => $this->getInput('issued_date', false, date('Y-m-d')),
            'due_date' => $this->getInput('due_date', true),
            'total_marks' => intval($this->getInput('total_marks', false, 100)),
            'is_published' => intval($this->getInput('is_published', false, 0))
        ]);

        $this->log('create', 'academics', 'assignments', $assignmentId);
        $this->respond(['assignment_id' => $assignmentId], 201);
    }

    /**
     * Attendance summary for today or a given class.
     */
    protected function get_attendance_summary() {
        $this->requireAuth();

        if (!$this->tableExists('tbl_attendance')) {
            $this->respond(['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0]);
        }

        $date = $this->getInput('attendance_date', false, date('Y-m-d'));
        $classId = $this->getInput('class_id');

        $where = ['attendance_date' => $date];
        if ($classId) {
            $where['class_id'] = $classId;
        }

        $rows = $this->db->select('tbl_attendance', ['status', 'COUNT(*) AS count'], $where, 'status');
        $summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            if ($status !== '' && isset($summary[$status])) {
                $summary[$status] = (int)($row['count'] ?? 0);
            }
        }

        $this->respond($summary);
    }

    /**
     * Latest pathway analysis records.
     */
    protected function get_pathway_analysis() {
        $this->requireAuth();

        if (!$this->tableExists('tbl_pathway_analysis')) {
            $this->respond([]);
        }

        $studentId = $this->getInput('student_id');
        $where = [];
        if ($studentId) $where['student_id'] = $studentId;

        $analysis = $this->db->select('tbl_pathway_analysis', [], $where, 'analysis_date DESC', 50);
        $this->respond($analysis);
    }

    /**
     * Competency profile for CBE/CBE.
     */
    protected function get_competency_profile() {
        $this->requireAuth();

        if (!$this->tableExists('tbl_cbe_competencies')) {
            $this->respond([]);
        }

        $studentId = $this->getInput('student_id', true);
        $competencies = $this->db->select('tbl_cbe_competencies', [], ['student_id' => $studentId], 'id DESC');
        $this->respond($competencies);
    }

    /**
     * Result summary by exam.
     */
    protected function get_results_summary() {
        $this->requireAuth();

        if (!$this->tableExists('tbl_exam_results')) {
            $this->respond([]);
        }

        $examId = $this->getInput('exam_id', true);
        $scoreColumn = $this->columnExists('tbl_exam_results', 'marks') ? 'marks' : 'score';
        $summary = $this->db->select('tbl_exam_results', [], ['exam' => $examId]);

        $total = 0;
        $count = 0;
        foreach ($summary as $row) {
            $total += (float)($row[$scoreColumn] ?? 0);
            $count++;
        }

        $this->respond([
            'exam_id' => $examId,
            'students' => $count,
            'average_score' => $count > 0 ? round($total / $count, 2) : 0,
            'results' => $summary
        ]);
    }

    /**
     * Calculate grade from marks
     */
    private function calculateGrade($marks, $subject_id) {
        try {
            $gradingSystemId = function_exists('report_default_grading_system_id')
                ? report_default_grading_system_id(app_db())
                : null;
            if (function_exists('report_grade_for_score')) {
                list($grade) = report_grade_for_score(app_db(), (float)$marks, $gradingSystemId);
                return (string)$grade;
            }
        } catch (Throwable $e) {
            // Fall through to a CBE-style default if the grading engine is unavailable.
        }
        if ($marks >= 90) return 'EE1';
        if ($marks >= 75) return 'EE2';
        if ($marks >= 58) return 'ME1';
        if ($marks >= 41) return 'ME2';
        if ($marks >= 31) return 'AE1';
        if ($marks >= 21) return 'AE2';
        if ($marks >= 11) return 'BE1';
        return 'BE2';
    }
}

// Route handling
$action = $_GET['action'] ?? 'exams';
$controller = new AcademicsController();
$controller->dispatch($action);
