<?php
/**
 * Database Helper Class
 * Enhanced utility class for database operations across all SchoolFix modules
 * Supports 80+ tables with prepared statements and transaction support
 */

class DatabaseHelper {
    private static $instance = null;
    private $conn;
    private $in_transaction = false;

    private function __construct() {
        $this->conn = new mysqli(
            getenv('DB_HOST'),
            getenv('DB_USER'),
            getenv('DB_PASS'),
            getenv('DB_NAME')
        );

        if ($this->conn->connect_error) {
            throw new Exception("Database connection failed: " . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8mb4");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute query with prepared statements
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @param string $types Type specification (i=int, s=string, d=double, b=blob)
     * @return mysqli_result|bool
     */
    public function query($sql, $params = [], $types = '') {
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types ?: str_repeat('s', count($params)), ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        return $stmt->get_result() ?: true;
    }

    /**
     * Insert a record
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int Last inserted ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = str_repeat('?,', count($data) - 1) . '?';
        
        $sql = "INSERT INTO `$table` (" . implode(',', $columns) . ") VALUES ($placeholders)";
        
        $types = $this->getTypes($data);
        $this->query($sql, $values, $types);
        
        return $this->conn->insert_id;
    }

    /**
     * Update records
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @param array $where WHERE conditions (column => value)
     * @return int Affected rows
     */
    public function update($table, $data, $where = []) {
        $set = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
        $values = array_values($data);
        
        $sql = "UPDATE `$table` SET $set";
        $types = $this->getTypes($data);
        
        if (!empty($where)) {
            $where_clause = implode(' AND ', array_map(fn($k) => "`$k`=?", array_keys($where)));
            $sql .= " WHERE $where_clause";
            $values = array_merge($values, array_values($where));
            $types .= $this->getTypes($where);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        
        return $stmt->affected_rows;
    }

    /**
     * Delete records
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @return int Affected rows
     */
    public function delete($table, $where) {
        $where_clause = implode(' AND ', array_map(fn($k) => "`$k`=?", array_keys($where)));
        $sql = "DELETE FROM `$table` WHERE $where_clause";
        
        $values = array_values($where);
        $types = $this->getTypes($where);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        
        return $stmt->affected_rows;
    }

    /**
     * Select with flexible filtering
     * @param string $table Table name
     * @param array $columns Columns to select (empty = *)
     * @param array $where WHERE conditions
     * @param string $order ORDER BY clause
     * @param int $limit LIMIT clause
     * @param int $offset OFFSET clause
     * @return array Results
     */
    public function select($table, $columns = [], $where = [], $order = '', $limit = 0, $offset = 0) {
        $cols = empty($columns) ? '*' : implode(',', $columns);
        $sql = "SELECT $cols FROM `$table`";
        
        $values = [];
        if (!empty($where)) {
            $where_clause = implode(' AND ', array_map(fn($k) => "`$k`=?", array_keys($where)));
            $sql .= " WHERE $where_clause";
            $values = array_values($where);
        }
        
        if (!empty($order)) {
            $sql .= " ORDER BY $order";
        }
        
        if ($limit > 0) {
            $sql .= " LIMIT $limit";
            if ($offset > 0) {
                $sql .= " OFFSET $offset";
            }
        }
        
        $types = empty($where) ? '' : $this->getTypes($where);
        $result = $this->query($sql, $values, $types);
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get single record
     */
    public function getOne($table, $where) {
        $results = $this->select($table, [], $where, '', 1);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Count records
     */
    public function count($table, $where = []) {
        $sql = "SELECT COUNT(*) as cnt FROM `$table`";
        
        $values = [];
        if (!empty($where)) {
            $where_clause = implode(' AND ', array_map(fn($k) => "`$k`=?", array_keys($where)));
            $sql .= " WHERE $where_clause";
            $values = array_values($where);
        }
        
        $types = empty($where) ? '' : $this->getTypes($where);
        $result = $this->query($sql, $values, $types);
        $row = $result->fetch_assoc();
        return $row['cnt'] ?? 0;
    }

    /**
     * Start transaction
     */
    public function beginTransaction() {
        $this->conn->begin_transaction();
        $this->in_transaction = true;
    }

    /**
     * Commit transaction
     */
    public function commit() {
        $this->conn->commit();
        $this->in_transaction = false;
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->conn->rollback();
        $this->in_transaction = false;
    }

    /**
     * Execute raw SQL (use with caution)
     */
    public function exec($sql) {
        return $this->conn->query($sql);
    }

    /**
     * Get connection
     */
    public function getConnection() {
        return $this->conn;
    }

    /**
     * Detect parameter types
     */
    private function getTypes($data) {
        $types = '';
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    /**
     * Generate unique ID (for distributed systems)
     */
    public static function generateUniqueId($prefix = '') {
        return $prefix . uniqid() . bin2hex(random_bytes(4));
    }

    /**
     * Audit log creation
     */
    public function auditLog($user_id, $action_type, $module, $record_type, $record_id, $old_value = null, $new_value = null) {
        return $this->insert('tbl_audit_logs', [
            'user_id' => $user_id,
            'action_type' => $action_type,
            'module' => $module,
            'record_type' => $record_type,
            'record_id' => $record_id,
            'old_value' => json_encode($old_value),
            'new_value' => json_encode($new_value),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * Soft delete with audit trail
     */
    public function softDelete($table, $record_id, $user_id) {
        $record = $this->getOne($table, ['id' => $record_id]);
        
        $this->insert('tbl_recycle_bin', [
            'record_type' => $table,
            'record_id' => $record_id,
            'original_data' => json_encode($record),
            'deleted_by' => $user_id,
            'restore_available_until' => date('Y-m-d', strtotime('+30 days'))
        ]);
        
        return $this->delete($table, ['id' => $record_id]);
    }

    /**
     * Validate and sanitize input
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public function __destruct() {
        if ($this->in_transaction) {
            $this->rollback();
        }
        // Keep connection alive for rest of request lifecycle
    }
}
