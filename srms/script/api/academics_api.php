<?php
/**
 * Academics Module - API Controller
 * Handles exams, marks, attendance, timetables, and online learning
 */

require_once __DIR__ . '/base_controller.php';

class AcademicsController extends BaseController {

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
     * Calculate grade from marks
     */
    private function calculateGrade($marks, $subject_id) {
        // This would typically use the school's grading system
        $grade_system = $this->db->getOne('tbl_grade_system', ['class' => 'A']);
        
        if ($marks >= 80) return 'A';
        if ($marks >= 70) return 'B';
        if ($marks >= 60) return 'C';
        if ($marks >= 50) return 'D';
        return 'E';
    }
}

// Route handling
$action = $_GET['action'] ?? 'exams';
$controller = new AcademicsController();
$controller->dispatch($action);
