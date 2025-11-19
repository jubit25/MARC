USE railway;

-- Create users table if it somehow doesn't exist
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('system_admin', 'registrar', 'students') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upsert admin: if exists, update; if not, insert
INSERT INTO users (username, password, email, first_name, last_name, role)
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- bcrypt('password')
    'admin@marcagape.edu',
    'System',
    'Administrator',
    'system_admin'
)
ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    email    = VALUES(email),
    first_name = VALUES(first_name),
    last_name  = VALUES(last_name),
    role       = VALUES(role);
