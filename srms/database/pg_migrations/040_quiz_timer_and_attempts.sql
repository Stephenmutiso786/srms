BEGIN;

ALTER TABLE IF EXISTS tbl_quizzes
  ADD COLUMN IF NOT EXISTS duration_minutes INTEGER NOT NULL DEFAULT 0;

ALTER TABLE IF EXISTS tbl_quizzes
  ADD COLUMN IF NOT EXISTS randomize_questions BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE IF EXISTS tbl_quizzes
  ADD COLUMN IF NOT EXISTS max_attempts INTEGER NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS tbl_quiz_attempts (
  id SERIAL PRIMARY KEY,
  quiz_id INTEGER NOT NULL REFERENCES tbl_quizzes(id) ON DELETE CASCADE,
  student_id VARCHAR(20) NOT NULL REFERENCES tbl_students(id) ON DELETE CASCADE,
  status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at TIMESTAMP NULL,
  auto_submitted BOOLEAN NOT NULL DEFAULT FALSE,
  score NUMERIC(6,2) NOT NULL DEFAULT 0,
  answers_json TEXT NOT NULL DEFAULT '{}',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_tbl_quiz_attempts_status CHECK (status IN ('in_progress', 'submitted', 'graded'))
);

CREATE INDEX IF NOT EXISTS idx_tbl_quiz_attempts_quiz_student
  ON tbl_quiz_attempts (quiz_id, student_id, started_at DESC);

COMMIT;
