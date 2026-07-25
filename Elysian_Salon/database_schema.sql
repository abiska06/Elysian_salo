-- Database Schema for Elysian Salon
-- Run this if you need to create the tables or verify the structure

CREATE DATABASE IF NOT EXISTS 25123780;
USE 25123780;

-- Users Table (Handles Auth)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
    failed_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    password_changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Staff Table
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Services Table
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration INT COMMENT 'Duration in minutes'
);

-- Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_id INT, -- Nullable if not assigned yet
    service_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Payments Table (Optional per requirement but good to have)
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id)
);

-- Sample Data Seeding

-- 1. Insert Services
INSERT INTO services (name, description, price, duration) VALUES
('Haircut & Style', 'Professional haircut and styling', 45.00, 60),
('Manicure', 'Classic manicure with gel polish', 25.00, 45),
('Facial Treatment', 'Rejuvenating facial for all skin types', 60.00, 90),
('Hair Coloring', 'Full hair coloring service', 80.00, 120);

-- 2. Insert Admin User (Password: admin123)
INSERT INTO users (username, password, role) VALUES 
('admin', 'HelloGurl321', 'admin');

-- 3. Insert Staff User (Password: staff123)
INSERT INTO users (username, password, role) VALUES 
('staff1', 'DoYourJob', 'staff');

-- 4. Insert Staff Details
-- Note: You need to get the user_id for staff1 first in a real scenario, assuming ID 2 here if auto-increment starts at 1
INSERT INTO staff (user_id, name, position) VALUES 
((SELECT id FROM users WHERE username='staff1'), 'Alice Stylist', 'Senior Stylist');

-- OTP Codes Table
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login Attempts Table
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    success TINYINT(1) NOT NULL,
    ip VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- User Audit Table
CREATE TABLE IF NOT EXISTS user_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Triggers to audit user changes
DELIMITER //
CREATE TRIGGER trg_users_after_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO user_audit (user_id, action, details)
    VALUES (NEW.id, 'UPDATE', CONCAT('role=', NEW.role));
END//
DELIMITER ;

-- Finance: Vouchers Table
CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_no VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('receipt','payment','journal') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    entry_date DATE NOT NULL DEFAULT (CURRENT_DATE),
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
