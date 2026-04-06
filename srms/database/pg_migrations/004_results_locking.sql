BEGIN;

CREATE TABLE IF NOT EXISTS tbl_results_locks (
  class_id integer NOT NULL,
  term_id integer NOT NULL,
  locked integer NOT NULL DEFAULT 1, -- 1 locked, 0 unlocked
  reason varchar(255) NOT NULL DEFAULT '',
  locked_by integer NULL,
  locked_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (class_id, term_id),
  CONSTRAINT tbl_results_locks_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_results_locks_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE CASCADE,
  CONSTRAINT tbl_results_locks_staff_fk FOREIGN KEY (locked_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
);

COMMIT;

