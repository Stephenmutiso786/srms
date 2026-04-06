BEGIN;

-- Add parent sessions support
ALTER TABLE tbl_login_sessions
  ADD COLUMN IF NOT EXISTS parent integer NULL;

-- Foreign key for parent sessions
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE constraint_name = 'tbl_login_sessions_parent_fk'
  ) THEN
    ALTER TABLE tbl_login_sessions
      ADD CONSTRAINT tbl_login_sessions_parent_fk
      FOREIGN KEY (parent) REFERENCES tbl_parents (id) ON DELETE CASCADE;
  END IF;
END $$;

COMMIT;

