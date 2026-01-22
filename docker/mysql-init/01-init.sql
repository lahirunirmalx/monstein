-- Monstein API - Database Initialization
-- This script runs when the database container is first created

-- Ensure UTF8MB4 encoding
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Grant additional privileges if needed
GRANT ALL PRIVILEGES ON monstein.* TO 'monstein'@'%';
FLUSH PRIVILEGES;

-- Log successful initialization
SELECT 'Monstein database initialized successfully' AS status;
