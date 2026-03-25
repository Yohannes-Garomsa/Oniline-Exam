# Online Exam System - End-to-End Functionality Guide

This document provides a comprehensive end-to-end overview of the Online Exam System's functionality. It breaks down the entire application architecture, explaining each crucial file and the specific features and functions it handles.

## 1. Core Configuration & Setup

### `config/database.php`

- **Purpose**: Manages the connection to the MySQL database.
- **Key Functionality**: Uses PHP Data Objects (PDO) to securely connect to the `online_exam_system` database using TCP/IP (`127.0.0.1`), username `root`, and an empty password for seamless integration with local development environments like XAMPP or the built-in PHP server.

### `database/schema.sql`

- **Purpose**: The architectural blueprint of the database.
- **Key Functionality**: Contains the raw MySQL instructions to create the necessary tables such as `users`, `students`, `instructors`, `exams`, `questions`, `exam_attempts`, and `student_answers`. It also maps the primary and foreign keys linking these relationships together.

### `database/migrate.php`

- **Purpose**: Database automation tool.
- **Key Functionality**: Provides an automated script that connects to the MySQL server, creates the database if it doesn't exist, and seamlessly executes the `schema.sql` file to build the system's tables instantly.

---

## 2. Data Models (The Logic Layer)

The `models/` directory acts as the bridge that interacts with the database tables. Instead of writing SQL everywhere, these files contain object-oriented classes and functions to manage data.

### `models/user.php`

- **Purpose**: Handles all user-related data (Students, Instructors, Admins).
- **Key Functions**:
  - `login($email, $password, $role)`: Authenticates users and verifies hashed passwords.
  - `register()`: Creates a new user account, safely hashes their password using `password_hash()`, and generates a unique ID.
  - `addStudentDetails()` / `addInstructorDetails()`: Inserts specific extra profile information into the specialized `students` or `instructors` tables depending on the user's registered role.
  - `getUserById($id)`: Retrieves a specific user's public info.

### `models/Exam.php`

- **Purpose**: Controls the creation and management of exams.
- **Key Functionality**: Allows instructors to create exams specifying a title, description, duration, and the date/time the exam unlocks and locks. It also manages retrieving a list of available exams for students.

### `models/Question.php`

- **Purpose**: Handles the actual questions inside of exams.
- **Key Functionality**: Allows instructors to attach specific questions (Multiple Choice, True/False, or Short Answer) to a designated Exam ID. Keeps track of the correct answer and the point value of the question.

### `models/ExamAttempt.php`

- **Purpose**: Manages the session when a student takes an exam.
- **Key Functionality**: Records the exact timestamp a student begins an exam, logs their individual answers to specific questions in the `student_answers` table, and marks the attempt as "completed" once they submit.

### `models/Grading.php` & `models/Report.php`

- **Purpose**: Evaluates student performance and generates analytics.
- **Key Functionality**: Automatically cross-references the student's submitted answers with the correct answers stored in `Question.php`. It calculates their total score and allows instructors/admins to export statistical reports about average class grades.

---

## 3. The API Layer (The Messengers)

The `api/` directory acts as the RESTful controllers. These are the URLs that the frontend JavaScript `fetch()` requests talk to.

### `api/auth/`

- **`register.php`**: Receives frontend POST requests, extracts the JSON data, and asks `models/user.php` to save the new user. Returns a JSON success/error.
- **`login.php`**: Takes the email/password JSON, asks `models/user.php` to verify it. If correct, it initiates a secure PHP Session using `$_SESSION` to keep the user logged in.
- **`logout.php`**: Destroys the PHP session and clears the browser's credentials.
- **`validate.php`**: A quick background endpoint the frontend constantly pings to verify the user's session hasn't expired.

### `api/instructor/` & `api/student/` & `api/admin/`

- **Purpose**: These directories contain the restricted endpoints for their specific roles. For example, `api/instructor/create_exam.php` will only execute if the logged-in session belongs to an Instructor, protecting the database from unauthorized data creation.

- **`api/instructor/parse-questions-file.php`**: (added in upload feature) accepts a file upload (CSV, PDF, DOC, DOCX, or TXT) and returns a JSON array representing the parsed questions. Used by the exam creation UI to preview and import questions from a file.

---

## 4. Middleware (Security)

### `middleware/auth.php`

- **Purpose**: Security guard for the API.
- **Key Functionality**: Before any restricted API file (like creating an exam) runs, this file steps in. It checks the PHP Session to ensure the user is actually authenticated. If a student tries to access an instructor API, this middleware forces a `403 Forbidden` error.

---

## 5. Front-End (User Interface)

The `pages/` and `assets/` directories comprise the interactive visual application that the end-user sees in the browser.

### `pages/`

- **`pages/auth/login.html` & `register.html`**: The UI forms where users input their credentials and select their role (Student/Instructor).
- **`pages/student/dashboard.html`**: UI showing available exams, past grades, and the test-taking interface.
- **`pages/instructor/dashboard.html`**: UI for creating new exams, adding questions, and analyzing student performance.

### `assets/js/`

- **`auth.js`**: Listens for the "Submit" click on the registration/login forms. It bundles the user's input into JSON and sends a `fetch()` request to `/api/auth/login.php`.
- **`auth-check.js`**: Runs automatically on every protected HTML page. It pings `/api/auth/validate.php` to see if the user's session is active. If not, it forcefully redirects the user back to the login page to keep the application secure.
