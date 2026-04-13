<?php

function app_certificate_types(): array
{
    return [
        'primary_completion' => 'Primary Education Completion Certificate',
        'junior_completion' => 'Junior Secondary Completion Certificate',
        'leaving' => 'Kenya Primary School Leaving Certificate',
        'transfer' => 'Transfer Certificate',
        'conduct' => 'Good Conduct Certificate',
        'merit' => 'Merit Certificate',
        'bonafide' => 'Bonafide Student Certificate',
    ];
}

function app_certificate_category_label(string $category): string
{
    $categories = [
        'primary_completion' => 'Primary Completion',
        'junior_completion' => 'Junior Secondary Completion',
        'leaving' => 'Kenya Primary School Leaving',
        'transfer' => 'Transfer',
        'conduct' => 'Good Conduct',
        'merit' => 'Merit',
        'general' => 'General',
    ];
    return $categories[$category] ?? 'Certificate';
}

function app_merit_grade_from_score(float $score): string
{
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B';
    if ($score >= 50) return 'C';
    if ($score >= 40) return 'D';
    return 'E';
}

function app_merit_grade_label(string $grade): string
{
    $labels = [
        'A' => 'Excellent (80-100)',
        'B' => 'Very Good (70-79)',
        'C' => 'Good (50-69)',
        'D' => 'Fair (40-49)',
        'E' => 'Improve (Below 40)',
    ];
    return $labels[$grade] ?? 'Unclassified';
}

function app_merit_grade_description(string $grade): string
{
    $descriptions = [
        'A' => 'Outstanding performance with excellent mastery of concepts',
        'B' => 'Very good performance showing strong understanding',
        'C' => 'Good performance with adequate understanding',
        'D' => 'Fair performance with basic understanding',
        'E' => 'Needs improvement; learner should focus on weak areas',
    ];
    return $descriptions[$grade] ?? 'Performance not classified';
}

function app_certificate_hash(array $payload): string
{
    $secret = defined('APP_SECRET') && APP_SECRET !== '' ? APP_SECRET : 'elimu-hub';
    return hash('sha256', json_encode($payload) . '|' . $secret);
}

function app_certificate_serial(string $type, string $studentId): string
{
    $prefix = strtoupper(substr($type, 0, 3));
    return 'CERT-' . $prefix . '-' . date('Y') . '-' . preg_replace('/[^A-Za-z0-9]/', '', $studentId) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

function app_certificate_code(string $studentId): string
{
    return 'CERTV-' . date('Y') . '-' . preg_replace('/[^A-Za-z0-9]/', '', $studentId) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function app_certificate_verify_url(string $code): string
{
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim((string)APP_URL, '/') . '/verify_certificate?code=' . urlencode($code);
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return 'http://' . $host . '/verify_certificate?code=' . urlencode($code);
}

/**
 * Get default CBC competencies for certificate generation
 */
function app_cbc_competencies(): array
{
    return [
        'communication' => [
            'name' => 'Communication & Collaboration',
            'code' => 'CC-001',
            'levels' => ['Developing', 'Proficient', 'Advanced', 'Excellent'],
        ],
        'critical_thinking' => [
            'name' => 'Critical Thinking & Problem Solving',
            'code' => 'CTPS-001',
            'levels' => ['Developing', 'Proficient', 'Advanced', 'Excellent'],
        ],
        'creativity' => [
            'name' => 'Creativity & Imagination',
            'code' => 'CI-001',
            'levels' => ['Developing', 'Proficient', 'Advanced', 'Excellent'],
        ],
        'citizenship' => [
            'name' => 'Citizenship & Personal Development',
            'code' => 'CPD-001',
            'levels' => ['Developing', 'Proficient', 'Advanced', 'Excellent'],
        ],
        'digital' => [
            'name' => 'Digital Literacy',
            'code' => 'DL-001',
            'levels' => ['Developing', 'Proficient', 'Advanced', 'Excellent'],
        ],
    ];
}

/**
 * Prepare competencies JSON for certificate storage
 */
function app_prepare_competencies(array $competencies): string
{
    $prepared = [
        'assessed_at' => date('Y-m-d H:i:s'),
        'competencies' => [],
    ];
    
    foreach ($competencies as $key => $data) {
        if (is_array($data) && isset($data['level'])) {
            $prepared['competencies'][$key] = [
                'achievement_level' => $data['level'],
                'comment' => $data['comment'] ?? '',
            ];
        }
    }
    
    return json_encode($prepared);
}

/**
 * Parse competencies from JSON for display
 */
function app_parse_competencies(string $json): array
{
    $data = (array)json_decode($json, true);
    return $data['competencies'] ?? [];
}

/**
 * Check if student qualifies for automatic certificate generation
 */
function app_certificate_auto_eligible(PDO $conn, int $studentId, int $classLevel, float $meanScore): bool
{
    // Automatic certificate generated for:
    // - Grade 6 (Primary completion) with mean score >= 40
    // - Grade 9 (Junior Secondary completion) with mean score >= 40
    $eligibleGrades = [6, 9];
    
    if (!in_array($classLevel, $eligibleGrades, true)) {
        return false;
    }
    
    if ($meanScore < 40.0) {
        return false;
    }
    
    return true;
}

/**
 * Get promotion eligibility checklist for student
 */
function app_promotion_eligibility_check(PDO $conn, int $studentId): array
{
    $checks = [
        'fees_cleared' => false,
        'report_finalized' => false,
        'eligible_for_promotion' => false,
        'messages' => [],
    ];
    
    try {
        // Check fees clearance
        $stmt = $conn->prepare('
            SELECT 
                (COALESCE(SUM(cf.amount), 0) - COALESCE(SUM(cp.amount), 0)) as balance
            FROM tbl_students st
            LEFT JOIN tbl_fees_charged cf ON cf.student_id = st.id
            LEFT JOIN tbl_fees_paid cp ON cp.student_id = st.id
            WHERE st.id = ?
            GROUP BY st.id
        ');
        $stmt->execute([$studentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $balance = (float)($result['balance'] ?? 0);
        $checks['fees_cleared'] = $balance <= 0;
        if (!$checks['fees_cleared']) {
            $checks['messages'][] = 'Outstanding fees: KES ' . number_format($balance, 2);
        } else {
            $checks['messages'][] = 'Fees cleared ✓';
        }
        
        // Check report card finalization
        $stmt = $conn->prepare('
            SELECT finalized FROM tbl_report_cards 
            WHERE student_id = ? 
            ORDER BY id DESC LIMIT 1
        ');
        $stmt->execute([$studentId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $checks['report_finalized'] = $report && $report['finalized'];
        if ($checks['report_finalized']) {
            $checks['messages'][] = 'Report card finalized ✓';
        } else {
            $checks['messages'][] = 'Report card not finalized';
        }
        
        // Overall eligibility
        $checks['eligible_for_promotion'] = $checks['fees_cleared'] && $checks['report_finalized'];
        
    } catch (Throwable $e) {
        $checks['messages'][] = 'Error checking eligibility: ' . $e->getMessage();
    }
    
    return $checks;
}

/**
 * Ensure certificates table has required columns
 */
if (!function_exists('app_ensure_certificates_table')) {
function app_ensure_certificates_table(PDO $conn): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    
    try {
        if (app_table_exists($conn, 'tbl_certificates')) {
            return;
        }
        
        // Create table if it doesn't exist
        if (defined('DBDriver') && DBDriver === 'pgsql') {
            $conn->exec('CREATE TABLE IF NOT EXISTS tbl_certificates (
                id SERIAL PRIMARY KEY,
                student_id INTEGER NOT NULL REFERENCES tbl_students(id) ON DELETE CASCADE,
                class_id INTEGER DEFAULT NULL REFERENCES tbl_classes(id) ON DELETE SET NULL,
                certificate_type VARCHAR(50) NOT NULL,
                certificate_category VARCHAR(50) DEFAULT \'general\',
                title VARCHAR(200) NOT NULL,
                serial_no VARCHAR(100) UNIQUE NOT NULL,
                issue_date DATE NOT NULL,
                status VARCHAR(20) DEFAULT \'issued\',
                notes TEXT DEFAULT NULL,
                verification_code VARCHAR(50) UNIQUE NOT NULL,
                cert_hash VARCHAR(128) DEFAULT NULL,
                issued_by INTEGER DEFAULT NULL REFERENCES tbl_staff(id) ON DELETE SET NULL,
                mean_score DECIMAL(5,2) DEFAULT NULL,
                merit_grade VARCHAR(1) DEFAULT NULL,
                competencies_json TEXT DEFAULT NULL,
                position_in_class INTEGER DEFAULT NULL,
                approved_by INTEGER DEFAULT NULL REFERENCES tbl_staff(id) ON DELETE SET NULL,
                approved_at TIMESTAMP DEFAULT NULL,
                locked BOOLEAN DEFAULT FALSE,
                downloads INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (serial_no),
                UNIQUE (verification_code)
            )');
            $conn->exec('CREATE INDEX IF NOT EXISTS tbl_certificates_student_idx ON tbl_certificates (student_id, issue_date DESC)');
        } else {
            $conn->exec('CREATE TABLE IF NOT EXISTS tbl_certificates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                class_id INT DEFAULT NULL,
                certificate_type VARCHAR(50) NOT NULL,
                certificate_category VARCHAR(50) DEFAULT \'general\',
                title VARCHAR(200) NOT NULL,
                serial_no VARCHAR(100) UNIQUE NOT NULL,
                issue_date DATE NOT NULL,
                status VARCHAR(20) DEFAULT \'issued\',
                notes LONGTEXT DEFAULT NULL,
                verification_code VARCHAR(50) UNIQUE NOT NULL,
                cert_hash VARCHAR(128) DEFAULT NULL,
                issued_by INT DEFAULT NULL,
                mean_score DECIMAL(5,2) DEFAULT NULL,
                merit_grade VARCHAR(1) DEFAULT NULL,
                competencies_json LONGTEXT DEFAULT NULL,
                position_in_class INT DEFAULT NULL,
                approved_by INT DEFAULT NULL,
                approved_at TIMESTAMP DEFAULT NULL,
                locked BOOLEAN DEFAULT FALSE,
                downloads INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
                FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE SET NULL,
                FOREIGN KEY (issued_by) REFERENCES tbl_staff (id) ON DELETE SET NULL,
                KEY tbl_certificates_student_idx (student_id, issue_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    } catch (Throwable $e) {
        // Table already exists or creation failed silently
    }
}
}
