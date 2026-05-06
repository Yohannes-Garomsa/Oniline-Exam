-- Create Database
CREATE DATABASE IF NOT EXISTS if0_41849798_online_exam_system;
USE if0_41849798_online_exam_system;

-- =====================================================
-- USERS TABLE (Students, Instructors, Admins)
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('student', 'instructor', 'admin') NOT NULL,
    department VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Student specific fields
CREATE TABLE students (
    user_id INT PRIMARY KEY,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    year_of_study INT,
    section VARCHAR(10),
    gpa DECIMAL(3,2),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Instructor specific fields
CREATE TABLE instructors (
    user_id INT PRIMARY KEY,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    qualification VARCHAR(255),
    experience_years INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- COURSES TABLE
-- =====================================================
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    department VARCHAR(100),
    credits INT,
    instructor_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- EXAMS TABLE
-- =====================================================
CREATE TABLE exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_title VARCHAR(255) NOT NULL,
    course_id INT NOT NULL,
    instructor_id INT NOT NULL,
    description TEXT,
    exam_type ENUM('Final', 'Mid', 'Quiz', 'Practice') DEFAULT 'Quiz',
    total_marks INT DEFAULT 100,
    passing_score INT DEFAULT 50,
    duration_minutes INT DEFAULT 120,
    available_from DATETIME,
    available_until DATETIME,
    randomize_questions BOOLEAN DEFAULT FALSE,
    show_results ENUM('immediate', 'after_end', 'manual') DEFAULT 'immediate',
    attempts_allowed INT DEFAULT 1,
    status ENUM('draft', 'active', 'completed') DEFAULT 'draft',
    exam_password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- QUESTIONS TABLE
-- =====================================================
CREATE TABLE questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_text TEXT NOT NULL,
    question_type ENUM('MCQ', 'True/False') DEFAULT 'MCQ',
    difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
    course_id INT NOT NULL,
    instructor_id INT NOT NULL,
    explanation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- QUESTION OPTIONS TABLE (for MCQ)
-- =====================================================
CREATE TABLE question_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    option_letter ENUM('A', 'B', 'C', 'D') NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- =====================================================
-- EXAM QUESTIONS (junction table)
-- =====================================================
CREATE TABLE exam_questions (
    exam_id INT NOT NULL,
    question_id INT NOT NULL,
    question_order INT,
    marks INT DEFAULT 2,
    PRIMARY KEY (exam_id, question_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- =====================================================
-- STUDENT EXAM ATTEMPTS
-- =====================================================
CREATE TABLE exam_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    start_time DATETIME,
    end_time DATETIME,
    status ENUM('in_progress', 'submitted', 'graded', 'timed_out') DEFAULT 'in_progress',
    total_score DECIMAL(5,2),
    percentage DECIMAL(5,2),
    passed BOOLEAN,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- STUDENT ANSWERS
-- =====================================================
CREATE TABLE student_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1),
    answer_text TEXT,
    marks_obtained DECIMAL(5,2),
    is_correct BOOLEAN,
    feedback TEXT,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- =====================================================
-- SAMPLE DATA (for testing)
-- =====================================================

-- Insert admin user
INSERT INTO users (user_id, first_name, last_name, email, password, role) VALUES
('ADMIN001', 'System', 'Admin', 'admin@oes.edu.et', '0987', 'admin');

-- Insert sample instructors
INSERT INTO users (user_id, first_name, last_name, email, password, role, department) VALUES
('INST001', 'Ayal', 'Yitbarek', 'ayal.yitbarek@oes.edu.et', '2127', 'instructor', 'IT'),
('INST002', 'Tigist', 'Mulugeta', 'tigist.mulugeta@oes.edu.et', '2233', 'instructor', 'IT');

INSERT INTO instructors (user_id, employee_id, qualification, experience_years) VALUES
(2, 'ETUSE/004/2013', 'MSc in Computer Science', 8),
(3, 'ETUSE/045/2013', 'MSc in Information Technology', 6);

-- Insert sample students
INSERT INTO users (user_id, first_name, last_name, email, password, role, department) VALUES
('STU001', 'Sara', 'Simeles', 'sara.simeles@oes.edu.et', '1234', 'student', 'IT'),
('STU002', 'Mehalet', 'Temesgen', 'mehalet.temesgen@oes.edu.et', '5678', 'student', 'IT'),
('STU003', 'Firehiwot', 'Birhanu', 'firehiwot.birhanu@oes.edu.et', '6789', 'student', 'IT');

INSERT INTO students (user_id, student_id, year_of_study, section, gpa) VALUES
(4, 'ETUSE/061/2013', 4, 'A', 3.8),
(5, 'ETUSE/023/2013', 4, 'A', 3.6),
(6, 'ETUSE/013/2013', 3, 'B', 3.4);

-- Insert sample courses
INSERT INTO courses (course_code, course_name, department, credits, instructor_id, description) VALUES
('ITEC401', 'Web Development', 'IT', 4, 2, 'HTML, CSS, JavaScript, PHP'),
('ITEC302', 'Database Systems', 'IT', 3, 2, 'SQL, Normalization, ER Diagrams'),
('ITEC305', 'Computer Networks', 'IT', 3, 3, 'OSI Model, TCP/IP, Protocols');

-- Insert sample questions
INSERT INTO questions (question_text, difficulty, course_id, instructor_id) VALUES
('What does HTML stand for?', 'Easy', 1, 2),
('Which SQL statement extracts data?', 'Medium', 2, 2),
('What is the default port for HTTP?', 'Easy', 3, 3);

-- Insert question options
INSERT INTO question_options (question_id, option_letter, option_text, is_correct) VALUES
(1, 'A', 'Hyper Text Markup Language', TRUE),
(1, 'B', 'High Tech Markup Language', FALSE),
(1, 'C', 'Hyper Transfer Markup Language', FALSE),
(1, 'D', 'Hyper Text Machine Language', FALSE),
(2, 'A', 'SELECT', TRUE),
(2, 'B', 'INSERT', FALSE),
(2, 'C', 'UPDATE', FALSE),
(2, 'D', 'DELETE', FALSE),
(3, 'A', '80', TRUE),
(3, 'B', '443', FALSE),
(3, 'C', '21', FALSE),
(3, 'D', '22', FALSE);