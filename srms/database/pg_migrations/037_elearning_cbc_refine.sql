-- Migration 037: E-learning CBC refinement
-- Adds CBC lesson metadata, offline content flags, and learner progress tracking.

ALTER TABLE IF EXISTS tbl_lessons
    ADD COLUMN IF NOT EXISTS learning_outcome VARCHAR(255) DEFAULT '';

ALTER TABLE IF EXISTS tbl_lessons
    ADD COLUMN IF NOT EXISTS grade_band VARCHAR(30) DEFAULT '';

ALTER TABLE IF EXISTS tbl_lesson_content
    ADD COLUMN IF NOT EXISTS title VARCHAR(150) DEFAULT '';

ALTER TABLE IF EXISTS tbl_lesson_content
    ADD COLUMN IF NOT EXISTS is_offline_available BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS tbl_elearning_progress (
    id SERIAL PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL REFERENCES tbl_students(id) ON DELETE CASCADE,
    course_id INT NOT NULL REFERENCES tbl_courses(id) ON DELETE CASCADE,
    lesson_id INT NULL REFERENCES tbl_lessons(id) ON DELETE SET NULL,
    competency_level VARCHAR(20) NOT NULL DEFAULT 'AE' CHECK (competency_level IN ('EE', 'ME', 'AE', 'BE')),
    completion_pct NUMERIC(5,2) NOT NULL DEFAULT 0,
    score NUMERIC(6,2) NOT NULL DEFAULT 0,
    last_activity_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_elearning_progress_student_course_lesson
    ON tbl_elearning_progress (student_id, course_id, COALESCE(lesson_id, 0));

CREATE INDEX IF NOT EXISTS idx_elearning_progress_student
    ON tbl_elearning_progress (student_id, last_activity_at DESC);

CREATE INDEX IF NOT EXISTS idx_elearning_progress_course
    ON tbl_elearning_progress (course_id, competency_level);

CREATE TABLE IF NOT EXISTS tbl_elearning_badges (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    icon VARCHAR(120) NOT NULL DEFAULT 'bi-award',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tbl_student_badges (
    id SERIAL PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL REFERENCES tbl_students(id) ON DELETE CASCADE,
    badge_id INT NOT NULL REFERENCES tbl_elearning_badges(id) ON DELETE CASCADE,
    earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (student_id, badge_id)
);

INSERT INTO tbl_elearning_badges (code, name, description, icon)
SELECT 'TOP_READER', 'Top Reader', 'Completed at least 10 lesson content items', 'bi-book'
WHERE NOT EXISTS (SELECT 1 FROM tbl_elearning_badges WHERE code = 'TOP_READER');

INSERT INTO tbl_elearning_badges (code, name, description, icon)
SELECT 'MATH_STAR', 'Math Star', 'Scored 80%+ average in mathematics courses', 'bi-stars'
WHERE NOT EXISTS (SELECT 1 FROM tbl_elearning_badges WHERE code = 'MATH_STAR');
