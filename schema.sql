CREATE DATABASE IF NOT EXISTS `ems`;
USE `ems`;

-- 1. Create users table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'Employee',
  `full_name` VARCHAR(100) NOT NULL,
  `login_attempts` INT DEFAULT 0,
  `lockout_until` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create employee_profiles table
CREATE TABLE IF NOT EXISTS `employee_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `contact` VARCHAR(50) DEFAULT NULL,
  `bank_account_number` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `hours_worked` DECIMAL(10,2) DEFAULT 0.00,
  `shift_rate` DECIMAL(10,2) DEFAULT 28.00,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create schedules table
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `schedules_date` DATE NOT NULL,
  `schedules_time` VARCHAR(20) NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create salaries table
CREATE TABLE IF NOT EXISTS `salaries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `month` VARCHAR(7) NOT NULL, -- YYYY-MM format
  `total_shifts` INT DEFAULT 0,
  `calculated_salary` DECIMAL(10,2) DEFAULT 0.00,
  `bonus` DECIMAL(10,2) DEFAULT 0.00,
  `deduction` DECIMAL(10,2) DEFAULT 0.00,
  `status` VARCHAR(20) DEFAULT 'Draft',
  UNIQUE KEY `user_month` (`user_id`, `month`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create leave_requests table
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `leave_date` DATE NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `status` VARCHAR(20) DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- 7. Create availability_preferences table
CREATE TABLE IF NOT EXISTS `availability_preferences` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `day_of_week` INT NOT NULL,
  `time_slot` VARCHAR(20) NOT NULL,
  `is_available` TINYINT DEFAULT 1,
  UNIQUE KEY `user_day_slot` (`user_id`, `day_of_week`, `time_slot`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

