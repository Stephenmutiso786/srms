BEGIN;

CREATE TABLE IF NOT EXISTS tbl_exam_subjects (
  exam_id integer NOT NULL,
  subject_id integer NOT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (exam_id, subject_id),
  CONSTRAINT tbl_exam_subjects_exam_fk FOREIGN KEY (exam_id) REFERENCES tbl_exams (id) ON DELETE CASCADE,
  CONSTRAINT tbl_exam_subjects_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS tbl_exam_subjects_subject_idx
  ON tbl_exam_subjects (subject_id);

COMMIT;
