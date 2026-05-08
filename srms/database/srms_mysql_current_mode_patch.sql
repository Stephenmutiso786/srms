CREATE TABLE IF NOT EXISTS tbl_app_settings (
  id int NOT NULL AUTO_INCREMENT,
  setting_key varchar(100) NOT NULL,
  setting_value longtext NULL,
  updated_by int NULL,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_app_settings_key_uq (setting_key),
  CONSTRAINT tbl_app_settings_staff_fk FOREIGN KEY (updated_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_result_settings (
  id int NOT NULL AUTO_INCREMENT,
  best_of int NOT NULL DEFAULT 0,
  use_weights tinyint(1) NOT NULL DEFAULT 1,
  require_fees_clear tinyint(1) NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_result_settings (best_of, use_weights, require_fees_clear)
SELECT 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM tbl_result_settings);

CREATE TABLE IF NOT EXISTS tbl_subject_weights (
  subject_id int NOT NULL,
  weight decimal(6,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (subject_id),
  CONSTRAINT tbl_subject_weights_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_cbe_grading (
  id int NOT NULL AUTO_INCREMENT,
  level varchar(20) NOT NULL,
  min_mark decimal(6,2) NOT NULL DEFAULT 0,
  max_mark decimal(6,2) NOT NULL DEFAULT 100,
  points int NOT NULL DEFAULT 0,
  sort_order int NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_cbe_grading (level, min_mark, max_mark, points, sort_order, active)
SELECT 'EE', 90, 100, 4, 1, 1 WHERE NOT EXISTS (SELECT 1 FROM tbl_cbe_grading);
INSERT INTO tbl_cbe_grading (level, min_mark, max_mark, points, sort_order, active)
SELECT 'ME', 75, 89.99, 3, 2, 1 WHERE NOT EXISTS (SELECT 1 FROM tbl_cbe_grading WHERE level = 'ME');
INSERT INTO tbl_cbe_grading (level, min_mark, max_mark, points, sort_order, active)
SELECT 'AE', 50, 74.99, 2, 3, 1 WHERE NOT EXISTS (SELECT 1 FROM tbl_cbe_grading WHERE level = 'AE');
INSERT INTO tbl_cbe_grading (level, min_mark, max_mark, points, sort_order, active)
SELECT 'BE', 0, 49.99, 1, 4, 1 WHERE NOT EXISTS (SELECT 1 FROM tbl_cbe_grading WHERE level = 'BE');

CREATE TABLE IF NOT EXISTS tbl_report_cards (
  id int NOT NULL AUTO_INCREMENT,
  student_id varchar(20) NOT NULL,
  class_id int NOT NULL,
  term_id int NOT NULL,
  total decimal(12,2) NOT NULL DEFAULT 0,
  mean decimal(6,2) NOT NULL DEFAULT 0,
  grade varchar(20) NOT NULL DEFAULT '',
  remark varchar(120) NOT NULL DEFAULT '',
  position int NOT NULL DEFAULT 0,
  total_students int NOT NULL DEFAULT 0,
  trend varchar(20) NOT NULL DEFAULT 'New',
  verification_code varchar(60) NOT NULL,
  report_hash varchar(120) NOT NULL DEFAULT '',
  generated_by int NULL,
  generated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  downloads int NOT NULL DEFAULT 0,
  finalized tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_report_cards_unique (student_id, term_id),
  UNIQUE KEY tbl_report_cards_code_uq (verification_code),
  CONSTRAINT tbl_report_cards_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
  CONSTRAINT tbl_report_cards_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_report_cards_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE CASCADE,
  CONSTRAINT tbl_report_cards_staff_fk FOREIGN KEY (generated_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_report_card_subjects (
  report_id int NOT NULL,
  subject_id int NOT NULL,
  score decimal(6,2) NOT NULL DEFAULT 0,
  grade varchar(20) NOT NULL DEFAULT '',
  weight decimal(6,2) NOT NULL DEFAULT 1,
  teacher_id int NULL,
  PRIMARY KEY (report_id, subject_id),
  CONSTRAINT tbl_report_card_subjects_report_fk FOREIGN KEY (report_id) REFERENCES tbl_report_cards (id) ON DELETE CASCADE,
  CONSTRAINT tbl_report_card_subjects_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE,
  CONSTRAINT tbl_report_card_subjects_teacher_fk FOREIGN KEY (teacher_id) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_report_publication (
  id int NOT NULL AUTO_INCREMENT,
  class_id int NOT NULL,
  term_id int NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'published',
  published_by int NULL,
  published_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_report_publication_uq (class_id, term_id),
  CONSTRAINT tbl_report_publication_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_report_publication_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE CASCADE,
  CONSTRAINT tbl_report_publication_staff_fk FOREIGN KEY (published_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_module_locks (
  module varchar(60) NOT NULL,
  locked tinyint(1) NOT NULL DEFAULT 0,
  reason varchar(255) NOT NULL DEFAULT '',
  locked_by int NULL,
  locked_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (module),
  CONSTRAINT tbl_module_locks_staff_fk FOREIGN KEY (locked_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_sms_settings (
  id int NOT NULL AUTO_INCREMENT,
  provider varchar(50) NOT NULL DEFAULT 'custom',
  api_url varchar(255) NOT NULL DEFAULT '',
  api_key varchar(255) NOT NULL DEFAULT '',
  sender_id varchar(60) NOT NULL DEFAULT '',
  status tinyint(1) NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_sms_settings (provider, api_url, api_key, sender_id, status)
SELECT 'custom', '', '', '', 0
WHERE NOT EXISTS (SELECT 1 FROM tbl_sms_settings);

CREATE TABLE IF NOT EXISTS tbl_sms_logs (
  id int NOT NULL AUTO_INCREMENT,
  recipient varchar(60) NOT NULL,
  message text NOT NULL,
  status varchar(30) NOT NULL DEFAULT 'pending',
  provider varchar(60) NOT NULL DEFAULT 'custom',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_sms_topup_requests (
  id int NOT NULL AUTO_INCREMENT,
  wallet_id int NOT NULL DEFAULT 1,
  phone varchar(30) NOT NULL,
  tokens int NOT NULL DEFAULT 0,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  status varchar(30) NOT NULL DEFAULT 'pending',
  checkout_request_id varchar(120) NOT NULL DEFAULT '',
  merchant_request_id varchar(120) NOT NULL DEFAULT '',
  mpesa_receipt varchar(120) NOT NULL DEFAULT '',
  customer_message varchar(255) NOT NULL DEFAULT '',
  result_code varchar(30) NOT NULL DEFAULT '',
  result_desc varchar(255) NOT NULL DEFAULT '',
  raw_callback longtext NULL,
  created_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_sms_topup_checkout_uq (checkout_request_id),
  CONSTRAINT tbl_sms_topup_wallet_fk FOREIGN KEY (wallet_id) REFERENCES tbl_sms_wallets (id) ON DELETE CASCADE,
  CONSTRAINT tbl_sms_topup_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_payment_settings (
  id int NOT NULL,
  environment varchar(20) NOT NULL DEFAULT 'sandbox',
  shortcode varchar(30) NOT NULL DEFAULT '',
  passkey varchar(255) NOT NULL DEFAULT '',
  consumer_key varchar(255) NOT NULL DEFAULT '',
  consumer_secret varchar(255) NOT NULL DEFAULT '',
  callback_url varchar(255) NOT NULL DEFAULT '',
  enabled tinyint(1) NOT NULL DEFAULT 0,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_payment_settings (id, environment, shortcode, passkey, consumer_key, consumer_secret, callback_url, enabled)
SELECT 1, 'sandbox', '', '', '', '', '', 0
WHERE NOT EXISTS (SELECT 1 FROM tbl_payment_settings WHERE id = 1);

CREATE TABLE IF NOT EXISTS tbl_mpesa_stk_requests (
  id int NOT NULL AUTO_INCREMENT,
  invoice_id int NULL,
  phone varchar(30) NOT NULL DEFAULT '',
  amount decimal(12,2) NOT NULL DEFAULT 0,
  account_reference varchar(120) NOT NULL DEFAULT '',
  checkout_request_id varchar(120) NOT NULL DEFAULT '',
  merchant_request_id varchar(120) NOT NULL DEFAULT '',
  status varchar(30) NOT NULL DEFAULT 'pending',
  result_code varchar(30) NOT NULL DEFAULT '',
  result_desc varchar(255) NOT NULL DEFAULT '',
  mpesa_receipt varchar(120) NOT NULL DEFAULT '',
  raw_callback longtext NULL,
  purpose varchar(30) NOT NULL DEFAULT 'invoice',
  target_ref varchar(120) NOT NULL DEFAULT '',
  notes text NULL,
  created_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_mpesa_stk_checkout_uq (checkout_request_id),
  CONSTRAINT tbl_mpesa_stk_invoice_fk FOREIGN KEY (invoice_id) REFERENCES tbl_invoices (id) ON DELETE CASCADE,
  CONSTRAINT tbl_mpesa_stk_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_messages (
  id int NOT NULL AUTO_INCREMENT,
  sender_type varchar(20) NOT NULL,
  sender_id varchar(64) NOT NULL,
  recipient_type varchar(20) NOT NULL,
  recipient_id varchar(64) NOT NULL,
  subject varchar(180) NOT NULL DEFAULT '',
  body text NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'sent',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_email_logs (
  id int NOT NULL AUTO_INCREMENT,
  recipient varchar(120) NOT NULL,
  subject varchar(255) NOT NULL DEFAULT '',
  message longtext NOT NULL,
  status varchar(30) NOT NULL DEFAULT 'queued',
  provider varchar(60) NOT NULL DEFAULT 'smtp',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_import_logs (
  id int NOT NULL AUTO_INCREMENT,
  import_type varchar(60) NOT NULL,
  total int NOT NULL DEFAULT 0,
  success int NOT NULL DEFAULT 0,
  failed int NOT NULL DEFAULT 0,
  details longtext NULL,
  created_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_import_logs_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_validation_issues (
  id int NOT NULL AUTO_INCREMENT,
  issue_type varchar(60) NOT NULL,
  severity varchar(20) NOT NULL DEFAULT 'warning',
  message varchar(255) NOT NULL,
  student_id varchar(20) NULL,
  class_id int NULL,
  term_id int NULL,
  subject_id int NULL,
  status varchar(20) NOT NULL DEFAULT 'new',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_insights_alerts (
  id int NOT NULL AUTO_INCREMENT,
  alert_type varchar(60) NOT NULL,
  severity varchar(20) NOT NULL DEFAULT 'info',
  title varchar(180) NOT NULL,
  message text NOT NULL,
  student_id varchar(20) NULL,
  class_id int NULL,
  term_id int NULL,
  subject_id int NULL,
  status varchar(20) NOT NULL DEFAULT 'new',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_exam_schedule (
  id int NOT NULL AUTO_INCREMENT,
  term_id int NOT NULL,
  class_id int NOT NULL,
  subject_combination_id int NOT NULL,
  subject_id int NULL,
  exam_date date NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  room varchar(120) NOT NULL DEFAULT '',
  invigilator varchar(120) NOT NULL DEFAULT '',
  created_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_exam_schedule_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE CASCADE,
  CONSTRAINT tbl_exam_schedule_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_exam_schedule_subjectcomb_fk FOREIGN KEY (subject_combination_id) REFERENCES tbl_subject_combinations (id) ON DELETE CASCADE,
  CONSTRAINT tbl_exam_schedule_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE SET NULL,
  CONSTRAINT tbl_exam_schedule_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_fees_charged (
  id int NOT NULL AUTO_INCREMENT,
  student_id varchar(20) NOT NULL,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  description varchar(255) NOT NULL DEFAULT '',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_fees_charged_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_fees_paid (
  id int NOT NULL AUTO_INCREMENT,
  student_id varchar(20) NOT NULL,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  reference_no varchar(120) NOT NULL DEFAULT '',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_fees_paid_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_routes (
  id int NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL,
  vehicle_id int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_route_stops (
  id int NOT NULL AUTO_INCREMENT,
  route_id int NOT NULL,
  stop_name varchar(120) NOT NULL,
  stop_order int NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_route_stops_route_fk FOREIGN KEY (route_id) REFERENCES tbl_routes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_vehicles (
  id int NOT NULL AUTO_INCREMENT,
  plate_number varchar(60) NOT NULL,
  model varchar(120) NOT NULL DEFAULT '',
  capacity int NOT NULL DEFAULT 0,
  driver_id int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_vehicles_plate_uq (plate_number),
  CONSTRAINT tbl_vehicles_driver_fk FOREIGN KEY (driver_id) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_transport_assignments (
  id int NOT NULL AUTO_INCREMENT,
  student_id varchar(20) NOT NULL,
  route_id int NOT NULL,
  stop_id int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_transport_assignments_student_uq (student_id),
  CONSTRAINT tbl_transport_assignments_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
  CONSTRAINT tbl_transport_assignments_route_fk FOREIGN KEY (route_id) REFERENCES tbl_routes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_transport_assignments_stop_fk FOREIGN KEY (stop_id) REFERENCES tbl_route_stops (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_library_books (
  id int NOT NULL AUTO_INCREMENT,
  isbn varchar(60) NOT NULL DEFAULT '',
  title varchar(180) NOT NULL,
  author varchar(180) NOT NULL DEFAULT '',
  category varchar(120) NOT NULL DEFAULT '',
  copies int NOT NULL DEFAULT 1,
  available int NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_library_loans (
  id int NOT NULL AUTO_INCREMENT,
  book_id int NOT NULL,
  borrower_type varchar(20) NOT NULL,
  borrower_id varchar(64) NOT NULL,
  issued_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_at datetime NULL,
  returned_at datetime NULL,
  PRIMARY KEY (id),
  CONSTRAINT tbl_library_loans_book_fk FOREIGN KEY (book_id) REFERENCES tbl_library_books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_courses (
  id int NOT NULL AUTO_INCREMENT,
  name varchar(180) NOT NULL,
  class_id int NOT NULL,
  subject_id int NOT NULL,
  teacher_id int NOT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_courses_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_courses_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE,
  CONSTRAINT tbl_courses_teacher_fk FOREIGN KEY (teacher_id) REFERENCES tbl_staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_lessons (
  id int NOT NULL AUTO_INCREMENT,
  course_id int NOT NULL,
  title varchar(180) NOT NULL,
  strand varchar(180) NOT NULL DEFAULT '',
  sub_strand varchar(180) NOT NULL DEFAULT '',
  learning_outcome text NULL,
  grade_band varchar(80) NOT NULL DEFAULT '',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_lessons_course_fk FOREIGN KEY (course_id) REFERENCES tbl_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_lesson_content (
  id int NOT NULL AUTO_INCREMENT,
  lesson_id int NOT NULL,
  title varchar(180) NOT NULL DEFAULT '',
  content_type varchar(40) NOT NULL DEFAULT 'file',
  file_path varchar(255) NOT NULL DEFAULT '',
  content_body longtext NULL,
  is_offline_available tinyint(1) NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_lesson_content_lesson_fk FOREIGN KEY (lesson_id) REFERENCES tbl_lessons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_assignments (
  id int NOT NULL AUTO_INCREMENT,
  course_id int NOT NULL,
  title varchar(180) NOT NULL,
  instructions longtext NULL,
  due_date datetime NULL,
  attachment varchar(255) NOT NULL DEFAULT '',
  created_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_assignments_course_fk FOREIGN KEY (course_id) REFERENCES tbl_courses (id) ON DELETE CASCADE,
  CONSTRAINT tbl_assignments_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_assignment_submissions (
  id int NOT NULL AUTO_INCREMENT,
  assignment_id int NOT NULL,
  student_id varchar(20) NOT NULL,
  submission_text longtext NULL,
  file_path varchar(255) NOT NULL DEFAULT '',
  score decimal(6,2) NULL,
  feedback longtext NULL,
  submitted_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_assignment_submissions_uq (assignment_id, student_id),
  CONSTRAINT tbl_assignment_submissions_assignment_fk FOREIGN KEY (assignment_id) REFERENCES tbl_assignments (id) ON DELETE CASCADE,
  CONSTRAINT tbl_assignment_submissions_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_live_classes (
  id int NOT NULL AUTO_INCREMENT,
  course_id int NOT NULL,
  title varchar(180) NOT NULL,
  platform varchar(60) NOT NULL DEFAULT 'Google Meet',
  meeting_link varchar(255) NOT NULL DEFAULT '',
  start_time datetime NOT NULL,
  end_time datetime NULL,
  status varchar(30) NOT NULL DEFAULT 'scheduled',
  started_at datetime NULL,
  ended_at datetime NULL,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_live_classes_course_fk FOREIGN KEY (course_id) REFERENCES tbl_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_quizzes (
  id int NOT NULL AUTO_INCREMENT,
  course_id int NOT NULL,
  title varchar(180) NOT NULL,
  duration_minutes int NOT NULL DEFAULT 0,
  randomize_questions tinyint(1) NOT NULL DEFAULT 0,
  max_attempts int NOT NULL DEFAULT 1,
  created_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_quizzes_course_fk FOREIGN KEY (course_id) REFERENCES tbl_courses (id) ON DELETE CASCADE,
  CONSTRAINT tbl_quizzes_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_quiz_questions (
  id int NOT NULL AUTO_INCREMENT,
  quiz_id int NOT NULL,
  question longtext NOT NULL,
  qtype varchar(30) NOT NULL DEFAULT 'multiple_choice',
  options longtext NULL,
  correct_answer longtext NULL,
  marks decimal(6,2) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT tbl_quiz_questions_quiz_fk FOREIGN KEY (quiz_id) REFERENCES tbl_quizzes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_quiz_results (
  id int NOT NULL AUTO_INCREMENT,
  quiz_id int NOT NULL,
  student_id varchar(20) NOT NULL,
  score decimal(6,2) NOT NULL DEFAULT 0,
  submitted_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_quiz_results_uq (quiz_id, student_id),
  CONSTRAINT tbl_quiz_results_quiz_fk FOREIGN KEY (quiz_id) REFERENCES tbl_quizzes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_quiz_results_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_elearning_progress (
  id int NOT NULL AUTO_INCREMENT,
  student_id varchar(20) NOT NULL,
  course_id int NOT NULL,
  lesson_id int NULL,
  competency_level varchar(20) NOT NULL DEFAULT '',
  completion_pct decimal(6,2) NOT NULL DEFAULT 0,
  score decimal(6,2) NOT NULL DEFAULT 0,
  last_activity_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_elearning_progress_uq (student_id, course_id, lesson_id),
  CONSTRAINT tbl_elearning_progress_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
  CONSTRAINT tbl_elearning_progress_course_fk FOREIGN KEY (course_id) REFERENCES tbl_courses (id) ON DELETE CASCADE,
  CONSTRAINT tbl_elearning_progress_lesson_fk FOREIGN KEY (lesson_id) REFERENCES tbl_lessons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_attendance_elearning (
  id int NOT NULL AUTO_INCREMENT,
  live_class_id int NOT NULL,
  student_id varchar(20) NOT NULL,
  joined_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_attendance_elearning_uq (live_class_id, student_id),
  CONSTRAINT tbl_attendance_elearning_live_fk FOREIGN KEY (live_class_id) REFERENCES tbl_live_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_attendance_elearning_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_cbe_strands (
  id int NOT NULL AUTO_INCREMENT,
  subject_id int NOT NULL,
  name varchar(180) NOT NULL,
  status tinyint(1) NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_cbe_strands_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_cbe_assessments (
  id int NOT NULL AUTO_INCREMENT,
  student_id varchar(20) NOT NULL,
  class_id int NOT NULL,
  term_id int NOT NULL,
  subject_id int NULL,
  learning_area varchar(180) NOT NULL DEFAULT '',
  strand varchar(180) NOT NULL,
  level varchar(20) NOT NULL DEFAULT '',
  marks decimal(6,2) NULL,
  points int NOT NULL DEFAULT 0,
  teacher_id int NULL,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tbl_cbe_assessments_lookup (student_id, class_id, term_id),
  CONSTRAINT tbl_cbe_assessments_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
  CONSTRAINT tbl_cbe_assessments_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_cbe_assessments_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE CASCADE,
  CONSTRAINT tbl_cbe_assessments_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE SET NULL,
  CONSTRAINT tbl_cbe_assessments_teacher_fk FOREIGN KEY (teacher_id) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_fee_installments (
  id int NOT NULL AUTO_INCREMENT,
  invoice_id int NOT NULL,
  number_of_installments int NOT NULL DEFAULT 1,
  installment_amount decimal(12,2) NOT NULL DEFAULT 0,
  first_due_date date NULL,
  created_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_fee_installments_invoice_fk FOREIGN KEY (invoice_id) REFERENCES tbl_invoices (id) ON DELETE CASCADE,
  CONSTRAINT tbl_fee_installments_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_installment_schedule (
  id int NOT NULL AUTO_INCREMENT,
  installment_id int NOT NULL,
  installment_number int NOT NULL DEFAULT 1,
  due_date date NULL,
  amount_due decimal(12,2) NOT NULL DEFAULT 0,
  amount_paid decimal(12,2) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'pending',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT tbl_installment_schedule_installment_fk FOREIGN KEY (installment_id) REFERENCES tbl_fee_installments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
