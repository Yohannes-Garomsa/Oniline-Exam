<?php
require_once __DIR__ . '/../config/database.php';

class Exam {
    private $conn;
    private $table_name = "exams";

    // Exam properties
    public $id;
    public $exam_title;
    public $course_id;
    public $instructor_id;
    public $description;
    public $exam_type;
    public $total_marks;
    public $passing_score;
    public $duration_minutes;
    public $available_from;
    public $available_until;
    public $randomize_questions;
    public $show_results;
    public $attempts_allowed;
    public $status;
    public $exam_password;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Create new exam
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET exam_title=:exam_title, course_id=:course_id,
                      instructor_id=:instructor_id, description=:description,
                      exam_type=:exam_type, total_marks=:total_marks,
                      passing_score=:passing_score, duration_minutes=:duration_minutes,
                      available_from=:available_from, available_until=:available_until,
                      randomize_questions=:randomize_questions, show_results=:show_results,
                      attempts_allowed=:attempts_allowed, status=:status,
                      exam_password=:exam_password";

        $stmt = $this->conn->prepare($query);

        // Clean and sanitize data
        $this->exam_title = htmlspecialchars(strip_tags($this->exam_title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->exam_password = !empty($this->exam_password) ? 
                               password_hash($this->exam_password, PASSWORD_DEFAULT) : null;

        // Bind parameters
        $stmt->bindParam(':exam_title', $this->exam_title);
        $stmt->bindParam(':course_id', $this->course_id);
        $stmt->bindParam(':instructor_id', $this->instructor_id);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':exam_type', $this->exam_type);
        $stmt->bindParam(':total_marks', $this->total_marks);
        $stmt->bindParam(':passing_score', $this->passing_score);
        $stmt->bindParam(':duration_minutes', $this->duration_minutes);
        $stmt->bindParam(':available_from', $this->available_from);
        $stmt->bindParam(':available_until', $this->available_until);
        $stmt->bindParam(':randomize_questions', $this->randomize_questions);
        $stmt->bindParam(':show_results', $this->show_results);
        $stmt->bindParam(':attempts_allowed', $this->attempts_allowed);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':exam_password', $this->exam_password);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // Get all exams (for instructor)
    public function getInstructorExams($instructor_id) {
        $query = "SELECT e.*, c.course_code, c.course_name,
                         (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as question_count
                  FROM " . $this->table_name . " e
                  LEFT JOIN courses c ON e.course_id = c.id
                  WHERE e.instructor_id = :instructor_id
                  ORDER BY e.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instructor_id', $instructor_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get available exams for students
    public function getAvailableExams($student_id, $department) {
        $query = "SELECT e.*, c.course_code, c.course_name,
                         i.first_name as instructor_first, i.last_name as instructor_last,
                         (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as total_questions,
                         (SELECT COUNT(*) FROM exam_attempts 
                          WHERE exam_id = e.id AND student_id = :student_id) as attempts_count
                  FROM " . $this->table_name . " e
                  LEFT JOIN courses c ON e.course_id = c.id
                  LEFT JOIN users i ON e.instructor_id = i.id
                  WHERE e.status = 'active' 
                  AND e.available_from <= NOW() 
                  AND e.available_until >= NOW()
                  AND (c.department = :department OR :department = 'all')
                  ORDER BY e.available_until ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':department', $department);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get single exam by ID
    public function getExamById($id) {
        $query = "SELECT e.*, c.course_code, c.course_name,
                         u.first_name as instructor_first, u.last_name as instructor_last
                  FROM " . $this->table_name . " e
                  LEFT JOIN courses c ON e.course_id = c.id
                  LEFT JOIN users u ON e.instructor_id = u.id
                  WHERE e.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update exam
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET exam_title=:exam_title, description=:description,
                      exam_type=:exam_type, total_marks=:total_marks,
                      passing_score=:passing_score, duration_minutes=:duration_minutes,
                      available_from=:available_from, available_until=:available_until,
                      randomize_questions=:randomize_questions, show_results=:show_results,
                      attempts_allowed=:attempts_allowed, status=:status
                  WHERE id=:id AND instructor_id=:instructor_id";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->exam_title = htmlspecialchars(strip_tags($this->exam_title));
        $this->description = htmlspecialchars(strip_tags($this->description));

        // Bind parameters
        $stmt->bindParam(':exam_title', $this->exam_title);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':exam_type', $this->exam_type);
        $stmt->bindParam(':total_marks', $this->total_marks);
        $stmt->bindParam(':passing_score', $this->passing_score);
        $stmt->bindParam(':duration_minutes', $this->duration_minutes);
        $stmt->bindParam(':available_from', $this->available_from);
        $stmt->bindParam(':available_until', $this->available_until);
        $stmt->bindParam(':randomize_questions', $this->randomize_questions);
        $stmt->bindParam(':show_results', $this->show_results);
        $stmt->bindParam(':attempts_allowed', $this->attempts_allowed);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':instructor_id', $this->instructor_id);

        return $stmt->execute();
    }

    // Delete exam
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE id = :id AND instructor_id = :instructor_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':instructor_id', $this->instructor_id);
        
        return $stmt->execute();
    }

    // Update exam status
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Get exam statistics
    public function getExamStats($exam_id) {
        $query = "SELECT 
                    COUNT(DISTINCT student_id) as total_students,
                    COUNT(*) as total_attempts,
                    AVG(total_score) as average_score,
                    MAX(total_score) as highest_score,
                    MIN(total_score) as lowest_score,
                    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_count,
                    SUM(CASE WHEN passed = 0 AND status = 'graded' THEN 1 ELSE 0 END) as failed_count
                  FROM exam_attempts 
                  WHERE exam_id = :exam_id AND status = 'graded'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get pending submissions count
    public function getPendingCount($instructor_id) {
        $query = "SELECT COUNT(*) as pending_count
                  FROM exam_attempts ea
                  JOIN " . $this->table_name . " e ON ea.exam_id = e.id
                  WHERE e.instructor_id = :instructor_id 
                  AND ea.status = 'submitted'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instructor_id', $instructor_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['pending_count'];
    }
}
?>