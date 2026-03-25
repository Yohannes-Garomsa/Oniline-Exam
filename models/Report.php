<?php
require_once __DIR__ . '/../config/database.php';

class Report {
    private $conn;
    private $users_table = "users";
    private $exams_table = "exams";
    private $attempts_table = "exam_attempts";
    private $courses_table = "courses";
    private $students_table = "students";
    private $instructors_table = "instructors";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Convert an array of fields to a properly escaped CSV line
     * @param array $fields
     * @return string
     */
    private function arrayToCsvRow($fields) {
        $processed = [];
        foreach ($fields as $field) {
            // Convert null to empty string
            $field = $field ?? '';
            // If field contains comma, double-quote, or newline, enclose in double quotes
            if (preg_match('/[,"\n\r]/', $field)) {
                // Double up any double quotes
                $field = str_replace('"', '""', $field);
                $field = '"' . $field . '"';
            }
            $processed[] = $field;
        }
        return implode(',', $processed) . "\n";
    }

    // ==================== ADMIN REPORTS ====================

    // Get system overview stats
    public function getSystemStats() {
        $stats = [];

        // Total users
        $users_query = "SELECT 
                          COUNT(*) as total_users,
                          SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as total_students,
                          SUM(CASE WHEN role = 'instructor' THEN 1 ELSE 0 END) as total_instructors,
                          SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins
                        FROM " . $this->users_table;
        $users_stmt = $this->conn->query($users_query);
        $stats['users'] = $users_stmt->fetch(PDO::FETCH_ASSOC);

        // Total exams
        $exams_query = "SELECT 
                          COUNT(*) as total_exams,
                          SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_exams,
                          SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_exams,
                          SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_exams
                        FROM " . $this->exams_table;
        $exams_stmt = $this->conn->query($exams_query);
        $stats['exams'] = $exams_stmt->fetch(PDO::FETCH_ASSOC);

        // Total attempts
        $attempts_query = "SELECT 
                            COUNT(*) as total_attempts,
                            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending_grading,
                            SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) as graded
                          FROM " . $this->attempts_table;
        $attempts_stmt = $this->conn->query($attempts_query);
        $stats['attempts'] = $attempts_stmt->fetch(PDO::FETCH_ASSOC);

        // Average score across all exams
        $score_query = "SELECT 
                          AVG(percentage) as average_score,
                          MAX(percentage) as highest_score,
                          MIN(percentage) as lowest_score
                        FROM " . $this->attempts_table . " 
                        WHERE status = 'graded'";
        $score_stmt = $this->conn->query($score_query);
        $stats['scores'] = $score_stmt->fetch(PDO::FETCH_ASSOC);

        // Recent activity (last 30 days)
        $activity_query = "SELECT 
                            DATE(created_at) as date,
                            COUNT(*) as new_users
                          FROM " . $this->users_table . "
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                          GROUP BY DATE(created_at)
                          ORDER BY date DESC";
        $activity_stmt = $this->conn->query($activity_query);
        $stats['recent_activity'] = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    // Get user growth over time
    public function getUserGrowth($period = 'monthly') {
        $interval = ($period == 'monthly') ? '%Y-%m' : '%Y-%m-%d';
        
        $query = "SELECT 
                    DATE_FORMAT(created_at, :interval) as period,
                    COUNT(*) as new_users,
                    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
                    SUM(CASE WHEN role = 'instructor' THEN 1 ELSE 0 END) as instructors
                  FROM " . $this->users_table . "
                  GROUP BY period
                  ORDER BY period DESC
                  LIMIT 12";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':interval', $interval);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get exam activity over time
    public function getExamActivity($period = 'monthly') {
        $interval = ($period == 'monthly') ? '%Y-%m' : '%Y-%m-%d';
        
        $query = "SELECT 
                    DATE_FORMAT(created_at, :interval) as period,
                    COUNT(*) as exams_created,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as exams_active
                  FROM " . $this->exams_table . "
                  GROUP BY period
                  ORDER BY period DESC
                  LIMIT 12";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':interval', $interval);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get department statistics
    public function getDepartmentStats() {
        $query = "SELECT 
                    c.department,
                    COUNT(DISTINCT c.id) as total_courses,
                    COUNT(DISTINCT e.id) as total_exams,
                    COUNT(DISTINCT u.id) as total_students,
                    AVG(a.percentage) as average_score,
                    SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) as passed_count
                  FROM courses c
                  LEFT JOIN exams e ON c.id = e.course_id
                  LEFT JOIN users u ON u.department = c.department AND u.role = 'student'
                  LEFT JOIN exam_attempts a ON e.id = a.exam_id AND a.status = 'graded'
                  GROUP BY c.department";

        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== INSTRUCTOR REPORTS ====================

    // Get instructor performance overview
    public function getInstructorStats($instructor_id) {
        $stats = [];

        // Courses taught
        $courses_query = "SELECT 
                            COUNT(*) as total_courses,
                            SUM(credits) as total_credits
                          FROM courses 
                          WHERE instructor_id = :instructor_id";
        $courses_stmt = $this->conn->prepare($courses_query);
        $courses_stmt->bindParam(':instructor_id', $instructor_id);
        $courses_stmt->execute();
        $stats['courses'] = $courses_stmt->fetch(PDO::FETCH_ASSOC);

        // Exams created
        $exams_query = "SELECT 
                          COUNT(*) as total_exams,
                          SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_exams,
                          SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_exams
                        FROM exams 
                        WHERE instructor_id = :instructor_id";
        $exams_stmt = $this->conn->prepare($exams_query);
        $exams_stmt->bindParam(':instructor_id', $instructor_id);
        $exams_stmt->execute();
        $stats['exams'] = $exams_stmt->fetch(PDO::FETCH_ASSOC);

        // Student performance
        $performance_query = "SELECT 
                                AVG(a.percentage) as average_score,
                                COUNT(DISTINCT a.student_id) as total_students,
                                SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) as passed_count
                              FROM exams e
                              LEFT JOIN exam_attempts a ON e.id = a.exam_id AND a.status = 'graded'
                              WHERE e.instructor_id = :instructor_id";
        $performance_stmt = $this->conn->prepare($performance_query);
        $performance_stmt->bindParam(':instructor_id', $instructor_id);
        $performance_stmt->execute();
        $stats['performance'] = $performance_stmt->fetch(PDO::FETCH_ASSOC);

        return $stats;
    }

    // Get exam performance for instructor
    public function getExamPerformance($instructor_id, $exam_id = null) {
        $query = "SELECT 
                    e.id,
                    e.exam_title,
                    c.course_code,
                    c.course_name,
                    COUNT(DISTINCT a.id) as total_attempts,
                    COUNT(DISTINCT a.student_id) as unique_students,
                    AVG(a.percentage) as average_score,
                    MAX(a.percentage) as highest_score,
                    MIN(a.percentage) as lowest_score,
                    SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) as passed_count,
                    SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) as pending_grading
                  FROM exams e
                  JOIN courses c ON e.course_id = c.id
                  LEFT JOIN exam_attempts a ON e.id = a.exam_id
                  WHERE e.instructor_id = :instructor_id";

        if($exam_id) {
            $query .= " AND e.id = :exam_id";
        }

        $query .= " GROUP BY e.id ORDER BY e.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instructor_id', $instructor_id);
        
        if($exam_id) {
            $stmt->bindParam(':exam_id', $exam_id);
        }
        
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get question performance analysis
    public function getQuestionPerformance($exam_id, $instructor_id) {
        // Verify instructor owns exam
        $verify_query = "SELECT id FROM exams 
                        WHERE id = :exam_id AND instructor_id = :instructor_id";
        $verify_stmt = $this->conn->prepare($verify_query);
        $verify_stmt->bindParam(':exam_id', $exam_id);
        $verify_stmt->bindParam(':instructor_id', $instructor_id);
        $verify_stmt->execute();

        if($verify_stmt->rowCount() == 0) {
            return false;
        }

        $query = "SELECT 
                    q.id as question_id,
                    q.question_text,
                    q.question_type,
                    q.difficulty,
                    eq.marks,
                    COUNT(DISTINCT sa.id) as times_answered,
                    AVG(sa.marks_obtained) as average_marks,
                    AVG(CASE WHEN sa.is_correct = 1 THEN 1 ELSE 0 END) * 100 as correct_percentage,
                    SUM(CASE WHEN sa.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                    SUM(CASE WHEN sa.is_correct = 0 THEN 1 ELSE 0 END) as incorrect_count
                  FROM exam_questions eq
                  JOIN questions q ON eq.question_id = q.id
                  LEFT JOIN student_answers sa ON q.id = sa.question_id
                  LEFT JOIN exam_attempts a ON sa.attempt_id = a.id AND a.exam_id = :exam_id
                  WHERE eq.exam_id = :exam_id
                  GROUP BY q.id
                  ORDER BY eq.question_order";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get student list with performance for an exam
    public function getStudentPerformance($exam_id, $instructor_id) {
        // Verify instructor owns exam
        $verify_query = "SELECT id FROM exams 
                        WHERE id = :exam_id AND instructor_id = :instructor_id";
        $verify_stmt = $this->conn->prepare($verify_query);
        $verify_stmt->bindParam(':exam_id', $exam_id);
        $verify_stmt->bindParam(':instructor_id', $instructor_id);
        $verify_stmt->execute();

        if($verify_stmt->rowCount() == 0) {
            return false;
        }

        $query = "SELECT 
                    u.id as student_id,
                    u.first_name,
                    u.last_name,
                    u.user_id,
                    a.id as attempt_id,
                    a.total_score,
                    a.percentage,
                    a.passed,
                    a.start_time,
                    a.end_time,
                    a.status,
                    TIMEDIFF(a.end_time, a.start_time) as time_taken
                  FROM exam_attempts a
                  JOIN users u ON a.student_id = u.id
                  WHERE a.exam_id = :exam_id
                  ORDER BY a.percentage DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== STUDENT REPORTS ====================

    // Get student progress report
    public function getStudentProgress($student_id) {
        $stats = [];

        // Overall stats
        $overall_query = "SELECT 
                            COUNT(DISTINCT exam_id) as total_exams_attempted,
                            COUNT(*) as total_attempts,
                            AVG(percentage) as average_score,
                            MAX(percentage) as best_score,
                            MIN(percentage) as lowest_score,
                            SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as exams_passed,
                            SUM(CASE WHEN passed = 0 AND status = 'graded' THEN 1 ELSE 0 END) as exams_failed
                          FROM exam_attempts
                          WHERE student_id = :student_id AND status = 'graded'";

        $overall_stmt = $this->conn->prepare($overall_query);
        $overall_stmt->bindParam(':student_id', $student_id);
        $overall_stmt->execute();
        $stats['overall'] = $overall_stmt->fetch(PDO::FETCH_ASSOC);

        // Performance by course
        $courses_query = "SELECT 
                            c.course_code,
                            c.course_name,
                            COUNT(DISTINCT e.id) as exams_taken,
                            AVG(a.percentage) as average_score,
                            SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) as passed_count
                          FROM exam_attempts a
                          JOIN exams e ON a.exam_id = e.id
                          JOIN courses c ON e.course_id = c.id
                          WHERE a.student_id = :student_id AND a.status = 'graded'
                          GROUP BY c.id
                          ORDER BY average_score DESC";

        $courses_stmt = $this->conn->prepare($courses_query);
        $courses_stmt->bindParam(':student_id', $student_id);
        $courses_stmt->execute();
        $stats['by_course'] = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent exams
        $recent_query = "SELECT 
                            e.exam_title,
                            c.course_code,
                            a.percentage,
                            a.passed,
                            a.end_time
                          FROM exam_attempts a
                          JOIN exams e ON a.exam_id = e.id
                          JOIN courses c ON e.course_id = c.id
                          WHERE a.student_id = :student_id AND a.status = 'graded'
                          ORDER BY a.end_time DESC
                          LIMIT 10";

        $recent_stmt = $this->conn->prepare($recent_query);
        $recent_stmt->bindParam(':student_id', $student_id);
        $recent_stmt->execute();
        $stats['recent'] = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Performance trend over time
        $trend_query = "SELECT 
                          DATE_FORMAT(a.end_time, '%Y-%m') as month,
                          AVG(a.percentage) as monthly_average,
                          COUNT(*) as exams_taken
                        FROM exam_attempts a
                        WHERE a.student_id = :student_id AND a.status = 'graded'
                        GROUP BY month
                        ORDER BY month DESC
                        LIMIT 12";

        $trend_stmt = $this->conn->prepare($trend_query);
        $trend_stmt->bindParam(':student_id', $student_id);
        $trend_stmt->execute();
        $stats['trend'] = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    // ==================== EXPORT FUNCTIONS ====================

    // Export exam results to CSV
    public function exportExamResults($exam_id, $instructor_id) {
        // Verify instructor owns exam
        $verify_query = "SELECT e.*, c.course_code 
                        FROM exams e
                        JOIN courses c ON e.course_id = c.id
                        WHERE e.id = :exam_id AND e.instructor_id = :instructor_id";
        $verify_stmt = $this->conn->prepare($verify_query);
        $verify_stmt->bindParam(':exam_id', $exam_id);
        $verify_stmt->bindParam(':instructor_id', $instructor_id);
        $verify_stmt->execute();

        if($verify_stmt->rowCount() == 0) {
            return false;
        }

        $exam = $verify_stmt->fetch(PDO::FETCH_ASSOC);

        // Get all graded attempts
        $query = "SELECT 
                    u.user_id as student_id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    a.total_score,
                    a.percentage,
                    a.passed,
                    a.start_time,
                    a.end_time,
                    TIMEDIFF(a.end_time, a.start_time) as duration
                  FROM exam_attempts a
                  JOIN users u ON a.student_id = u.id
                  WHERE a.exam_id = :exam_id AND a.status = 'graded'
                  ORDER BY a.percentage DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate CSV content with proper escaping
        $csv = "Exam: " . $exam['exam_title'] . " (" . $exam['course_code'] . ")\n";
        $csv .= "Total Marks: " . $exam['total_marks'] . ", Passing Score: " . $exam['passing_score'] . "%\n\n";
        $csv .= "Student ID,First Name,Last Name,Email,Score,Percentage,Passed,Start Time,End Time,Duration\n";

        foreach($results as $row) {
            $fields = [
                $row['student_id'],
                $row['first_name'],
                $row['last_name'],
                $row['email'],
                $row['total_score'],
                round($row['percentage'], 2) . '%',
                $row['passed'] ? 'Yes' : 'No',
                $row['start_time'],
                $row['end_time'],
                $row['duration']
            ];
            $csv .= $this->arrayToCsvRow($fields);
        }

        return $csv;
    }

    // Export all students list
    public function exportStudentsList() {
        $query = "SELECT 
                    u.user_id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.phone,
                    u.department,
                    s.year_of_study,
                    s.section,
                    s.gpa,
                    u.created_at,
                    u.status,
                    (SELECT COUNT(*) FROM exam_attempts WHERE student_id = u.id) as exams_taken,
                    (SELECT AVG(percentage) FROM exam_attempts 
                     WHERE student_id = u.id AND status = 'graded') as average_score
                  FROM users u
                  LEFT JOIN students s ON u.id = s.user_id
                  WHERE u.role = 'student'
                  ORDER BY u.created_at DESC";

        $stmt = $this->conn->query($query);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate CSV content with proper escaping
        $csv = "Student ID,First Name,Last Name,Email,Phone,Department,Year,Section,GPA,Registered,Status,Exams Taken,Average Score\n";

        foreach($students as $student) {
            $fields = [
                $student['user_id'],
                $student['first_name'],
                $student['last_name'],
                $student['email'],
                $student['phone'] ?? 'N/A',
                $student['department'] ?? 'N/A',
                $student['year_of_study'] ?? 'N/A',
                $student['section'] ?? 'N/A',
                $student['gpa'] ?? 'N/A',
                $student['created_at'],
                $student['status'],
                $student['exams_taken'] ?? 0,
                round($student['average_score'] ?? 0, 2) . '%'
            ];
            $csv .= $this->arrayToCsvRow($fields);
        }

        return $csv;
    }
}
?>