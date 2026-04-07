BEGIN;

DO $$
DECLARE
  subject_count integer := 0;
  combo_count integer := 0;
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tbl_subjects') THEN
    SELECT COUNT(*) INTO subject_count FROM tbl_subjects;
  END IF;

  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tbl_subject_combinations') THEN
    SELECT COUNT(*) INTO combo_count FROM tbl_subject_combinations;
  END IF;

  IF subject_count > 0 AND combo_count = 0 THEN
    DELETE FROM tbl_subjects;
    INSERT INTO tbl_subjects (name) VALUES
    ('Mathematics'),
    ('English'),
    ('Kiswahili'),
    ('Kenya Sign Language'),
    ('Science and Technology'),
    ('Agriculture and Nutrition'),
    ('Social Studies'),
    ('Creative Arts'),
    ('Physical and Health Education'),
    ('Religious Education'),
    ('Life Skills'),
    ('Pre-Technical and Pre-Career Education'),
    ('Integrated Science'),
    ('Health Education'),
    ('Creative Arts and Sports'),
    ('Environmental Activities'),
    ('Hygiene and Nutrition Activities'),
    ('Language Activities'),
    ('Psychomotor and Creative Activities'),
    ('Movement and Creative Activities'),
    ('Arabic'),
    ('French'),
    ('German'),
    ('Mandarin');
  END IF;
END $$;

COMMIT;
