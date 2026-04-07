BEGIN;

ALTER TABLE IF EXISTS tbl_exam_results
  ADD COLUMN IF NOT EXISTS exam_id integer NULL;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'tbl_exam_results_exam_fk'
  ) THEN
    ALTER TABLE tbl_exam_results
      ADD CONSTRAINT tbl_exam_results_exam_fk
      FOREIGN KEY (exam_id) REFERENCES tbl_exams (id) ON DELETE SET NULL;
  END IF;
END $$;

CREATE INDEX IF NOT EXISTS tbl_exam_results_exam_idx ON tbl_exam_results (exam_id);

COMMIT;
