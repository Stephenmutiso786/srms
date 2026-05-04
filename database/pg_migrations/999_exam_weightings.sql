-- Migration: 999 - Exam weightings scaffold (KPSEA/KJSEA support)
CREATE TABLE IF NOT EXISTS tbl_exam_weightings (
  id SERIAL PRIMARY KEY,
  exam_key VARCHAR(100) NOT NULL UNIQUE,
  display_name VARCHAR(255) NOT NULL,
  weight_percent INTEGER NOT NULL DEFAULT 100,
  created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT now()
);

-- per-exam weight table (used by the application). Keep separate from preset weightings.
CREATE TABLE IF NOT EXISTS tbl_exam_weights (
  exam_id INTEGER NOT NULL,
  weight_percentage NUMERIC(6,2) NOT NULL DEFAULT 100,
  created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT now(),
  PRIMARY KEY (exam_id),
  CONSTRAINT tbl_exam_weights_exam_fk FOREIGN KEY (exam_id) REFERENCES tbl_exams (id) ON DELETE CASCADE
);

-- seed common national exam weights (optional) into the presets table
INSERT INTO tbl_exam_weightings (exam_key, display_name, weight_percent) VALUES
('kpsea', 'KPSEA (Grade 6)', 20) ON CONFLICT (exam_key) DO NOTHING;
INSERT INTO tbl_exam_weightings (exam_key, display_name, weight_percent) VALUES
('national_mid', 'National Exams (Grade 7-8)', 20) ON CONFLICT (exam_key) DO NOTHING;
INSERT INTO tbl_exam_weightings (exam_key, display_name, weight_percent) VALUES
('kjsea', 'KJSEA (Grade 9)', 60) ON CONFLICT (exam_key) DO NOTHING;
