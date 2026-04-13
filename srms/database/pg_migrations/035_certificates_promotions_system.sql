-- Migration 035: Enhanced Certificates and Promotion System
-- Adds CBC competencies, merit classification, and promotion workflow
-- Created: 2026-04-12

-- ============================================================================
-- ALTER tbl_certificates: Add new columns for enhanced certificate data
-- ============================================================================

INSERT INTO tbl_audit_logs (actor_type, actor_id, action, entity, entity_id, ip, user_agent)
VALUES ('system', 'migration-035', 'MIGRATION', 'schema', '035_certificates_promotions_system.sql', '', 'Applied migration 035: Enhanced certificates and promotion system')

ALTER TABLE IF EXISTS tbl_certificates ADD COLUMN IF NOT EXISTS merit_grade VARCHAR(1) DEFAULT NULL CHECK (merit_grade IN ('A', 'B', 'C', 'D', 'E'));

ALTER TABLE IF EXISTS tbl_certificates ADD COLUMN IF NOT EXISTS competencies_json TEXT DEFAULT NULL;

ALTER TABLE IF EXISTS tbl_certificates ADD COLUMN IF NOT EXISTS certificate_category VARCHAR(50) DEFAULT 'general' CHECK (certificate_category IN ('primary_completion', 'junior_completion', 'leaving', 'transfer', 'conduct', 'merit', 'general'));

ALTER TABLE IF EXISTS tbl_certificates ADD COLUMN IF NOT EXISTS position_in_class INT DEFAULT NULL;

ALTER TABLE IF EXISTS tbl_certificates ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL REFERENCES tbl_staff(id) ON DELETE SET NULL;

ALTER TABLE IF EXISTS tbl_certificates ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP DEFAULT NULL;

ALTER TABLE IF EXISTS tbl_certificates ADD COLUMN IF NOT EXISTS locked BOOLEAN DEFAULT FALSE;

-- ============================================================================
-- CREATE tbl_promotion_batches: Manage promotion cycles by class/year
-- ============================================================================

CREATE TABLE IF NOT EXISTS tbl_promotion_batches (
    id SERIAL PRIMARY KEY,
    school_id INT DEFAULT NULL REFERENCES tbl_school(id) ON DELETE CASCADE,
    class_id INT NOT NULL REFERENCES tbl_classes(id) ON DELETE CASCADE,
    academic_year VARCHAR(10) NOT NULL,
    promotion_cycle VARCHAR(50) DEFAULT 'year_end',
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled')),
    students_promoted INT DEFAULT 0,
    students_repeated INT DEFAULT 0,
    students_exited INT DEFAULT 0,
    total_fees_balance DECIMAL(10,2) DEFAULT 0,
    created_by INT REFERENCES tbl_staff(id) ON DELETE SET NULL,
    approved_by INT DEFAULT NULL REFERENCES tbl_staff(id) ON DELETE SET NULL,
    approved_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_promotion_batches_class ON tbl_promotion_batches(class_id, academic_year, status);
CREATE INDEX IF NOT EXISTS idx_promotion_batches_status ON tbl_promotion_batches(status, approved_at DESC);

-- ============================================================================
-- CREATE tbl_student_promotions: Individual student promotion records
-- ============================================================================

ALTER TABLE IF EXISTS tbl_student_promotions
    ALTER COLUMN student_id TYPE VARCHAR(20) USING student_id::VARCHAR(20);

CREATE TABLE IF NOT EXISTS tbl_student_promotions (
    id SERIAL PRIMARY KEY,
    batch_id INT NOT NULL REFERENCES tbl_promotion_batches(id) ON DELETE CASCADE,
    student_id VARCHAR(20) NOT NULL REFERENCES tbl_students(id) ON DELETE CASCADE,
    from_class INT NOT NULL REFERENCES tbl_classes(id) ON DELETE CASCADE,
    to_class INT DEFAULT NULL REFERENCES tbl_classes(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'promoted' CHECK (status IN ('promoted', 'repeated', 'exited', 'suspended')),
    mean_score DECIMAL(5,2) DEFAULT NULL,
    merit_grade VARCHAR(1) DEFAULT NULL CHECK (merit_grade IN ('A', 'B', 'C', 'D', 'E')),
    fees_balance DECIMAL(10,2) DEFAULT 0,
    fees_cleared BOOLEAN DEFAULT FALSE,
    report_card_finalized BOOLEAN DEFAULT FALSE,
    certificate_generated BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,
    created_by INT REFERENCES tbl_staff(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_student_promotions_batch ON tbl_student_promotions(batch_id, status);
CREATE INDEX IF NOT EXISTS idx_student_promotions_student ON tbl_student_promotions(student_id, batch_id);
CREATE INDEX IF NOT EXISTS idx_student_promotions_status ON tbl_student_promotions(status, fees_cleared);

-- ============================================================================
-- CREATE tbl_cbc_competencies: Store CBC competency framework
-- ============================================================================

CREATE TABLE IF NOT EXISTS tbl_cbc_competencies (
    id SERIAL PRIMARY KEY,
    school_id INT DEFAULT NULL REFERENCES tbl_school(id) ON DELETE CASCADE,
    competency_name VARCHAR(100) NOT NULL,
    competency_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT DEFAULT NULL,
    strand VARCHAR(50) DEFAULT NULL,
    grade_range VARCHAR(20) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_competency_per_school UNIQUE (school_id, competency_code)
);

-- ============================================================================
-- CREATE tbl_student_competencies: Track student competency achievements
-- ============================================================================

ALTER TABLE IF EXISTS tbl_student_competencies
    ALTER COLUMN student_id TYPE VARCHAR(20) USING student_id::VARCHAR(20);

CREATE TABLE IF NOT EXISTS tbl_student_competencies (
    id SERIAL PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL REFERENCES tbl_students(id) ON DELETE CASCADE,
    competency_id INT NOT NULL REFERENCES tbl_cbc_competencies(id) ON DELETE CASCADE,
    exam_id INT DEFAULT NULL REFERENCES tbl_exams(id) ON DELETE SET NULL,
    achievement_level VARCHAR(20) DEFAULT 'developing' 
        CHECK (achievement_level IN ('developing', 'proficient', 'advanced', 'excellent')),
    score DECIMAL(5,2) DEFAULT NULL,
    teacher_comment TEXT DEFAULT NULL,
    assessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_student_competency UNIQUE (student_id, competency_id, exam_id)
);

CREATE INDEX IF NOT EXISTS idx_student_competencies_student ON tbl_student_competencies(student_id);
CREATE INDEX IF NOT EXISTS idx_student_competencies_competency ON tbl_student_competencies(competency_id);

-- ============================================================================
-- CREATE tbl_promotion_rules: Define promotion criteria per grade
-- ============================================================================

CREATE TABLE IF NOT EXISTS tbl_promotion_rules (
    id SERIAL PRIMARY KEY,
    school_id INT DEFAULT NULL REFERENCES tbl_school(id) ON DELETE CASCADE,
    grade_level INT NOT NULL,
    min_score_for_promotion DECIMAL(5,2) DEFAULT 40.0,
    require_fees_clearance BOOLEAN DEFAULT TRUE,
    require_report_finalization BOOLEAN DEFAULT TRUE,
    require_headteacher_approval BOOLEAN DEFAULT TRUE,
    auto_generate_certificate BOOLEAN DEFAULT TRUE,
    certificate_type VARCHAR(50) DEFAULT 'completion',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_promotion_rule UNIQUE (school_id, grade_level)
);

-- ============================================================================
-- SEED: Default CBC Competencies (Kenya Primary/Secondary)
-- ============================================================================

INSERT INTO tbl_cbc_competencies (school_id, competency_name, competency_code, description, strand, grade_range, status)
SELECT NULL, 'Communication and Collaboration', 'CC-001', 'Ability to communicate effectively and collaborate with others', 'Core Competency', 'G1-G6', 'active'
WHERE NOT EXISTS (SELECT 1 FROM tbl_cbc_competencies WHERE competency_code = 'CC-001');
INSERT INTO tbl_cbc_competencies (school_id, competency_name, competency_code, description, strand, grade_range, status)
SELECT NULL, 'Critical Thinking and Problem Solving', 'CTPS-001', 'Ability to analyze problems and find creative solutions', 'Core Competency', 'G1-G6', 'active'
WHERE NOT EXISTS (SELECT 1 FROM tbl_cbc_competencies WHERE competency_code = 'CTPS-001');
INSERT INTO tbl_cbc_competencies (school_id, competency_name, competency_code, description, strand, grade_range, status)
SELECT NULL, 'Creativity and Imagination', 'CI-001', 'Ability to think creatively and generate new ideas', 'Core Competency', 'G1-G6', 'active'
WHERE NOT EXISTS (SELECT 1 FROM tbl_cbc_competencies WHERE competency_code = 'CI-001');
INSERT INTO tbl_cbc_competencies (school_id, competency_name, competency_code, description, strand, grade_range, status)
SELECT NULL, 'Citizenship and Personal Development', 'CPD-001', 'Understanding of civic responsibilities and personal values', 'Core Competency', 'G1-G6', 'active'
WHERE NOT EXISTS (SELECT 1 FROM tbl_cbc_competencies WHERE competency_code = 'CPD-001');
INSERT INTO tbl_cbc_competencies (school_id, competency_name, competency_code, description, strand, grade_range, status)
SELECT NULL, 'Digital Literacy', 'DL-001', 'Proficiency with digital tools and technologies', 'Core Competency', 'G1-G6', 'active'
WHERE NOT EXISTS (SELECT 1 FROM tbl_cbc_competencies WHERE competency_code = 'DL-001');
INSERT INTO tbl_cbc_competencies (school_id, competency_name, competency_code, description, strand, grade_range, status)
SELECT NULL, 'Learning Outcomes Achievement', 'LOA-001', 'Achievement of subject-specific learning outcomes', 'Academic', 'G1-G6', 'active'
WHERE NOT EXISTS (SELECT 1 FROM tbl_cbc_competencies WHERE competency_code = 'LOA-001');

-- ============================================================================
-- SEED: Default Promotion Rules
-- ============================================================================

INSERT INTO tbl_promotion_rules (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
SELECT NULL, 1, 40.0, TRUE, TRUE, TRUE, FALSE, 'general'
WHERE NOT EXISTS (SELECT 1 FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = 1);
INSERT INTO tbl_promotion_rules (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
SELECT NULL, 2, 40.0, TRUE, TRUE, TRUE, FALSE, 'general'
WHERE NOT EXISTS (SELECT 1 FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = 2);
INSERT INTO tbl_promotion_rules (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
SELECT NULL, 3, 40.0, TRUE, TRUE, TRUE, FALSE, 'general'
WHERE NOT EXISTS (SELECT 1 FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = 3);
INSERT INTO tbl_promotion_rules (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
SELECT NULL, 4, 40.0, TRUE, TRUE, TRUE, FALSE, 'general'
WHERE NOT EXISTS (SELECT 1 FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = 4);
INSERT INTO tbl_promotion_rules (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
SELECT NULL, 5, 40.0, TRUE, TRUE, TRUE, FALSE, 'general'
WHERE NOT EXISTS (SELECT 1 FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = 5);
INSERT INTO tbl_promotion_rules (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
SELECT NULL, 6, 40.0, TRUE, TRUE, TRUE, TRUE, 'primary_completion'
WHERE NOT EXISTS (SELECT 1 FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = 6);
INSERT INTO tbl_promotion_rules (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
SELECT NULL, 7, 40.0, TRUE, TRUE, TRUE, FALSE, 'general'
WHERE NOT EXISTS (SELECT 1 FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = 7);
INSERT INTO tbl_promotion_rules (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
SELECT NULL, 8, 40.0, TRUE, TRUE, TRUE, FALSE, 'general'
WHERE NOT EXISTS (SELECT 1 FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = 8);
INSERT INTO tbl_promotion_rules (school_id, grade_level, min_score_for_promotion, require_fees_clearance, require_report_finalization, require_headteacher_approval, auto_generate_certificate, certificate_type)
SELECT NULL, 9, 40.0, TRUE, TRUE, TRUE, TRUE, 'junior_completion'
WHERE NOT EXISTS (SELECT 1 FROM tbl_promotion_rules WHERE school_id IS NULL AND grade_level = 9);

-- ============================================================================
-- Add audit log for this migration
-- ============================================================================

INSERT INTO tbl_audit_logs (user_id, action, description, affected_table, created_at)
VALUES (NULL, 'MIGRATION', 'Applied migration 035: Enhanced certificates and promotion system', 'tbl_certificates, tbl_promotion_batches, tbl_student_promotions, tbl_cbc_competencies', CURRENT_TIMESTAMP)
ON CONFLICT DO NOTHING;
