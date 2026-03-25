<?php
require_once __DIR__ . '/../config/database.php';

class ExamAttempt {
    private $conn;
    private $attempts_table = "exam_attempts";
    private $answers_table = "student_answers";
    private $exams_table = "exams";
    private $questions_table = "exam_questions";

    public $id;
    public $exam_id;
    public $student_id;
    public $start_time;
    public $end_time;
    public $status;
    public $total_score;
    public $percentage;
    public $passed;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Start a new exam attempt
    public function startExam($exam_id, $student_id) {
        // Check if student has already started this exam
        $check_query = "SELECT id, status FROM " . $this->attempts_table . "
                        WHERE exam_id = :exam_id AND student_id = :student_id 
                        AND status IN ('in_progress', 'submitted')";
        
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':exam_id', $exam_id);
        $check_stmt->bindParam(':student_id', $student_id);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() > 0) {
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            if($existing['status'] == 'in_progress') {
                // Resume existing attempt
                $this->id = $existing['id'];
                return ['success' => true, 'resume' => true, 'attempt_id' => $this->id];
            } else {
                // Already submitted
                return ['success' => false, 'message' => 'You have already submitted this exam'];
            }
        }

        // Start new attempt
        $query = "INSERT INTO " . $this->attempts_table . "
                  SET exam_id = :exam_id, student_id = :student_id,
                      start_time = NOW(), status = 'in_progress'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->bindParam(':student_id', $student_id);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return ['success' => true, 'resume' => false, 'attempt_id' => $this->id];
        }

        return ['success' => false, 'message' => 'Failed to start exam'];
    }

    // Get exam details with questions for taking
    public function getExamQuestions($exam_id, $student_id, $attempt_id) {
        // Verify attempt belongs to student
        $verify_query = "SELECT id FROM " . $this->attempts_table . "
                        WHERE id = :attempt_id AND student_id = :student_id 
                        AND status = 'in_progress'";
        
        $verify_stmt = $this->conn->prepare($verify_query);
        $verify_stmt->bindParam(':attempt_id', $attempt_id);
        $verify_stmt->bindParam(':student_id', $student_id);
        $verify_stmt->execute();

        if($verify_stmt->rowCount() == 0) {
            return false;
        }

        // Get exam details
        $exam_query = "SELECT e.*, c.course_code, c.course_name,
                              (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as total_questions
                       FROM " . $this->exams_table . " e
                       LEFT JOIN courses c ON e.course_id = c.id
                       WHERE e.id = :exam_id";
        
        $exam_stmt = $this->conn->prepare($exam_query);
        $exam_stmt->bindParam(':exam_id', $exam_id);
        $exam_stmt->execute();
        $exam = $exam_stmt->fetch(PDO::FETCH_ASSOC);

        if(!$exam) {
            return false;
        }

        // Get questions with options
        $questions_query = "SELECT q.*, eq.marks, eq.question_order,
                                   (SELECT COUNT(*) FROM question_options WHERE question_id = q.id) as has_options
                            FROM exam_questions eq
                            JOIN questions q ON eq.question_id = q.id
                            WHERE eq.exam_id = :exam_id
                            ORDER BY eq.question_order";
        
        $questions_stmt = $this->conn->prepare($questions_query);
        $questions_stmt->bindParam(':exam_id', $exam_id);
        $questions_stmt->execute();
        $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get options for each question
        foreach($questions as &$question) {
            if($question['has_options'] > 0) {
                $options_query = "SELECT * FROM question_options 
                                  WHERE question_id = :question_id 
                                  ORDER BY option_letter";
                $options_stmt = $this->conn->prepare($options_query);
                $options_stmt->bindParam(':question_id', $question['id']);
                $options_stmt->execute();
                $question['options'] = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Get student's saved answer if any
            $answer_query = "SELECT * FROM " . $this->answers_table . "
                            WHERE attempt_id = :attempt_id AND question_id = :question_id";
            $answer_stmt = $this->conn->prepare($answer_query);
            $answer_stmt->bindParam(':attempt_id', $attempt_id);
            $answer_stmt->bindParam(':question_id', $question['id']);
            $answer_stmt->execute();
            
            if($answer_stmt->rowCount() > 0) {
                $question['student_answer'] = $answer_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $question['student_answer'] = null;
            }
        }

        $exam['questions'] = $questions;
        return $exam;
    }

    // Save answer (auto-save or manual)
    public function saveAnswer($attempt_id, $question_id, $selected_option, $answer_text = null) {
        // Check if answer already exists
        $check_query = "SELECT id FROM " . $this->answers_table . "
                        WHERE attempt_id = :attempt_id AND question_id = :question_id";
        
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':attempt_id', $attempt_id);
        $check_stmt->bindParam(':question_id', $question_id);
        $check_stmt->execute();

        if($check_stmt->rowCount() > 0) {
            // Update existing answer
            $query = "UPDATE " . $this->answers_table . "
                      SET selected_option = :selected_option, answer_text = :answer_text,
                          updated_at = NOW()
                      WHERE attempt_id = :attempt_id AND question_id = :question_id";
        } else {
            // Insert new answer
            $query = "INSERT INTO " . $this->answers_table . "
                      (attempt_id, question_id, selected_option, answer_text)
                      VALUES (:attempt_id, :question_id, :selected_option, :answer_text)";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':attempt_id', $attempt_id);
        $stmt->bindParam(':question_id', $question_id);
        $stmt->bindParam(':selected_option', $selected_option);
        $stmt->bindParam(':answer_text', $answer_text);

        return $stmt->execute();
    }

    // Submit exam
    public function submitExam($attempt_id, $student_id) {
        // Verify attempt belongs to student
        $verify_query = "SELECT id, exam_id FROM " . $this->attempts_table . "
                        WHERE id = :attempt_id AND student_id = :student_id 
                        AND status = 'in_progress'";
        
        $verify_stmt = $this->conn->prepare($verify_query);
        $verify_stmt->bindParam(':attempt_id', $attempt_id);
        $verify_stmt->bindParam(':student_id', $student_id);
        $verify_stmt->execute();

        if($verify_stmt->rowCount() == 0) {
            return ['success' => false, 'message' => 'Invalid attempt'];
        }

        $attempt = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        $exam_id = $attempt['exam_id'];

        // Calculate score
        $score_result = $this->calculateScore($attempt_id, $exam_id);

        // Update attempt
        $update_query = "UPDATE " . $this->attempts_table . "
                        SET end_time = NOW(), status = 'submitted',
                            total_score = :total_score, percentage = :percentage,
                            passed = :passed
                        WHERE id = :attempt_id";

        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->bindParam(':total_score', $score_result['total_score']);
        $update_stmt->bindParam(':percentage', $score_result['percentage']);
        $update_stmt->bindParam(':passed', $score_result['passed'], PDO::PARAM_BOOL);
        $update_stmt->bindParam(':attempt_id', $attempt_id);

        if($update_stmt->execute()) {
            return [
                'success' => true,
                'score' => $score_result['total_score'],
                'percentage' => $score_result['percentage'],
                'passed' => $score_result['passed']
            ];
        }

        return ['success' => false, 'message' => 'Failed to submit exam'];
    }

    // Auto-submit when time expires
    public function autoSubmitExpired($attempt_id) {
        $query = "UPDATE " . $this->attempts_table . "
                  SET end_time = NOW(), status = 'timed_out'
                  WHERE id = :attempt_id AND status = 'in_progress'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':attempt_id', $attempt_id);
        return $stmt->execute();
    }

    // Calculate score
    private function calculateScore($attempt_id, $exam_id) {
        // Get exam details
        $exam_query = "SELECT total_marks, passing_score FROM " . $this->exams_table . "
                       WHERE id = :exam_id";
        $exam_stmt = $this->conn->prepare($exam_query);
        $exam_stmt->bindParam(':exam_id', $exam_id);
        $exam_stmt->execute();
        $exam = $exam_stmt->fetch(PDO::FETCH_ASSOC);

        // Get all questions with correct answers
        $questions_query = "SELECT q.id, q.question_type, eq.marks,
                                   (SELECT option_letter FROM question_options 
                                    WHERE question_id = q.id AND is_correct = 1 LIMIT 1) as correct_option
                            FROM exam_questions eq
                            JOIN questions q ON eq.question_id = q.id
                            WHERE eq.exam_id = :exam_id";
        
        $questions_stmt = $this->conn->prepare($questions_query);
        $questions_stmt->bindParam(':exam_id', $exam_id);
        $questions_stmt->execute();
        $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get student answers
        $answers_query = "SELECT * FROM " . $this->answers_table . "
                          WHERE attempt_id = :attempt_id";
        $answers_stmt = $this->conn->prepare($answers_query);
        $answers_stmt->bindParam(':attempt_id', $attempt_id);
        $answers_stmt->execute();
        $answers = [];
        while($row = $answers_stmt->fetch(PDO::FETCH_ASSOC)) {
            $answers[$row['question_id']] = $row;
        }

        $total_score = 0;
        $max_score = $exam['total_marks'];

        foreach($questions as $question) {
            if(isset($answers[$question['id']])) {
                $answer = $answers[$question['id']];
                $is_correct = false;

                if($question['question_type'] == 'MCQ') {
                    $is_correct = ($answer['selected_option'] == $question['correct_option']);
                } else {
                    // For text answers, instructor will grade later
                    // So we mark as needs_grading
                    $is_correct = null;
                }

                if($is_correct === true) {
                    $total_score += $question['marks'];
                    
                    // Update answer with correct status
                    $update_query = "UPDATE " . $this->answers_table . "
                                     SET is_correct = 1, marks_obtained = :marks
                                     WHERE id = :answer_id";
                    $update_stmt = $this->conn->prepare($update_query);
                    $update_stmt->bindParam(':marks', $question['marks']);
                    $update_stmt->bindParam(':answer_id', $answer['id']);
                    $update_stmt->execute();
                } else if($is_correct === false) {
                    $update_query = "UPDATE " . $this->answers_table . "
                                     SET is_correct = 0, marks_obtained = 0
                                     WHERE id = :answer_id";
                    $update_stmt = $this->conn->prepare($update_query);
                    $update_stmt->bindParam(':answer_id', $answer['id']);
                    $update_stmt->execute();
                }
            }
        }

        $percentage = ($total_score / $max_score) * 100;
        $passed = ($percentage >= $exam['passing_score']);

        return [
            'total_score' => $total_score,
            'percentage' => $percentage,
            'passed' => $passed
        ];
    }

    // Get remaining time for in-progress exam
    public function getRemainingTime($attempt_id) {
        $query = "SELECT a.start_time, e.duration_minutes
                  FROM " . $this->attempts_table . " a
                  JOIN " . $this->exams_table . " e ON a.exam_id = e.id
                  WHERE a.id = :attempt_id AND a.status = 'in_progress'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':attempt_id', $attempt_id);
        $stmt->execute();

        if($stmt->rowCount() == 0) {
            return false;
        }

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $start_time = strtotime($data['start_time']);
        $end_time = $start_time + ($data['duration_minutes'] * 60);
        $remaining = $end_time - time();

        return max(0, $remaining);
    }

    // Get student's attempts for an exam
    public function getStudentAttempts($exam_id, $student_id) {
        $query = "SELECT * FROM " . $this->attempts_table . "
                  WHERE exam_id = :exam_id AND student_id = :student_id
                  ORDER BY start_time DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>