BEGIN;

DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tbl_subjects') THEN
    IF (SELECT COUNT(*) FROM tbl_subjects) = 0 THEN
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
  END IF;
END $$;

COMMIT;
