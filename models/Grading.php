<?php
require_once __DIR__ . '/../config/database.php';

class Grading {
    private $conn;
    private $attempts_table = "exam_attempts";
    private $answers_table = "student_answers";
    private $exams_table = "exams";
    private $questions_table = "exam_questions";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Get all submissions pending grading for an instructor
    public function getPendingSubmissions($instructor_id, $exam_id = null) {
        $query = "SELECT 
                    a.id as attempt_id,
                    a.exam_id,
                    a.student_id,
                    a.start_time,
                    a.end_time,
                    a.status,
                    u.first_name,
                    u.last_name,
                    u.user_id,
                    e.exam_title,
                    e.course_id,
                    c.course_code,
                    c.course_name,
                    (SELECT COUNT(*) FROM " . $this->answers_table . " 
                     WHERE attempt_id = a.id AND is_correct IS NULL) as pending_count,
                    (SELECT COUNT(*) FROM " . $this->questions_table . " 
                     WHERE exam_id = a.id) as total_questions
                  FROM " . $this->attempts_table . " a
                  JOIN users u ON a.student_id = u.id
                  JOIN " . $this->exams_table . " e ON a.exam_id = e.id
                  JOIN courses c ON e.course_id = c.id
                  WHERE e.instructor_id = :instructor_id
                  AND a.status = 'submitted'";

        if($exam_id) {
            $query .= " AND a.exam_id = :exam_id";
        }

        $query .= " ORDER BY a.end_time DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instructor_id', $instructor_id);
        
        if($exam_id) {
            $stmt->bindParam(':exam_id', $exam_id);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get submission details for grading
    public function getSubmissionForGrading($attempt_id, $instructor_id) {
        // Verify instructor owns this exam
        $verify_query = "SELECT a.*, e.instructor_id, e.exam_title, e.total_marks
                        FROM " . $this->attempts_table . " a
                        JOIN " . $this->exams_table . " e ON a.exam_id = e.id
                        WHERE a.id = :attempt_id";
        
        $verify_stmt = $this->conn->prepare($verify_query);
        $verify_stmt->bindParam(':attempt_id', $attempt_id);
        $verify_stmt->execute();
        
        if($verify_stmt->rowCount() == 0) {
            return false;
        }
        
        $attempt = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($attempt['instructor_id'] != $instructor_id) {
            return false;
        }

        // Get student details
        $student_query = "SELECT id, first_name, last_name, user_id, email, department
                         FROM users WHERE id = :student_id";
        $student_stmt = $this->conn->prepare($student_query);
        $student_stmt->bindParam(':student_id', $attempt['student_id']);
        $student_stmt->execute();
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

        // Get all questions with student answers
        $questions_query = "SELECT 
                              q.id as question_id,
                              q.question_text,
                              q.question_type,
                              q.explanation as correct_explanation,
                              eq.marks as max_marks,
                              sa.id as answer_id,
                              sa.selected_option,
                              sa.answer_text,
                              sa.marks_obtained,
                              sa.is_correct,
                              sa.feedback,
                              (SELECT option_text FROM question_options 
                               WHERE question_id = q.id AND is_correct = 1 LIMIT 1) as correct_answer
                            FROM " . $this->questions_table . " eq
                            JOIN questions q ON eq.question_id = q.id
                            LEFT JOIN " . $this->answers_table . " sa 
                                ON sa.question_id = q.id AND sa.attempt_id = :attempt_id
                            WHERE eq.exam_id = :exam_id
                            ORDER BY eq.question_order";

        $questions_stmt = $this->conn->prepare($questions_query);
        $questions_stmt->bindParam(':attempt_id', $attempt_id);
        $questions_stmt->bindParam(':exam_id', $attempt['exam_id']);
        $questions_stmt->execute();
        $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get options for MCQ questions
        foreach($questions as &$question) {
            if($question['question_type'] == 'MCQ') {
                $options_query = "SELECT option_letter, option_text, is_correct
                                 FROM question_options
                                 WHERE question_id = :question_id
                                 ORDER BY option_letter";
                $options_stmt = $this->conn->prepare($options_query);
                $options_stmt->bindParam(':question_id', $question['question_id']);
                $options_stmt->execute();
                $question['options'] = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return [
            'attempt' => $attempt,
            'student' => $student,
            'questions' => $questions
        ];
    }

    // Save grade for a single question
    public function saveQuestionGrade($answer_id, $marks_obtained, $feedback = null) {
        $query = "UPDATE " . $this->answers_table . "
                  SET marks_obtained = :marks_obtained,
                      feedback = :feedback,
                      is_correct = CASE 
                          WHEN marks_obtained > 0 THEN 1 
                          ELSE 0 
                      END
                  WHERE id = :answer_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':marks_obtained', $marks_obtained);
        $stmt->bindParam(':feedback', $feedback);
        $stmt->bindParam(':answer_id', $answer_id);

        return $stmt->execute();
    }

    // Submit all grades and finalize attempt
    public function finalizeGrading($attempt_id) {
        // Calculate total score
        $calc_query = "SELECT SUM(marks_obtained) as total_score,
                              SUM(max_marks) as total_max
                       FROM (
                           SELECT sa.marks_obtained, eq.marks as max_marks
                           FROM " . $this->answers_table . " sa
                           JOIN " . $this->questions_table . " eq 
                               ON sa.question_id = eq.question_id AND eq.exam_id = sa.exam_id
                           WHERE sa.attempt_id = :attempt_id
                       ) as scores";

        $calc_stmt = $this->conn->prepare($calc_query);
        $calc_stmt->bindParam(':attempt_id', $attempt_id);
        $calc_stmt->execute();
        $scores = $calc_stmt->fetch(PDO::FETCH_ASSOC);

        // Get exam details for passing score
        $exam_query = "SELECT e.passing_score, e.total_marks
                       FROM " . $this->attempts_table . " a
                       JOIN " . $this->exams_table . " e ON a.exam_id = e.id
                       WHERE a.id = :attempt_id";

        $exam_stmt = $this->conn->prepare($exam_query);
        $exam_stmt->bindParam(':attempt_id', $attempt_id);
        $exam_stmt->execute();
        $exam = $exam_stmt->fetch(PDO::FETCH_ASSOC);

        $percentage = ($scores['total_score'] / $exam['total_marks']) * 100;
        $passed = ($percentage >= $exam['passing_score']) ? 1 : 0;

        // Update attempt
        $update_query = "UPDATE " . $this->attempts_table . "
                        SET total_score = :total_score,
                            percentage = :percentage,
                            passed = :passed,
                            status = 'graded'
                        WHERE id = :attempt_id";

        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->bindParam(':total_score', $scores['total_score']);
        $update_stmt->bindParam(':percentage', $percentage);
        $update_stmt->bindParam(':passed', $passed);
        $update_stmt->bindParam(':attempt_id', $attempt_id);

        return $update_stmt->execute();
    }

    // Save draft grading (without finalizing)
    public function saveGradingDraft($attempt_id, $grades) {
        $this->conn->beginTransaction();

        try {
            foreach($grades as $grade) {
                if(isset($grade['answer_id'])) {
                    $this->saveQuestionGrade(
                        $grade['answer_id'],
                        $grade['marks_obtained'],
                        $grade['feedback'] ?? null
                    );
                }
            }
            
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Get grading statistics for an exam
    public function getGradingStats($exam_id, $instructor_id) {
        // Verify instructor owns exam
        $verify_query = "SELECT id FROM " . $this->exams_table . "
                        WHERE id = :exam_id AND instructor_id = :instructor_id";
        $verify_stmt = $this->conn->prepare($verify_query);
        $verify_stmt->bindParam(':exam_id', $exam_id);
        $verify_stmt->bindParam(':instructor_id', $instructor_id);
        $verify_stmt->execute();

        if($verify_stmt->rowCount() == 0) {
            return false;
        }

        $stats = [];

        // Total submissions
        $total_query = "SELECT 
                          COUNT(*) as total_submissions,
                          SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending,
                          SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) as graded
                        FROM " . $this->attempts_table . "
                        WHERE exam_id = :exam_id";
        
        $total_stmt = $this->conn->prepare($total_query);
        $total_stmt->bindParam(':exam_id', $exam_id);
        $total_stmt->execute();
        $stats['submissions'] = $total_stmt->fetch(PDO::FETCH_ASSOC);

        // Average score
        $avg_query = "SELECT 
                        AVG(total_score) as avg_score,
                        AVG(percentage) as avg_percentage,
                        MAX(total_score) as highest_score,
                        MIN(total_score) as lowest_score,
                        SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_count
                      FROM " . $this->attempts_table . "
                      WHERE exam_id = :exam_id AND status = 'graded'";
        
        $avg_stmt = $this->conn->prepare($avg_query);
        $avg_stmt->bindParam(':exam_id', $exam_id);
        $avg_stmt->execute();
        $stats['performance'] = $avg_stmt->fetch(PDO::FETCH_ASSOC);

        // Score distribution
        $dist_query = "SELECT 
                          SUM(CASE WHEN percentage >= 90 THEN 1 ELSE 0 END) as A,
                          SUM(CASE WHEN percentage >= 80 AND percentage < 90 THEN 1 ELSE 0 END) as B,
                          SUM(CASE WHEN percentage >= 70 AND percentage < 80 THEN 1 ELSE 0 END) as C,
                          SUM(CASE WHEN percentage >= 60 AND percentage < 70 THEN 1 ELSE 0 END) as D,
                          SUM(CASE WHEN percentage < 60 THEN 1 ELSE 0 END) as F
                        FROM " . $this->attempts_table . "
                        WHERE exam_id = :exam_id AND status = 'graded'";
        
        $dist_stmt = $this->conn->prepare($dist_query);
        $dist_stmt->bindParam(':exam_id', $exam_id);
        $dist_stmt->execute();
        $stats['distribution'] = $dist_stmt->fetch(PDO::FETCH_ASSOC);

        return $stats;
    }

    // Get all graded submissions for an exam
    public function getGradedSubmissions($exam_id, $instructor_id) {
        $query = "SELECT 
                    a.id as attempt_id,
                    a.student_id,
                    a.total_score,
                    a.percentage,
                    a.passed,
                    a.end_time,
                    u.first_name,
                    u.last_name,
                    u.user_id,
                    (SELECT COUNT(*) FROM " . $this->answers_table . " 
                     WHERE attempt_id = a.id) as answers_count
                  FROM " . $this->attempts_table . " a
                  JOIN users u ON a.student_id = u.id
                  JOIN " . $this->exams_table . " e ON a.exam_id = e.id
                  WHERE a.exam_id = :exam_id 
                    AND e.instructor_id = :instructor_id
                    AND a.status = 'graded'
                  ORDER BY a.percentage DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->bindParam(':instructor_id', $instructor_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>