<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== '1' || !in_array((int)$level, [0, 9], true)) {
    app_reply_redirect('danger', 'Only admin can update promotion rules.', '../promotion_rules');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_reply_redirect('danger', 'Invalid request method.', '../promotion_rules');
}

$gradeLevels = $_POST['grade_level'] ?? [];
$minScores = $_POST['min_score_for_promotion'] ?? [];
$certificateTypes = $_POST['certificate_type'] ?? [];

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_promotion_workflow_schema($conn);
    $conn->beginTransaction();

    for ($index = 0; $index < count($gradeLevels); $index++) {
        $gradeLevel = (int)($gradeLevels[$index] ?? 0);
        if ($gradeLevel < 1) {
            continue;
        }

        $minScore = (float)($minScores[$index] ?? 40);
        $certificateType = trim((string)($certificateTypes[$index] ?? 'general'));
        if ($certificateType === '') {
            $certificateType = 'general';
        }

        $requireFees = isset($_POST['require_fees_clearance'][$gradeLevel]) ? 1 : 0;
        $requireReport = isset($_POST['require_report_finalization'][$gradeLevel]) ? 1 : 0;
        $requireHeadteacher = isset($_POST['require_headteacher_approval'][$gradeLevel]) ? 1 : 0;
        $autoCertificate = isset($_POST['auto_generate_certificate'][$gradeLevel]) ? 1 : 0;

        $stmt = $conn->prepare('SELECT id FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = ? LIMIT 1');
        $stmt->execute([$gradeLevel]);
        $ruleId = (int)($stmt->fetchColumn() ?: 0);

        if ($ruleId > 0) {
            $stmt = $conn->prepare('UPDATE tbl_promotion_rules
                SET min_score_for_promotion = ?, require_fees_clearance = ?, require_report_finalization = ?, require_headteacher_approval = ?, auto_generate_certificate = ?, certificate_type = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?');
            $stmt->execute([$minScore, $requireFees, $requireReport, $requireHeadteacher, $autoCertificate, $certificateType, $ruleId]);
        } else {
            $stmt = $conn->prepare('INSERT INTO tbl_promotion_rules
                (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$gradeLevel, $minScore, $requireFees, $requireReport, $requireHeadteacher, $autoCertificate, $certificateType]);
        }
    }

    app_audit_log(
        $conn,
        'staff',
        (string)$account_id,
        'promotion.rules.update',
        'tbl_promotion_rules',
        '',
        ['grades_updated' => count($gradeLevels)]
    );

    $conn->commit();
    app_reply_redirect('success', 'Promotion rules updated successfully.', '../promotion_rules');
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Promotion rule update error: ' . $e->getMessage());
    app_reply_redirect('danger', 'Failed to save promotion rules: ' . $e->getMessage(), '../promotion_rules');
}
