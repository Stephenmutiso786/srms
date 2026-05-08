<?php
/**
 * Staff Management Module - API Controller
 * Handles staff records, departments, positions, leaves, and performance
 */

require_once __DIR__ . '/base_controller.php';
require_once __DIR__ . '/../const/report_engine.php';

class StaffController extends BaseController {

    /**
     * List staff with filtering
     */
    protected function get_staff() {
        $this->requireAuth();
        
        $pagination = $this->getPagination();
        $department = $this->getInput('department');
        $position = $this->getInput('position');
        $status = $this->getInput('status', false, 1);
        
        $where = [];
        if ($department) $where['department'] = $department;
        if ($position) $where['position'] = $position;
        $where['status'] = $status;
        
        $total = $this->db->count('tbl_staff', $where);
        $staff = $this->db->select(
            'tbl_staff',
            [],
            $where,
            'first_name ASC, last_name ASC',
            $pagination['per_page'],
            $pagination['offset']
        );

        // Enrich with department and role info
        foreach ($staff as &$member) {
            if ($member['department']) {
                $dept = $this->db->getOne('tbl_departments', ['id' => $member['department']]);
                $member['department_name'] = $dept['name'] ?? '';
            }
            
            $roles = $this->db->select('tbl_staff_roles', ['role_id'], ['staff_id' => $member['id']]);
            $member['roles'] = array_map(fn($r) => $r['role_id'], $roles);
        }

        $this->respondList($staff, $total, $pagination['page'], $pagination['per_page']);
    }

    /**
     * Add new staff member
     */
    protected function post_create_staff() {
        $this->requireAuth();
        $this->requirePermission('admin.manage_staff');
        
        $this->validateRequired(['first_name', 'last_name', 'email']);
        
        $email = $this->getInput('email', true);
        
        // Check if email already exists
        if ($this->db->getOne('tbl_staff', ['email_address' => $email])) {
            $this->respondError('Email already registered', 400);
        }
        
        try {
            $this->db->beginTransaction();
            
            $staff_id = $this->db->insert('tbl_staff', [
                'first_name' => $this->getInput('first_name', true),
                'last_name' => $this->getInput('last_name', true),
                'email_address' => $email,
                'phone' => $this->getInput('phone'),
                'department' => $this->getInput('department'),
                'position' => $this->getInput('position'),
                'employment_date' => $this->getInput('employment_date'),
                'status' => 1,
                'pf_number' => $this->generatePFNumber(),
                'tsced_certification' => $this->getInput('tsced_certification'),
                'registration_number' => $this->generateRegistrationNumber()
            ]);
            
            // Assign default role
            $role_id = $this->getInput('role_id', false, 4); // Default teacher role
            $this->db->insert('tbl_staff_roles', [
                'staff_id' => $staff_id,
                'role_id' => $role_id,
                'assigned_by' => $this->user_id
            ]);
            
            // Create user account if credentials provided
            if ($this->getInput('create_account')) {
                $password = password_hash($this->getInput('password'), PASSWORD_BCRYPT);
                $this->db->insert('tbl_login_sessions', [
                    'staff_id' => $staff_id,
                    'password' => $password,
                    'is_active' => 1
                ]);
            }
            
            $this->log('create', 'staff', 'staff', $staff_id);
            
            $this->db->commit();
            
            $this->respond([
                'staff_id' => $staff_id,
                'pf_number' => $this->generatePFNumber(),
                'message' => 'Staff member created successfully'
            ], 201);
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->respondError('Failed to create staff: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Department management
     */
    protected function get_departments() {
        $this->requireAuth();
        
        $departments = $this->db->select('tbl_departments', [], ['is_active' => 1]);
        
        foreach ($departments as &$dept) {
            // Get head
            if ($dept['head_id']) {
                $head = $this->db->getOne('tbl_staff', ['id' => $dept['head_id']]);
                $dept['head_name'] = $head['first_name'] . ' ' . $head['last_name'];
            }
            
            // Count staff in department
            $staff_count = $this->db->count('tbl_staff', ['department' => $dept['id']]);
            $dept['staff_count'] = $staff_count;
        }
        
        $this->respond($departments);
    }

    protected function post_create_department() {
        $this->requireAuth();
        $this->requirePermission('admin.manage_departments');
        
        $this->validateRequired(['name']);
        
        $dept_id = $this->db->insert('tbl_departments', [
            'name' => $this->getInput('name', true),
            'description' => $this->getInput('description'),
            'head_id' => $this->getInput('head_id'),
            'budget_allocation' => floatval($this->getInput('budget_allocation', false, 0))
        ]);
        
        $this->log('create', 'staff', 'departments', $dept_id);
        $this->respond(['department_id' => $dept_id], 201);
    }

    /**
     * Leave management
     */
    protected function post_request_leave() {
        $this->requireAuth();
        
        $this->validateRequired(['leave_type', 'start_date', 'end_date']);
        
        try {
            // Calculate days
            $start = new DateTime($this->getInput('start_date', true));
            $end = new DateTime($this->getInput('end_date', true));
            $days = $start->diff($end)->days + 1;
            
            $leave_id = $this->db->insert('tbl_leave_requests', [
                'staff_id' => $this->user_id,
                'leave_type' => $this->getInput('leave_type', true),
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'number_of_days' => $days,
                'reason' => $this->getInput('reason'),
                'status' => 'pending'
            ]);
            
            $this->log('create', 'staff', 'leave_requests', $leave_id);
            
            $this->respond([
                'leave_id' => $leave_id,
                'days_requested' => $days,
                'message' => 'Leave request submitted for approval'
            ], 201);
            
        } catch (Exception $e) {
            $this->respondError('Failed to request leave: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Approve/reject leave
     */
    protected function put_approve_leave() {
        $this->requireAuth();
        $this->requirePermission('admin.approve_leaves');
        
        $this->validateRequired(['leave_id', 'status']);
        
        $leave_id = $this->getInput('leave_id', true);
        $status = $this->getInput('status', true);
        $comments = $this->getInput('comments');
        
        if (!in_array($status, ['approved', 'rejected', 'cancelled'])) {
            $this->respondError('Invalid status', 400);
        }
        
        $this->db->update('tbl_leave_requests', [
            'status' => $status,
            'approved_by' => $this->user_id,
            'approved_at' => date('Y-m-d H:i:s'),
            'approval_comments' => $comments
        ], ['id' => $leave_id]);
        
        $this->log('update', 'staff', 'leave_requests', $leave_id, ['status' => 'pending'], ['status' => $status]);
        
        $this->respond([
            'leave_id' => $leave_id,
            'message' => 'Leave request ' . $status
        ]);
    }

    /**
     * Performance appraisal
     */
    protected function post_create_appraisal() {
        $this->requireAuth();
        $this->requirePermission('admin.manage_appraisals');
        
        $this->validateRequired(['staff_id', 'appraisal_period']);
        
        $appraisal_id = $this->db->insert('tbl_staff_appraisals', [
            'staff_id' => $this->getInput('staff_id', true),
            'appraisal_period' => $this->getInput('appraisal_period', true),
            'appraisal_date' => date('Y-m-d'),
            'appraiser_id' => $this->user_id,
            'status' => 'draft'
        ]);
        
        $this->log('create', 'staff', 'staff_appraisals', $appraisal_id);
        $this->respond(['appraisal_id' => $appraisal_id], 201);
    }

    protected function put_complete_appraisal() {
        $this->requireAuth();
        
        $this->validateRequired(['appraisal_id', 'appraisal_score']);
        
        $appraisal_id = $this->getInput('appraisal_id', true);
        $score = floatval($this->getInput('appraisal_score', true));
        
        // Calculate grade
        $grade = $this->calculatePerformanceGrade($score);
        
        $this->db->update('tbl_staff_appraisals', [
            'appraisal_score' => $score,
            'appraisal_grade' => $grade,
            'supervisor_comments' => $this->getInput('comments'),
            'status' => 'completed'
        ], ['id' => $appraisal_id]);
        
        $this->log('update', 'staff', 'staff_appraisals', $appraisal_id);
        
        $this->respond([
            'appraisal_id' => $appraisal_id,
            'score' => $score,
            'grade' => $grade
        ]);
    }

    /**
     * Get staff directory
     */
    protected function get_directory() {
        $this->requireAuth();
        
        $departments = $this->db->select('tbl_departments', []);
        
        $directory = [];
        foreach ($departments as $dept) {
            $staff = $this->db->select('tbl_staff', [], ['department' => $dept['id']]);
            $directory[$dept['name']] = $staff;
        }
        
        $this->respond($directory);
    }

    /**
     * Staff contact list for notifications
     */
    protected function get_contacts() {
        $this->requireAuth();
        
        $role_id = $this->getInput('role_id');
        $department_id = $this->getInput('department_id');
        
        $where = ['status' => 1];
        
        if ($role_id) {
            // Get staff with specific role
            $staff_roles = $this->db->select('tbl_staff_roles', ['staff_id'], ['role_id' => $role_id]);
            $staff_ids = array_map(fn($r) => $r['staff_id'], $staff_roles);
            
            if (empty($staff_ids)) {
                $this->respond([]);
                return;
            }
        }
        
        if ($department_id) {
            $where['department'] = $department_id;
        }
        
        $staff = $this->db->select('tbl_staff', ['id', 'first_name', 'last_name', 'email_address', 'phone'], $where);
        
        $this->respond($staff);
    }

    /**
     * Generate PF number
     */
    private function generatePFNumber() {
        $series = $this->db->getOne('tbl_no_series', ['series_type' => 'pf_numbers']);
        
        if (!$series) {
            $this->db->insert('tbl_no_series', [
                'series_type' => 'pf_numbers',
                'current_value' => 1
            ]);
            $series = ['current_value' => 1];
        }
        
        $pf = sprintf('PF%04d', $series['current_value']);
        
        $this->db->update('tbl_no_series',
            ['current_value' => $series['current_value'] + 1],
            ['series_type' => 'pf_numbers']
        );
        
        return $pf;
    }

    /**
     * Generate registration number
     */
    private function generateRegistrationNumber() {
        $year = date('Y');
        $series = $this->db->getOne('tbl_no_series', ['series_type' => 'registration_numbers']);
        
        if (!$series) {
            $this->db->insert('tbl_no_series', [
                'series_type' => 'registration_numbers',
                'current_value' => 1
            ]);
            $series = ['current_value' => 1];
        }
        
        $reg = sprintf('REG%s%03d', $year, $series['current_value']);
        
        $this->db->update('tbl_no_series',
            ['current_value' => $series['current_value'] + 1],
            ['series_type' => 'registration_numbers']
        );
        
        return $reg;
    }

    /**
     * Calculate performance grade
     */
    private function calculatePerformanceGrade($score) {
        try {
            $gradingSystemId = function_exists('report_default_grading_system_id')
                ? report_default_grading_system_id(app_db())
                : null;
            if (function_exists('report_grade_for_score')) {
                list($grade) = report_grade_for_score(app_db(), (float)$score, $gradingSystemId);
                return (string)$grade;
            }
        } catch (Throwable $e) {
            // Fall through to a CBE-style default if the grading engine is unavailable.
        }
        if ($score >= 90) return 'EE1';
        if ($score >= 75) return 'EE2';
        if ($score >= 58) return 'ME1';
        if ($score >= 41) return 'ME2';
        if ($score >= 31) return 'AE1';
        if ($score >= 21) return 'AE2';
        if ($score >= 11) return 'BE1';
        return 'BE2';
    }
}

// Route handling
$action = $_GET['action'] ?? 'staff';
$controller = new StaffController();
$controller->dispatch($action);
