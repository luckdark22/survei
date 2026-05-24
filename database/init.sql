-- Master Schema for Survei Kiosk
-- Consolidates all tables, foreign keys, and initial seed data.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) DEFAULT 'staff',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: events
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_event_user` (`user_id`),
  CONSTRAINT `fk_event_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: questions
CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) DEFAULT NULL,
  `question_key` VARCHAR(50) NOT NULL UNIQUE,
  `section` VARCHAR(50) NOT NULL,
  `question` TEXT NOT NULL,
  `type` VARCHAR(20) NOT NULL DEFAULT 'rating',
  `placeholder` TEXT DEFAULT NULL,
  `options` TEXT DEFAULT NULL,
  `order_num` INT(11) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type_idx` (`type`),
  KEY `fk_question_event` (`event_id`),
  CONSTRAINT `fk_question_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: survey_sessions
CREATE TABLE IF NOT EXISTS `survey_sessions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) DEFAULT NULL,
  `device_id` VARCHAR(100) DEFAULT 'kiosk_main',
  `user_agent` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `event_idx` (`event_id`),
  KEY `created_at_idx` (`created_at`),
  CONSTRAINT `fk_session_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: survey_answers
CREATE TABLE IF NOT EXISTS `survey_answers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `session_id` INT(11) NOT NULL,
  `question_id` INT(11) DEFAULT NULL,
  `question_text` TEXT DEFAULT NULL,
  `answer_value` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `session_idx` (`session_id`),
  KEY `question_idx` (`question_id`),
  CONSTRAINT `fk_answer_session` FOREIGN KEY (`session_id`) REFERENCES `survey_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_answer_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `username` VARCHAR(50) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Default Data: Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('instansi_name', 'Direktorat Inovasi & Layanan'),
('running_text', 'Selamat Datang di Layanan Survei Kepuasan Masyarakat! Suara Anda sangat berarti bagi pengembangan kualitas layanan kami.');

-- Default Data: Admin User
-- Username: admin, Password: admin
INSERT IGNORE INTO `users` (`username`, `password`, `role`) VALUES
('admin', '$2y$10$B4/EdmcjEVWxTyj/JZsDBuKObqphZNzGgpmXGrs6B66nn/OGLvTt2', 'admin');

-- Default Data: Global Survey (Optional Example)
-- (Admin will usually add these manually, but we can seed if none exist)

COMMIT;
