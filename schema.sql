CREATE DATABASE IF NOT EXISTS `ems`;
USE `ems`;

-- 1. Create users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'Employee',
  `full_name` VARCHAR(100) NOT NULL
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
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create schedules table
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_username` VARCHAR(50) NOT NULL,
  `schedules_date` DATE NOT NULL,
  `schedules_time` VARCHAR(20) NOT NULL,
  FOREIGN KEY (`employee_username`) REFERENCES `users`(`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create salaries table
CREATE TABLE IF NOT EXISTS `salaries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_username` VARCHAR(50) NOT NULL,
  `month` VARCHAR(7) NOT NULL, -- YYYY-MM format
  `total_shifts` INT DEFAULT 0,
  `calculated_salary` DECIMAL(10,2) DEFAULT 0.00,
  UNIQUE KEY `user_month` (`employee_username`, `month`),
  FOREIGN KEY (`employee_username`) REFERENCES `users`(`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
