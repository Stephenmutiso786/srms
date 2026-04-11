BEGIN;

ALTER TABLE tbl_ai_feedback
  ADD COLUMN IF NOT EXISTS subject varchar(120) NULL;

ALTER TABLE tbl_ai_feedback
  ADD COLUMN IF NOT EXISTS status varchar(20) NOT NULL DEFAULT 'open';

ALTER TABLE tbl_ai_feedback
  ADD COLUMN IF NOT EXISTS reply_message text NULL;

ALTER TABLE tbl_ai_feedback
  ADD COLUMN IF NOT EXISTS replied_by integer NULL;

ALTER TABLE tbl_ai_feedback
  ADD COLUMN IF NOT EXISTS replied_at timestamp NULL;

ALTER TABLE tbl_ai_feedback
  ADD COLUMN IF NOT EXISTS intent varchar(50) NULL;

CREATE INDEX IF NOT EXISTS tbl_ai_feedback_category_idx ON tbl_ai_feedback (category);
CREATE INDEX IF NOT EXISTS tbl_ai_feedback_status_idx ON tbl_ai_feedback (status);
CREATE INDEX IF NOT EXISTS tbl_ai_feedback_created_idx ON tbl_ai_feedback (created_at);

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE constraint_name = 'tbl_ai_feedback_replied_by_fk'
      AND table_name = 'tbl_ai_feedback'
  ) THEN
    ALTER TABLE tbl_ai_feedback
      ADD CONSTRAINT tbl_ai_feedback_replied_by_fk
      FOREIGN KEY (replied_by) REFERENCES tbl_staff (id) ON DELETE SET NULL;
  END IF;
END $$;

COMMIT;