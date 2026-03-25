<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "users";

    // Common user fields
    public $id;
    public $user_id;
    public $first_name;
    public $last_name;
    public $email;
    public $password;
    public $phone;
    public $role;
    public $department;
    public $status;

    // Student‑specific fields
    public $student_id;      // matches user_id for students
    public $year_of_study;
    public $section;
    public $gpa;

    // Instructor‑specific fields
    public $employee_id;      // matches user_id for instructors
    public $qualification;
    public $experience_years;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Login user by email/user_id and password
     */
public function login($email, $password, $role) {
    $query = "SELECT id, user_id, first_name, last_name, email, password, role, department 
              FROM " . $this->table_name . "
              WHERE (email = :email OR user_id = :email)
              AND role = :role
              AND status = 'active'
              LIMIT 1";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);
    $stmt->execute();

    error_log("Login query executed for email=$email, role=$role, rowCount=" . $stmt->rowCount());

    if($stmt->rowCount() == 1){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("User found: id={$row['id']}, email={$row['email']}, role={$row['role']}, hash={$row['password']}");
        
        if(password_verify($password, $row['password']) || $password === $row['password']){
            error_log("Password verify SUCCESS");
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->email = $row['email'];
            $this->role = $row['role'];
            $this->department = $row['department'];
            return true;
        } else {
            error_log("Password verify FAILED");
        }
    } else {
        error_log("No user found with given email/role or status not active");
    }
    return false;
}
    /**
     * Register a new user (with role‑specific details)
     */
    public function register() {
        // Check if email already exists
        if ($this->emailExists()) {
            return false;
        }

        // Begin transaction
        $this->conn->beginTransaction();

        try {
            // Hash password
            $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);

            // Generate user_id if not provided
            if (empty($this->user_id)) {
                $this->user_id = $this->generateUserId();
            }

            // Insert into users table
            $query = "INSERT INTO " . $this->table_name . "
                      SET user_id     = :user_id,
                          first_name  = :first_name,
                          last_name   = :last_name,
                          email       = :email,
                          password    = :password,
                          role        = :role,
                          department  = :department,
                          phone       = :phone,
                          status      = 'active'";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $this->user_id);
            $stmt->bindParam(':first_name', $this->first_name);
            $stmt->bindParam(':last_name', $this->last_name);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':role', $this->role);
            $stmt->bindParam(':department', $this->department);
            $stmt->bindParam(':phone', $this->phone);
            $stmt->execute();

            $userId = $this->conn->lastInsertId();
            $this->id = $userId;

            // Insert role‑specific data
            if ($this->role === 'student') {
                $this->addStudentDetails($userId);
            } elseif ($this->role === 'instructor') {
                $this->addInstructorDetails($userId);
            }
            // Admin has no extra table

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if email already exists
     */
    private function emailExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Generate a unique user ID based on role
     */
    private function generateUserId() {
        if ($this->role === 'student') {
            $prefix = 'STU';
        } elseif ($this->role === 'instructor') {
            $prefix = 'INS';
        } else {
            $prefix = 'ADM';
        }

        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE role = :role";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $this->role);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $row['count'] + 1;

        return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Insert student‑specific details (uses class properties)
     */
    private function addStudentDetails($userId) {
        $query = "INSERT INTO students (user_id, student_id, year_of_study, section, gpa)
                  VALUES (:user_id, :student_id, :year, :section, :gpa)";
        $stmt = $this->conn->prepare($query);

        // Use the stored properties (set from outside)
        $studentId = $this->student_id ?: $this->user_id;  // fallback to user_id if not set
        $year      = $this->year_of_study ?? 1;
        $section   = $this->section ?? 'A';
        $gpa       = $this->gpa ?? null;

        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':student_id', $studentId);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':section', $section);
        $stmt->bindParam(':gpa', $gpa);
        $stmt->execute();
    }

    /**
     * Insert instructor‑specific details (uses class properties)
     */
    private function addInstructorDetails($userId) {
        $query = "INSERT INTO instructors (user_id, employee_id, qualification, experience_years)
                  VALUES (:user_id, :employee_id, :qualification, :experience)";
        $stmt = $this->conn->prepare($query);

        $employeeId    = $this->employee_id ?: $this->user_id;
        $qualification = $this->qualification ?? '';
        $experience    = $this->experience_years ?? 0;

        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':employee_id', $employeeId);
        $stmt->bindParam(':qualification', $qualification);
        $stmt->bindParam(':experience', $experience);
        $stmt->execute();
    }

    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}