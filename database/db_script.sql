-- Monstein API - Initial Database Schema
-- This script creates all required tables for the Monstein API
-- Run with: mysql -u username -p database_name < db_script.sql

-- ============================================================================
-- Users Table
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME
);

-- ============================================================================
-- Categories Table
-- ============================================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================================
-- Todo Table
-- ============================================================================
CREATE TABLE IF NOT EXISTS todo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- ============================================================================
-- Files Table (for file uploads)
-- ============================================================================
CREATE TABLE IF NOT EXISTS files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    path VARCHAR(500) NULL,
    hash VARCHAR(64) NULL,
    content LONGTEXT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_files_user (user_id),
    INDEX idx_files_hash (hash)
);

-- ============================================================================
-- Usage Logs Table (for API tracking)
-- ============================================================================
CREATE TABLE IF NOT EXISTS usage_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    status_code INT UNSIGNED NOT NULL DEFAULT 200,
    response_time_ms DECIMAL(10,2) NOT NULL DEFAULT 0,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    request_size INT UNSIGNED NOT NULL DEFAULT 0,
    response_size INT UNSIGNED NOT NULL DEFAULT 0,
    route_name VARCHAR(100) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usage_endpoint (endpoint),
    INDEX idx_usage_method (method),
    INDEX idx_usage_status (status_code),
    INDEX idx_usage_user (user_id),
    INDEX idx_usage_created (created_at),
    INDEX idx_usage_endpoint_method (endpoint, method)
);

-- ============================================================================
-- Phinx Migrations Table (for migration tracking)
-- ============================================================================
CREATE TABLE IF NOT EXISTS phinxlog (
    version BIGINT NOT NULL,
    migration_name VARCHAR(100) DEFAULT NULL,
    start_time TIMESTAMP NULL DEFAULT NULL,
    end_time TIMESTAMP NULL DEFAULT NULL,
    breakpoint TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (version)
);

-- ============================================================================
-- Demo User (username: demo, password: demo123)
-- ============================================================================
-- Note: Password hash is bcrypt for 'demo123'
INSERT IGNORE INTO users (username, password, created_at, updated_at) 
VALUES ('demo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW());
