-- MySQL compatibility patch for SRMS newer modules on XAMPP/MariaDB.
-- Safe to run multiple times.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- RBAC core
CREATE TABLE IF NOT EXISTS tbl_permissions (
  id int NOT NULL AUTO_INCREMENT,
  code varchar(80) NOT NULL,
  description varchar(255) NOT NULL DEFAULT '',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_permissions_code_uq (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_roles (
  id int NOT NULL AUTO_INCREMENT,
  name varchar(80) NOT NULL,
  level int NOT NULL DEFAULT 0,
  description varchar(255) NOT NULL DEFAULT '',
  is_system tinyint(1) NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_roles_name_uq (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_role_permissions (
  role_id int NOT NULL,
  permission_id int NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT tbl_role_permissions_role_fk FOREIGN KEY (role_id) REFERENCES tbl_roles (id) ON DELETE CASCADE,
  CONSTRAINT tbl_role_permissions_perm_fk FOREIGN KEY (permission_id) REFERENCES tbl_permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_user_roles (
  staff_id int NOT NULL,
  role_id int NOT NULL,
  assigned_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (staff_id, role_id),
  CONSTRAINT tbl_user_roles_staff_fk FOREIGN KEY (staff_id) REFERENCES tbl_staff (id) ON DELETE CASCADE,
  CONSTRAINT tbl_user_roles_role_fk FOREIGN KEY (role_id) REFERENCES tbl_roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parent module
CREATE TABLE IF NOT EXISTS tbl_parents (
  id int NOT NULL AUTO_INCREMENT,
  fname varchar(70) NOT NULL,
  lname varchar(70) NOT NULL,
  phone varchar(30) NOT NULL DEFAULT '',
  email varchar(120) NOT NULL,
  password varchar(255) NOT NULL,
  status int NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_parents_email_uq (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_parent_students (
  parent_id int NOT NULL,
  student_id varchar(20) NOT NULL,
  PRIMARY KEY (parent_id, student_id),
  CONSTRAINT tbl_parent_students_parent_fk FOREIGN KEY (parent_id) REFERENCES tbl_parents (id) ON DELETE CASCADE,
  CONSTRAINT tbl_parent_students_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parent session support
ALTER TABLE tbl_login_sessions
  ADD COLUMN IF NOT EXISTS parent int NULL;

-- Attendance module
CREATE TABLE IF NOT EXISTS tbl_attendance_sessions (
  id int NOT NULL AUTO_INCREMENT,
  class_id int NOT NULL,
  term_id int NULL,
  session_date date NOT NULL,
  session_type varchar(20) NOT NULL DEFAULT 'daily',
  subject_id int NULL,
  created_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_attendance_sessions_unique (class_id, session_date, session_type, subject_id),
  KEY tbl_attendance_sessions_term_idx (term_id),
  KEY tbl_attendance_sessions_subject_idx (subject_id),
  KEY tbl_attendance_sessions_created_by_idx (created_by),
  CONSTRAINT tbl_attendance_sessions_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_attendance_sessions_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE SET NULL,
  CONSTRAINT tbl_attendance_sessions_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE SET NULL,
  CONSTRAINT tbl_attendance_sessions_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_attendance_records (
  session_id int NOT NULL,
  student_id varchar(20) NOT NULL,
  status varchar(10) NOT NULL,
  marked_by int NULL,
  marked_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (session_id, student_id),
  KEY tbl_attendance_records_student_idx (student_id),
  KEY tbl_attendance_records_marked_by_idx (marked_by),
  CONSTRAINT tbl_attendance_records_session_fk FOREIGN KEY (session_id) REFERENCES tbl_attendance_sessions (id) ON DELETE CASCADE,
  CONSTRAINT tbl_attendance_records_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
  CONSTRAINT tbl_attendance_records_staff_fk FOREIGN KEY (marked_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_staff_attendance (
  id int NOT NULL AUTO_INCREMENT,
  staff_id int NOT NULL,
  attendance_date date NOT NULL,
  status varchar(10) NOT NULL DEFAULT 'present',
  clock_in datetime NULL,
  clock_out datetime NULL,
  marked_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_staff_attendance_unique (staff_id, attendance_date),
  KEY tbl_staff_attendance_marked_by_idx (marked_by),
  CONSTRAINT tbl_staff_attendance_staff_fk FOREIGN KEY (staff_id) REFERENCES tbl_staff (id) ON DELETE CASCADE,
  CONSTRAINT tbl_staff_attendance_marked_by_fk FOREIGN KEY (marked_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit + notifications
CREATE TABLE IF NOT EXISTS tbl_audit_logs (
  id int NOT NULL AUTO_INCREMENT,
  actor_type varchar(20) NOT NULL,
  actor_id varchar(50) NOT NULL,
  action varchar(100) NOT NULL,
  entity varchar(100) NOT NULL,
  entity_id varchar(100) NOT NULL DEFAULT '',
  ip varchar(90) NOT NULL DEFAULT '',
  user_agent text NOT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tbl_audit_logs_created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_notifications (
  id int NOT NULL AUTO_INCREMENT,
  title varchar(180) NOT NULL,
  message text NOT NULL,
  audience varchar(30) NOT NULL DEFAULT 'all',
  class_id int NULL,
  term_id int NULL,
  link varchar(255) NULL,
  created_by int NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tbl_notifications_audience_idx (audience),
  KEY tbl_notifications_class_idx (class_id),
  KEY tbl_notifications_term_idx (term_id),
  KEY tbl_notifications_created_by_idx (created_by),
  CONSTRAINT tbl_notifications_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE SET NULL,
  CONSTRAINT tbl_notifications_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE SET NULL,
  CONSTRAINT tbl_notifications_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exams baseline (needed by many modules)
CREATE TABLE IF NOT EXISTS tbl_exam_types (
  id int NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL,
  status int NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY tbl_exam_types_name_uq (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_exams (
  id int NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL,
  term_id int NOT NULL,
  class_id int NOT NULL,
  exam_type_id int NULL,
  grading_system_id int NULL,
  assessment_mode varchar(20) NOT NULL DEFAULT 'normal',
  status varchar(20) NOT NULL DEFAULT 'draft',
  created_by int NULL,
  academic_year varchar(20) NOT NULL DEFAULT '',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tbl_exams_term_idx (term_id),
  KEY tbl_exams_class_idx (class_id),
  KEY tbl_exams_type_idx (exam_type_id),
  KEY tbl_exams_created_by_idx (created_by),
  CONSTRAINT tbl_exams_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE CASCADE,
  CONSTRAINT tbl_exams_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
  CONSTRAINT tbl_exams_type_fk FOREIGN KEY (exam_type_id) REFERENCES tbl_exam_types (id) ON DELETE SET NULL,
  CONSTRAINT tbl_exams_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_exam_weights (
  exam_id int NOT NULL,
  weight_percentage decimal(6,2) NOT NULL DEFAULT 100,
  PRIMARY KEY (exam_id),
  CONSTRAINT tbl_exam_weights_exam_fk FOREIGN KEY (exam_id) REFERENCES tbl_exams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_exam_components (
  exam_id int NOT NULL,
  component_exam_id int NOT NULL,
  PRIMARY KEY (exam_id, component_exam_id),
  CONSTRAINT tbl_exam_components_exam_fk FOREIGN KEY (exam_id) REFERENCES tbl_exams (id) ON DELETE CASCADE,
  CONSTRAINT tbl_exam_components_component_fk FOREIGN KEY (component_exam_id) REFERENCES tbl_exams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Keep old exam results table compatible with newer code paths.
ALTER TABLE tbl_exam_results
  ADD COLUMN IF NOT EXISTS exam_id int NULL;

-- Seed permissions and roles used by current code.
INSERT INTO tbl_permissions (code, description) VALUES
('system.manage', 'Manage system settings'),
('audit.view', 'View audit logs'),
('users.impersonate', 'Impersonate users for support and debugging'),
('students.manage', 'Manage students'),
('admissions.manage', 'Manage admissions and student onboarding'),
('staff.manage', 'Manage staff and role assignment'),
('teacher.allocate', 'Allocate teachers to subjects/classes'),
('classes.assign', 'Assign classes and class teachers'),
('timetable.manage', 'Manage school and exam timetables'),
('student.leadership.manage', 'Manage student leadership assignments and reports'),
('bom.manage', 'Manage Board of Management records and meetings'),
('bom.view', 'View Board of Management records'),
('attendance.manage', 'Manage attendance'),
('exams.manage', 'Manage exams and timetable'),
('marks.enter', 'Enter marks and assessments'),
('marks.review', 'Review submitted marks before approval'),
('results.approve', 'Approve results'),
('results.publish', 'Publish approved results to students and parents'),
('results.lock', 'Lock results'),
('results.unlock', 'Unlock results'),
('report.generate', 'Generate report cards'),
('report.view', 'View report cards'),
('finance.manage', 'Manage fees and payments'),
('finance.view', 'View finance reports'),
('communication.manage', 'Manage communication'),
('communication.send', 'Send direct messages and announcements'),
('notifications.send', 'Send SMS/email notifications'),
('sms.wallet.manage', 'Manage SMS wallet and topups'),
('transport.manage', 'Manage transport'),
('library.manage', 'Manage library'),
('inventory.manage', 'Manage inventory'),
('academic.manage', 'Manage academic structures and settings'),
('certificates.manage', 'Manage certificates'),
('student.leadership.view', 'View student leadership data')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO tbl_roles (name, level, description, is_system) VALUES
('Headteacher', 100, 'School head with full operational control', 1),
('Deputy Headteacher', 90, 'Deputy head with broad academic control', 1),
('Teacher', 50, 'Teacher role for class and marks operations', 1),
('Accountant', 50, 'Finance role', 1),
('HR Manager', 40, 'Human resources role', 1),
('Transport Manager', 30, 'Transport operations role', 1),
('Librarian', 30, 'Library operations role', 1),
('Super Admin', 1000, 'Global full-access role', 1),
('BOM Member', 35, 'Board of management role', 1)
ON DUPLICATE KEY UPDATE level = VALUES(level), description = VALUES(description), is_system = VALUES(is_system);

-- Grant full permissions to Headteacher and Super Admin for local recovery setup.
INSERT IGNORE INTO tbl_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM tbl_roles r
JOIN tbl_permissions p
WHERE r.name IN ('Headteacher', 'Super Admin');

-- Basic role defaults by legacy staff level.
INSERT IGNORE INTO tbl_user_roles (staff_id, role_id)
SELECT s.id, r.id
FROM tbl_staff s
JOIN tbl_roles r ON r.name = CASE
  WHEN s.level = 0 THEN 'Headteacher'
  WHEN s.level = 1 THEN 'Deputy Headteacher'
  WHEN s.level = 2 THEN 'Teacher'
  WHEN s.level = 5 THEN 'Accountant'
  WHEN s.level = 6 THEN 'HR Manager'
  WHEN s.level = 7 THEN 'Transport Manager'
  WHEN s.level = 8 THEN 'Librarian'
  WHEN s.level = 9 THEN 'Super Admin'
  WHEN s.level = 10 THEN 'BOM Member'
  ELSE 'Teacher'
END;

SET FOREIGN_KEY_CHECKS=1;
