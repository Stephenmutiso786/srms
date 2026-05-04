-- Migration 038: Allow NULL in tbl_report_card_subjects.score and add has_score flag
-- This migration applies to both PostgreSQL and MySQL where applicable.

-- POSTGRESQL block
-- Add has_score column, populate it, set score NULL where appropriate, then drop NOT NULL
BEGIN;
ALTER TABLE tbl_report_card_subjects ADD COLUMN IF NOT EXISTS has_score BOOLEAN DEFAULT TRUE;
-- For legacy rows where score = 0 and grade is empty, mark as not scored (adjust as needed)
UPDATE tbl_report_card_subjects SET has_score = FALSE WHERE (score = 0 OR score IS NULL) AND (grade = '' OR grade IS NULL);
-- Set score to NULL for rows that are not scored
UPDATE tbl_report_card_subjects SET score = NULL WHERE has_score = FALSE;
-- Make score nullable
ALTER TABLE tbl_report_card_subjects ALTER COLUMN score DROP NOT NULL;
-- Remove default if present
ALTER TABLE tbl_report_card_subjects ALTER COLUMN score DROP DEFAULT;
COMMIT;

-- MYSQL block (compatible with MySQL 5.7+ / MariaDB)
-- Add has_score column
ALTER TABLE tbl_report_card_subjects ADD COLUMN IF NOT EXISTS has_score TINYINT(1) DEFAULT 1;
-- Populate has_score from existing score/grade
UPDATE tbl_report_card_subjects SET has_score = 0 WHERE (score = 0 OR score IS NULL) AND (grade = '' OR grade IS NULL);
-- Set score to NULL where not scored
UPDATE tbl_report_card_subjects SET score = NULL WHERE has_score = 0;
-- Make score nullable and drop default
ALTER TABLE tbl_report_card_subjects MODIFY COLUMN score DECIMAL(6,2) NULL;
ALTER TABLE tbl_report_card_subjects ALTER COLUMN score DROP DEFAULT; -- may be ignored on some MySQL versions

-- NOTE: Back up your DB before running migrations. Review conditions for "not scored" to match your data semantics.
