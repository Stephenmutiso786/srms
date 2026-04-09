BEGIN;

ALTER TABLE IF EXISTS tbl_students
  ADD COLUMN IF NOT EXISTS school_id varchar(30);

ALTER TABLE IF EXISTS tbl_staff
  ADD COLUMN IF NOT EXISTS school_id varchar(30);

DO $$
DECLARE
  current_year text := to_char(CURRENT_DATE, 'YYYY');
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tbl_students' AND column_name = 'school_id') THEN
    WITH cte AS (
      SELECT id, row_number() OVER (ORDER BY id) AS rn
      FROM tbl_students
      WHERE school_id IS NULL OR school_id = ''
    )
    UPDATE tbl_students s
    SET school_id = 'STD-' || current_year || '-' || lpad(cte.rn::text, 4, '0')
    FROM cte
    WHERE s.id = cte.id;
  END IF;

  IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tbl_staff' AND column_name = 'school_id') THEN
    WITH cte AS (
      SELECT id, level,
        CASE
          WHEN level IN (0,1) THEN 'ADM'
          WHEN level = 2 THEN 'TCH'
          WHEN level = 5 THEN 'ACC'
          ELSE 'STF'
        END AS prefix,
        row_number() OVER (
          PARTITION BY CASE
            WHEN level IN (0,1) THEN 'ADM'
            WHEN level = 2 THEN 'TCH'
            WHEN level = 5 THEN 'ACC'
            ELSE 'STF'
          END
          ORDER BY id
        ) AS rn
      FROM tbl_staff
      WHERE school_id IS NULL OR school_id = ''
    )
    UPDATE tbl_staff s
    SET school_id = cte.prefix || '-' || current_year || '-' || lpad(cte.rn::text, 4, '0')
    FROM cte
    WHERE s.id = cte.id;
  END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS tbl_students_school_id_uq ON tbl_students (school_id);
CREATE UNIQUE INDEX IF NOT EXISTS tbl_staff_school_id_uq ON tbl_staff (school_id);

COMMIT;
