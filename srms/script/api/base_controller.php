<?php
/**
 * BaseController - Foundation class for all API endpoints
 * Handles authentication, responses, and common operations
 */

require_once __DIR__ . '/database_helper.php';

class BaseController {
    protected $db;
    protected $user_id;
    protected $user_role;
    protected $permissions = [];
    protected $request_method;
    protected $request_data = [];

    public function __construct() {
        $this->db = DatabaseHelper::getInstance();
        $this->request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->parseRequest();
        $this->authenticate();
    }

    /**
     * Parse incoming request
     */
    protected function parseRequest() {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        
        switch ($this->request_method) {
            case 'GET':
                $this->request_data = $_GET;
                break;
            case 'POST':
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                if (strpos($content_type, 'application/json') !== false) {
                    $this->request_data = json_decode(file_get_contents('php://input'), true) ?? [];
                } else {
                    $this->request_data = $_POST;
                }
                break;
        }
    }

    /**
     * Authenticate user
     */
    protected function authenticate() {
        // Check session or token
        if (isset($_SESSION['user_id'])) {
            $this->user_id = $_SESSION['user_id'];
            $this->user_role = $_SESSION['role'] ?? 'guest';
            $this->loadPermissions();
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->authenticateToken();
        } else {
            $this->user_id = null;
            $this->user_role = 'guest';
        }
    }

    /**
     * Load user permissions
     */
    protected function loadPermissions() {
        $role = $this->db->getOne('tbl_roles', ['id' => $this->user_role]);
        if ($role) {
            $perms = $this->db->select('tbl_role_permissions', ['permission_id'], ['role_id' => $role['id']]);
            // Get actual permission names
            // This would be expanded based on actual permission IDs
        }
    }

    /**
     * Check permission
     */
    protected function hasPermission($permission) {
        return in_array($permission, $this->permissions) || $this->user_role === 'admin';
    }

    /**
     * Require authentication
     */
    protected function requireAuth() {
        if (!$this->user_id) {
            return $this->respondError('Unauthorized', 401);
        }
    }

    /**
     * Require specific permission
     */
    protected function requirePermission($permission) {
        if (!$this->hasPermission($permission)) {
            return $this->respondError('Forbidden: ' . $permission, 403);
        }
    }

    /**
     * Response handlers
     */
    protected function respond($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'code' => $code,
            'data' => $data
        ]);
        exit;
    }

    protected function respondError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'code' => $code,
            'error' => $message
        ]);
        exit;
    }

    protected function respondList($data, $total = 0, $page = 1, $per_page = 20) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'pages' => ceil($total / $per_page)
            ]
        ]);
        exit;
    }

    /**
     * Get input value with validation
     */
    protected function getInput($key, $required = false, $default = null) {
        if (!isset($this->request_data[$key])) {
            if ($required) {
                $this->respondError("Missing required field: $key", 400);
            }
            return $default;
        }

        $value = $this->request_data[$key];
        return DatabaseHelper::sanitize($value);
    }

    /**
     * Validate email
     */
    protected function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate required fields
     */
    protected function validateRequired($fields) {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($this->request_data[$field]) || empty($this->request_data[$field])) {
                $missing[] = $field;
            }
        }
        if (!empty($missing)) {
            $this->respondError("Missing required fields: " . implode(', ', $missing), 400);
        }
    }

    /**
     * Pagination helper
     */
    protected function getPagination() {
        $page = max(1, intval($this->getInput('page', false, 1)));
        $per_page = min(100, intval($this->getInput('per_page', false, 20)));
        $offset = ($page - 1) * $per_page;
        
        return [
            'page' => $page,
            'per_page' => $per_page,
            'offset' => $offset
        ];
    }

    /**
     * Log audit trail
     */
    protected function log($action, $module, $record_type, $record_id, $old = null, $new = null) {
        return $this->db->auditLog($this->user_id, $action, $module, $record_type, $record_id, $old, $new);
    }

    /**
     * Dispatch to handler methods
     */
    public function dispatch($action = '') {
        if (empty($action)) {
            $action = $this->getInput('action', false, 'list');
        }

        $method = strtolower($this->request_method) . '_' . $action;
        
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        $this->respondError("Action not found: $action", 404);
    }

    /**
     * Default handlers
     */
    protected function get_list() {
        $this->respondError("Not implemented", 501);
    }

    protected function post_create() {
        $this->respondError("Not implemented", 501);
    }

    protected function put_update() {
        $this->respondError("Not implemented", 501);
    }

    protected function delete_remove() {
        $this->respondError("Not implemented", 501);
    }
}
