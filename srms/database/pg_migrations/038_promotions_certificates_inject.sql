-- Migration 038: Inject and backfill promotion/certificate data
-- Purpose: provide safe defaults and normalize legacy rows for promotion and certificate workflows

DO $$
BEGIN
    IF to_regclass('public.tbl_audit_logs') IS NOT NULL THEN
        BEGIN
            INSERT INTO tbl_audit_logs (actor_type, actor_id, action, entity, entity_id, ip, user_agent)
            VALUES ('system', 'migration-038', 'MIGRATION', 'data', '038_promotions_certificates_inject.sql', '', 'Applied migration 038: Promotions/certificates data inject');
        EXCEPTION
            WHEN undefined_column THEN
                NULL;
            WHEN others THEN
                NULL;
        END;
    END IF;
END $$;

-- --------------------------------------------------------------------------
-- Certificates: normalize legacy rows and add fast lookup indexes
-- --------------------------------------------------------------------------

DO $$
BEGIN
    IF to_regclass('public.tbl_certificates') IS NOT NULL THEN
        BEGIN
            UPDATE tbl_certificates
            SET status = 'issued'
            WHERE status IS NULL;
        EXCEPTION
            WHEN others THEN
                NULL;
        END;

        BEGIN
            UPDATE tbl_certificates
            SET certificate_category = CASE
                WHEN LOWER(COALESCE(certificate_type, '')) IN ('leaving', 'kenya primary school leaving certificate') THEN 'leaving'
                WHEN LOWER(COALESCE(certificate_type, '')) IN ('primary_completion', 'primary completion') THEN 'primary_completion'
                WHEN LOWER(COALESCE(certificate_type, '')) IN ('junior_completion', 'junior completion') THEN 'junior_completion'
                WHEN LOWER(COALESCE(certificate_type, '')) = 'transfer' THEN 'transfer'
                WHEN LOWER(COALESCE(certificate_type, '')) = 'conduct' THEN 'conduct'
                WHEN LOWER(COALESCE(certificate_type, '')) = 'merit' THEN 'merit'
                ELSE 'general'
            END
            WHERE certificate_category IS NULL OR certificate_category = '' OR certificate_category = 'general';
        EXCEPTION
            WHEN others THEN
                NULL;
        END;

        BEGIN
            UPDATE tbl_certificates
            SET approved_at = COALESCE(approved_at, created_at)
            WHERE locked = TRUE AND approved_at IS NULL;
        EXCEPTION
            WHEN others THEN
                NULL;
        END;

        BEGIN
            CREATE INDEX IF NOT EXISTS idx_certificates_type_status
            ON tbl_certificates (certificate_type, status, issue_date DESC);

            CREATE INDEX IF NOT EXISTS idx_certificates_category_date
            ON tbl_certificates (certificate_category, issue_date DESC);
        EXCEPTION
            WHEN others THEN
                NULL;
        END;
    END IF;
END $$;

-- --------------------------------------------------------------------------
-- Promotions: inject/normalize default rules for grade levels 1-9
-- --------------------------------------------------------------------------

DO $$
BEGIN
    IF to_regclass('public.tbl_promotion_rules') IS NOT NULL THEN
        BEGIN
            WITH default_rules AS (
                SELECT *
                FROM (VALUES
                    (1, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (2, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (3, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (4, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (5, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (6, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, TRUE, 'primary_completion'),
                    (7, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (8, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (9, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, TRUE, 'junior_completion')
                ) AS t(
                    grade_level,
                    min_score_for_promotion,
                    require_fees_clearance,
                    require_report_finalization,
                    require_headteacher_approval,
                    auto_generate_certificate,
                    certificate_type
                )
            )
            UPDATE tbl_promotion_rules pr
            SET min_score_for_promotion = dr.min_score_for_promotion,
                require_fees_clearance = dr.require_fees_clearance,
                require_report_finalization = dr.require_report_finalization,
                require_headteacher_approval = dr.require_headteacher_approval,
                auto_generate_certificate = dr.auto_generate_certificate,
                certificate_type = dr.certificate_type,
                updated_at = CURRENT_TIMESTAMP
            FROM default_rules dr
            WHERE pr.school_id IS NULL
              AND pr.grade_level = dr.grade_level;
        EXCEPTION
            WHEN others THEN
                NULL;
        END;

        BEGIN
            WITH default_rules AS (
                SELECT *
                FROM (VALUES
                    (1, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (2, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (3, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (4, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (5, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (6, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, TRUE, 'primary_completion'),
                    (7, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (8, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, FALSE, 'general'),
                    (9, 40.0::DECIMAL(5,2), TRUE, TRUE, TRUE, TRUE, 'junior_completion')
                ) AS t(
                    grade_level,
                    min_score_for_promotion,
                    require_fees_clearance,
                    require_report_finalization,
                    require_headteacher_approval,
                    auto_generate_certificate,
                    certificate_type
                )
            )
            INSERT INTO tbl_promotion_rules (
                school_id,
                grade_level,
                min_score_for_promotion,
                require_fees_clearance,
                require_report_finalization,
                require_headteacher_approval,
                auto_generate_certificate,
                certificate_type
            )
            SELECT
                NULL,
                dr.grade_level,
                dr.min_score_for_promotion,
                dr.require_fees_clearance,
                dr.require_report_finalization,
                dr.require_headteacher_approval,
                dr.auto_generate_certificate,
                dr.certificate_type
            FROM default_rules dr
            WHERE NOT EXISTS (
                SELECT 1
                FROM tbl_promotion_rules pr
                WHERE pr.school_id IS NULL
                  AND pr.grade_level = dr.grade_level
            );
        EXCEPTION
            WHEN others THEN
                NULL;
        END;
    END IF;

    IF to_regclass('public.tbl_student_promotions') IS NOT NULL THEN
        BEGIN
            UPDATE tbl_student_promotions
            SET fees_cleared = TRUE,
                updated_at = CURRENT_TIMESTAMP
            WHERE COALESCE(fees_balance, 0) <= 0
              AND COALESCE(fees_cleared, FALSE) = FALSE;
        EXCEPTION
            WHEN others THEN
                NULL;
        END;
    END IF;
END $$;
