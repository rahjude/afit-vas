-- AFIT Virtual Admission System - Database Schema
-- MySQL 8.0

CREATE DATABASE IF NOT EXISTS afit_vas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE afit_vas;

-- ─── USERS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  first_name      VARCHAR(100)        NOT NULL,
  last_name       VARCHAR(100)        NOT NULL,
  email           VARCHAR(150) UNIQUE NOT NULL,
  password_hash   VARCHAR(255)        NOT NULL,
  phone           VARCHAR(20)         NOT NULL,
  gender          ENUM('Male','Female','Other') NULL,
  state_of_origin VARCHAR(100)        NULL,
  date_of_birth   DATE                NULL,
  role            ENUM('applicant','admin','super_admin') DEFAULT 'applicant',
  is_verified     TINYINT(1)          DEFAULT 0,
  verify_token    VARCHAR(100)        NULL,
  created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role (role)
) ENGINE=InnoDB;

-- ─── PROGRAMMES ────────────────────────────────────
CREATE TABLE IF NOT EXISTS programmes (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(150)    NOT NULL,
  code              VARCHAR(20)     UNIQUE NOT NULL,
  department        VARCHAR(150)    NOT NULL,
  degree_type       VARCHAR(50)     NOT NULL DEFAULT 'B.Sc.',
  duration_years    INT             NOT NULL DEFAULT 4,
  required_subjects JSON            NOT NULL COMMENT 'JSON array of required O-level subject names',
  min_credits       INT             NOT NULL DEFAULT 5,
  is_active         TINYINT(1)      DEFAULT 1,
  created_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── APPLICATIONS ──────────────────────────────────
CREATE TABLE IF NOT EXISTS applications (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  user_id             INT             NOT NULL,
  programme_id        INT             NOT NULL,
  reference_number    VARCHAR(30)     UNIQUE NOT NULL,
  jamb_reg_number     VARCHAR(30)     NULL,
  jamb_score          INT             NOT NULL,
  previous_institution VARCHAR(200)   NULL,
  previous_qualification VARCHAR(100) NULL,
  o_level_results     JSON            NOT NULL COMMENT 'Array of {subject, grade, exam_type, year}',
  status              ENUM('draft','submitted','eligible','not_eligible','under_review','admitted','rejected') DEFAULT 'draft',
  eligibility_score   JSON            NULL COMMENT 'Result from eligibility engine',
  admin_notes         TEXT            NULL,
  submitted_at        TIMESTAMP       NULL,
  decided_at          TIMESTAMP       NULL,
  created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_programme (user_id, programme_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE RESTRICT,
  INDEX idx_status (status),
  INDEX idx_reference (reference_number)
) ENGINE=InnoDB;

-- ─── DOCUMENTS ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  application_id  INT             NOT NULL,
  doc_type        ENUM('waec_result','neco_result','jamb_slip','birth_certificate','passport_photo','lga_certificate','medical_certificate') NOT NULL,
  file_name       VARCHAR(255)    NOT NULL,
  file_path       VARCHAR(500)    NOT NULL,
  file_size       INT             NOT NULL DEFAULT 0,
  mime_type       VARCHAR(100)    NOT NULL,
  status          ENUM('pending','verified','rejected') DEFAULT 'pending',
  rejection_reason TEXT           NULL,
  verified_by     INT             NULL,
  uploaded_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_app_doc (application_id, doc_type)
) ENGINE=InnoDB;

-- ─── AUDIT LOGS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT             NOT NULL,
  action      VARCHAR(255)    NOT NULL,
  target_type VARCHAR(50)     NULL,
  target_id   INT             NULL,
  payload     JSON            NULL COMMENT 'Before/after values',
  ip_address  VARCHAR(45)     NULL,
  logged_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id),
  INDEX idx_admin (admin_id),
  INDEX idx_target (target_type, target_id)
) ENGINE=InnoDB;

-- ─── NOTIFICATIONS ─────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT             NOT NULL,
  title       VARCHAR(255)    NOT NULL,
  message     TEXT            NOT NULL,
  type        VARCHAR(50)     NOT NULL DEFAULT 'info',
  is_read     TINYINT(1)      DEFAULT 0,
  read_at     TIMESTAMP       NULL,
  created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- ─── STATUS HISTORY ────────────────────────────────
CREATE TABLE IF NOT EXISTS status_history (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  application_id  INT             NOT NULL,
  old_status      VARCHAR(30)     NULL,
  new_status      VARCHAR(30)     NOT NULL,
  changed_by      INT             NULL,
  notes           TEXT            NULL,
  changed_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_app_history (application_id)
) ENGINE=InnoDB;

-- ─── SEED PROGRAMMES ───────────────────────────────
INSERT INTO programmes (name, code, department, degree_type, duration_years, required_subjects, min_credits) VALUES
('Computer Science', 'CSC', 'Computer Science', 'B.Sc.', 4, '["Mathematics", "English Language", "Physics", "Chemistry", "Biology"]', 5),
('Cyber Security', 'CYB', 'Cyber Security', 'B.Sc.', 4, '["Mathematics", "English Language", "Physics", "Chemistry", "Biology"]', 5),
('Information Technology', 'IFT', 'Information Technology', 'B.Sc.', 4, '["Mathematics", "English Language", "Physics", "Chemistry", "Biology"]', 5),
('Electrical Electronics Engineering', 'EEE', 'Electrical Engineering', 'B.Eng.', 5, '["Mathematics", "English Language", "Physics", "Chemistry", "Further Mathematics"]', 5),
('Mechanical Engineering', 'MEE', 'Mechanical Engineering', 'B.Eng.', 5, '["Mathematics", "English Language", "Physics", "Chemistry", "Technical Drawing"]', 5),
('Aircraft Maintenance Engineering', 'AME', 'Aircraft Engineering', 'B.Eng.', 5, '["Mathematics", "English Language", "Physics", "Chemistry", "Further Mathematics"]', 5);

-- ─── SEED ADMIN ────────────────────────────────────
INSERT INTO users (first_name, last_name, email, password_hash, phone, role, is_verified) VALUES
('Super', 'Admin', 'admin@afit.edu.ng', '$2y$12$5O2Ms3X.EAqbc94KZ2NDR.GryqTl3pLa.2IEd09Y4KzYT1CVVubYW', '08012345678', 'super_admin', 1);
-- Default password: Admin@2026
