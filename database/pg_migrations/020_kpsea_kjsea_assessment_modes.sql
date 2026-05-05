-- Add SBA Scores Table (School-Based Assessment for Grade 7 & 8)
CREATE TABLE IF NOT EXISTS tbl_sba_scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  grade INT NOT NULL,  -- 7 or 8
  subject_id INT NOT NULL,
  score DECIMAL(5, 2) NOT NULL DEFAULT 0,
  term_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES tbl_students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES tbl_subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (term_id) REFERENCES tbl_terms(id) ON DELETE SET NULL,
  UNIQUE KEY unique_sba (student_id, grade, subject_id, term_id)
);

-- Add KPSEA Results Table
CREATE TABLE IF NOT EXISTS tbl_kpsea_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  score DECIMAL(5, 2) NOT NULL DEFAULT 0,
  grade_awarded VARCHAR(5),
  exam_session_year INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES tbl_students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES tbl_subjects(id) ON DELETE CASCADE,
  UNIQUE KEY unique_kpsea (student_id, subject_id, exam_session_year)
);

-- Add assessment_mode and is_locked to exams table (if not already present)
ALTER TABLE tbl_exams 
ADD COLUMN IF NOT EXISTS assessment_mode VARCHAR(20) DEFAULT 'normal' COMMENT 'normal, cbc, KPSEA, KJSEA, consolidated',
ADD COLUMN IF NOT EXISTS is_locked BOOLEAN DEFAULT FALSE COMMENT 'Prevents editing after submission',
ADD COLUMN IF NOT EXISTS required_sba_grade VARCHAR(10) COMMENT 'Grade(s) required for SBA (e.g., "7,8" for KJSEA)';

-- National Assessment Mode Lookup Table
CREATE TABLE IF NOT EXISTS tbl_assessment_modes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(20) UNIQUE NOT NULL,
  description TEXT,
  required_grade INT,
  requires_sba BOOLEAN DEFAULT FALSE,
  requires_kpsea BOOLEAN DEFAULT FALSE,
  sba_weight DECIMAL(3, 2) DEFAULT 0,
  exam_weight DECIMAL(3, 2) DEFAULT 1,
  is_national BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert assessment modes
INSERT INTO tbl_assessment_modes (name, code, description, required_grade, requires_sba, requires_kpsea, sba_weight, exam_weight, is_national) VALUES
('Normal Exam', 'normal', 'Standard classroom exam', NULL, FALSE, FALSE, 0, 1, FALSE),
('CBC Assessment', 'cbc', 'Competency-Based Assessment', NULL, FALSE, FALSE, 0, 1, FALSE),
('KPSEA', 'KPSEA', 'Kenya Primary School Examination Assessment - Grade 6', 6, FALSE, FALSE, 0, 1, TRUE),
('KJSEA', 'KJSEA', 'Kenya Junior School Examination Assessment - Grade 9', 9, TRUE, TRUE, 0.30, 0.70, TRUE),
('Consolidated', 'consolidated', 'Auto-computed from multiple exams', NULL, FALSE, FALSE, 0, 1, FALSE)
ON DUPLICATE KEY UPDATE id=id;
