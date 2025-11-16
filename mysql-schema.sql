-- CoachSearching.com MySQL Database Schema
-- Created: 2025-11-16
-- Description: Complete database schema for the coaching platform

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: coachsearching
CREATE DATABASE IF NOT EXISTS `coachsearching` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `coachsearching`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('user', 'business', 'coach', 'admin', 'admin_delegate') NOT NULL DEFAULT 'user',
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `profile_image` VARCHAR(500) DEFAULT NULL,
  `email_verified` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role` (`role`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `sessions`
-- --------------------------------------------------------

CREATE TABLE `sessions` (
  `id` VARCHAR(128) NOT NULL,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `payload` TEXT NOT NULL,
  `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `last_activity` (`last_activity`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `coach_profiles`
-- --------------------------------------------------------

CREATE TABLE `coach_profiles` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `bio` TEXT,
  `tagline` VARCHAR(255) DEFAULT NULL,
  `specializations` JSON DEFAULT NULL,
  `certifications` JSON DEFAULT NULL,
  `languages` JSON DEFAULT NULL,
  `hourly_rate` DECIMAL(10, 2) DEFAULT NULL,
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `years_experience` INT(11) DEFAULT NULL,
  `location_city` VARCHAR(100) DEFAULT NULL,
  `location_country` VARCHAR(100) DEFAULT NULL,
  `location_lat` DECIMAL(10, 7) DEFAULT NULL,
  `location_lng` DECIMAL(10, 7) DEFAULT NULL,
  `offers_in_person` TINYINT(1) DEFAULT 0,
  `offers_online` TINYINT(1) DEFAULT 1,
  `auto_accept_bookings` TINYINT(1) DEFAULT 0,
  `average_rating` DECIMAL(3, 2) DEFAULT 0.00,
  `total_reviews` INT(11) DEFAULT 0,
  `total_sessions` INT(11) DEFAULT 0,
  `is_verified` TINYINT(1) DEFAULT 0,
  `is_featured` TINYINT(1) DEFAULT 0,
  `profile_views` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `average_rating` (`average_rating`),
  KEY `is_verified` (`is_verified`),
  KEY `is_featured` (`is_featured`),
  KEY `location` (`location_lat`, `location_lng`),
  FULLTEXT KEY `search_text` (`bio`, `tagline`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `business_profiles`
-- --------------------------------------------------------

CREATE TABLE `business_profiles` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `company_size` ENUM('1-10', '11-50', '51-200', '201-500', '501-1000', '1000+') DEFAULT NULL,
  `industry` VARCHAR(100) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `description` TEXT,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `vat_number` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `business_team_members`
-- --------------------------------------------------------

CREATE TABLE `business_team_members` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id` INT(11) UNSIGNED NOT NULL,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `invite_token` VARCHAR(64) DEFAULT NULL,
  `invite_email` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'active', 'inactive') DEFAULT 'pending',
  `invited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `accepted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_user` (`business_id`, `user_id`),
  KEY `invite_token` (`invite_token`),
  KEY `status` (`status`),
  FOREIGN KEY (`business_id`) REFERENCES `business_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `coaching_sessions`
-- --------------------------------------------------------

CREATE TABLE `coaching_sessions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `coach_id` INT(11) UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `session_type` ENUM('individual', 'group', 'workshop', 'package') DEFAULT 'individual',
  `duration_minutes` INT(11) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `max_participants` INT(11) DEFAULT 1,
  `location_type` ENUM('online', 'in_person', 'both') DEFAULT 'online',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `coach_id` (`coach_id`),
  KEY `is_active` (`is_active`),
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `coach_availability`
-- --------------------------------------------------------

CREATE TABLE `coach_availability` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `coach_id` INT(11) UNSIGNED NOT NULL,
  `day_of_week` TINYINT(1) NOT NULL COMMENT '0=Sunday, 6=Saturday',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `coach_id` (`coach_id`),
  KEY `day_of_week` (`day_of_week`),
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `bookings`
-- --------------------------------------------------------

CREATE TABLE `bookings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` INT(11) UNSIGNED NOT NULL,
  `coach_id` INT(11) UNSIGNED NOT NULL,
  `client_id` INT(11) UNSIGNED NOT NULL,
  `client_type` ENUM('user', 'business') NOT NULL DEFAULT 'user',
  `requested_datetime` DATETIME NOT NULL,
  `confirmed_datetime` DATETIME DEFAULT NULL,
  `status` ENUM('pending', 'confirmed', 'rejected', 'cancelled', 'completed') DEFAULT 'pending',
  `total_price` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `payment_status` ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
  `payment_id` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT,
  `coach_notes` TEXT,
  `cancellation_reason` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `coach_id` (`coach_id`),
  KEY `client_id` (`client_id`),
  KEY `status` (`status`),
  KEY `requested_datetime` (`requested_datetime`),
  FOREIGN KEY (`session_id`) REFERENCES `coaching_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `reviews`
-- --------------------------------------------------------

CREATE TABLE `reviews` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `coach_id` INT(11) UNSIGNED NOT NULL,
  `reviewer_id` INT(11) UNSIGNED NOT NULL,
  `booking_id` INT(11) UNSIGNED NOT NULL,
  `rating` TINYINT(1) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `title` VARCHAR(255) DEFAULT NULL,
  `comment` TEXT,
  `is_verified` TINYINT(1) DEFAULT 1,
  `is_visible` TINYINT(1) DEFAULT 1,
  `coach_response` TEXT,
  `coach_response_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `coach_id` (`coach_id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `rating` (`rating`),
  KEY `is_visible` (`is_visible`),
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `articles`
-- --------------------------------------------------------

CREATE TABLE `articles` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `coach_id` INT(11) UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `excerpt` TEXT,
  `featured_image` VARCHAR(500) DEFAULT NULL,
  `tags` JSON DEFAULT NULL,
  `is_published` TINYINT(1) DEFAULT 0,
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `views` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coach_slug` (`coach_id`, `slug`),
  KEY `coach_id` (`coach_id`),
  KEY `is_published` (`is_published`),
  KEY `published_at` (`published_at`),
  FULLTEXT KEY `search_content` (`title`, `content`, `excerpt`),
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `messages`
-- --------------------------------------------------------

CREATE TABLE `messages` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` VARCHAR(64) NOT NULL,
  `from_user_id` INT(11) UNSIGNED NOT NULL,
  `to_user_id` INT(11) UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `to_user_id` (`to_user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `follows`
-- --------------------------------------------------------

CREATE TABLE `follows` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `coach_id` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_coach` (`user_id`, `coach_id`),
  KEY `user_id` (`user_id`),
  KEY `coach_id` (`coach_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `likes`
-- --------------------------------------------------------

CREATE TABLE `likes` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `likeable_type` ENUM('coach', 'article') NOT NULL,
  `likeable_id` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_likeable` (`user_id`, `likeable_type`, `likeable_id`),
  KEY `user_id` (`user_id`),
  KEY `likeable` (`likeable_type`, `likeable_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `questionnaire_responses`
-- --------------------------------------------------------

CREATE TABLE `questionnaire_responses` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `session_id` VARCHAR(128) DEFAULT NULL,
  `user_type` ENUM('guest', 'user', 'business') NOT NULL DEFAULT 'guest',
  `answers` JSON NOT NULL,
  `matched_coaches` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `admin_delegates`
-- --------------------------------------------------------

CREATE TABLE `admin_delegates` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) UNSIGNED NOT NULL,
  `delegate_user_id` INT(11) UNSIGNED NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delegate_user_id` (`delegate_user_id`),
  KEY `admin_id` (`admin_id`),
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`delegate_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `activity_feed`
-- --------------------------------------------------------

CREATE TABLE `activity_feed` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `activity_type` ENUM('article', 'session', 'achievement', 'announcement') NOT NULL,
  `activity_data` JSON NOT NULL,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `activity_type` (`activity_type`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------

CREATE TABLE `notifications` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(500) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_transactions`
-- --------------------------------------------------------

CREATE TABLE `payment_transactions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) UNSIGNED NOT NULL,
  `coach_id` INT(11) UNSIGNED NOT NULL,
  `client_id` INT(11) UNSIGNED NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `platform_fee` DECIMAL(10, 2) DEFAULT 0.00,
  `coach_payout` DECIMAL(10, 2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_provider` VARCHAR(50) DEFAULT NULL,
  `provider_transaction_id` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
  `payout_status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `coach_id` (`coach_id`),
  KEY `client_id` (`client_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Insert sample admin user
-- --------------------------------------------------------

INSERT INTO `users` (`email`, `password_hash`, `role`, `first_name`, `last_name`, `email_verified`, `is_active`) VALUES
('admin@coachsearching.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', 1, 1);
-- Default password: password (CHANGE THIS IN PRODUCTION!)

COMMIT;
