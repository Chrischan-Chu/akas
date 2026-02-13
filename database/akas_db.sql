-- AKAS Database Schema (multi-admin clinics)
-- If you already have an older AKAS database, it's best to CREATE a new one and import this.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `clinic_doctors`;
DROP TABLE IF EXISTS `accounts`;
DROP TABLE IF EXISTS `clinics`;

/* =====================
   Table: clinics
   One clinic can have MANY clinic_admin accounts.
   ===================== */
CREATE TABLE `clinics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clinic_name` varchar(190) NOT NULL,
  `specialty` varchar(120) NOT NULL,
  `specialty_other` varchar(120) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `business_id` varchar(20) NOT NULL,
  `contact` varchar(32) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `approval_status` enum('PENDING','APPROVED','DECLINED') NOT NULL DEFAULT 'PENDING',
  `approved_at` datetime DEFAULT NULL,
  `declined_at` datetime DEFAULT NULL,
  `declined_reason` varchar(255) DEFAULT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_business_id` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* =====================
   Table: accounts
   clinic_id is NULL for normal users.
   ===================== */
CREATE TABLE `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role` enum('user','clinic_admin','super_admin') NOT NULL,
  `clinic_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(190) NOT NULL,
  `gender` varchar(40) DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `admin_work_id_path` varchar(255) DEFAULT NULL,

  -- ✅ Auth (local / google)
  `auth_provider` enum('local','google') NOT NULL DEFAULT 'local',
  `google_sub` varchar(64) DEFAULT NULL,
  `google_picture` varchar(255) DEFAULT NULL,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_clinic_id` (`clinic_id`),
  KEY `idx_google_sub` (`google_sub`),
  CONSTRAINT `fk_accounts_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* =====================
   Table: clinic_doctors
   Doctors belong to a clinic (not to a specific admin).
   ===================== */
CREATE TABLE `clinic_doctors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` int(10) unsigned NOT NULL,
  `name` varchar(190) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `specialization` varchar(120) DEFAULT NULL,
  `prc_no` varchar(50) DEFAULT NULL,
  `schedule` text DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `about` text DEFAULT NULL,
  `availability` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,

  -- ✅ Doctor approval workflow
  `approval_status` enum('PENDING','APPROVED','DECLINED') NOT NULL DEFAULT 'PENDING',
  `approved_at` datetime DEFAULT NULL,
  `declined_at` datetime DEFAULT NULL,
  `declined_reason` varchar(255) DEFAULT NULL,
  `created_via` enum('CMS','REGISTRATION') NOT NULL DEFAULT 'CMS',

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clinic_id` (`clinic_id`),
  KEY `idx_doc_status` (`approval_status`),
  KEY `idx_doc_via` (`created_via`),
  CONSTRAINT `fk_clinic_doctors_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* =====================
   Table: appointments
   Used by api/book_appointment.php
   ===================== */
CREATE TABLE `appointments` (
  `APT_AppointmentID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `APT_UserID` int(10) unsigned NOT NULL,
  `APT_DoctorID` int(10) unsigned NOT NULL,
  `APT_ClinicID` int(10) unsigned NOT NULL,
  `APT_Date` date NOT NULL,
  `APT_Time` time NOT NULL,
  `APT_Status` enum('PENDING','APPROVED','CANCELLED','DONE') NOT NULL DEFAULT 'PENDING',
  `APT_Notes` text DEFAULT NULL,
  `APT_Created` datetime NOT NULL,
  PRIMARY KEY (`APT_AppointmentID`),
  KEY `idx_appt_clinic_dt` (`APT_ClinicID`,`APT_Date`,`APT_Time`),
  KEY `idx_appt_user` (`APT_UserID`),
  KEY `idx_appt_doctor` (`APT_DoctorID`),
  CONSTRAINT `fk_appt_user` FOREIGN KEY (`APT_UserID`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_clinic` FOREIGN KEY (`APT_ClinicID`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`APT_DoctorID`) REFERENCES `clinic_doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
