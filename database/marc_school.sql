-- Use the same database name you configured in Render (DB_NAME = railway)
USE railway;

-- =========================
-- USERS TABLE
-- =========================
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

-- Upsert default system admin
-- Password in plain text: password
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
    password   = VALUES(password),
    email      = VALUES(email),
    first_name = VALUES(first_name),
    last_name  = VALUES(last_name),
    role       = VALUES(role);

-- =========================
-- STUDENTS TABLE
-- =========================
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    grade_level VARCHAR(20) NOT NULL,
    section VARCHAR(20),
    birth_date DATE,
    address TEXT,
    parent_name VARCHAR(100),
    parent_contact VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- SUBJECTS TABLE
-- =========================
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    subject_name VARCHAR(100) NOT NULL,
    description TEXT,
    grade_level VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- GRADES TABLE
-- =========================
CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    grade DECIMAL(5,2) NOT NULL,
    quarter ENUM('1st', '2nd', '3rd', '4th') NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    remarks VARCHAR(50),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- PAYMENT CATEGORIES TABLE
-- =========================
CREATE TABLE IF NOT EXISTS payment_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    frequency ENUM('one_time', 'monthly', 'quarterly', 'yearly') DEFAULT 'one_time',
    UNIQUE KEY uq_payment_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- PAYMENTS TABLE
-- =========================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payment_category_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'online') NOT NULL,
    reference_number VARCHAR(100),
    received_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_category_id) REFERENCES payment_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- PAYMENT SCHEDULE TABLE
-- =========================
CREATE TABLE IF NOT EXISTS payment_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payment_category_id INT NOT NULL,
    due_date DATE NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_category_id) REFERENCES payment_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- DEFAULT PAYMENT CATEGORIES
-- =========================
INSERT INTO payment_categories (name, description, amount, is_recurring, frequency) VALUES
('Tuition Fee', 'Monthly tuition fee', 5000.00, TRUE, 'monthly'),
('Registration Fee', 'One-time registration fee', 2000.00, FALSE, 'one_time'),
('Books', 'Books and learning materials', 1500.00, FALSE, 'one_time'),
('Uniform', 'School uniform', 1200.00, FALSE, 'one_time')
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    amount      = VALUES(amount),
    is_recurring = VALUES(is_recurring),
    frequency    = VALUES(frequency);

-- =========================
-- DEFAULT SUBJECTS
-- =========================
INSERT INTO subjects (subject_code, subject_name, description, grade_level) VALUES
('MATH-101', 'Mathematics', 'Basic Mathematics', 'Grade 1'),
('ENG-101', 'English', 'English Language', 'Grade 1'),
('SCI-101', 'Science', 'Basic Science', 'Grade 1'),
('FIL-101', 'Filipino', 'Filipino Language', 'Grade 1')
ON DUPLICATE KEY UPDATE
    subject_name = VALUES(subject_name),
    description  = VALUES(description),
    grade_level  = VALUES(grade_level);
