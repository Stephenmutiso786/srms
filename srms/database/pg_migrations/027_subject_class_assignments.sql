BEGIN;

CREATE TABLE IF NOT EXISTS tbl_subject_class_assignments (
  subject_id integer NOT NULL,
  class_id integer NOT NULL,
  created_by integer NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (subject_id, class_id),
  CONSTRAINT tbl_subject_class_assignments_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE,
  CONSTRAINT tbl_subject_class_assignments_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_subject_class_assignments_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS tbl_subject_class_assignments_subject_idx
  ON tbl_subject_class_assignments (subject_id);

CREATE INDEX IF NOT EXISTS tbl_subject_class_assignments_class_idx
  ON tbl_subject_class_assignments (class_id);

COMMIT;
