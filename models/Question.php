<?php
require_once __DIR__ . '/../config/database.php';

class Question {
    private $conn;
    private $table_name = "questions";
    private $options_table = "question_options";
    private $exam_questions_table = "exam_questions";

    // Question properties
    public $id;
    public $question_text;
    public $question_type;
    public $difficulty;
    public $course_id;
    public $instructor_id;
    public $explanation;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Create new question
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET question_text=:question_text, question_type=:question_type,
                      difficulty=:difficulty, course_id=:course_id,
                      instructor_id=:instructor_id, explanation=:explanation";

        $stmt = $this->conn->prepare($query);

        // Clean and sanitize data
        $this->question_text = htmlspecialchars(strip_tags($this->question_text));
        $this->explanation = htmlspecialchars(strip_tags($this->explanation ?? ''));

        // Bind parameters
        $stmt->bindParam(':question_text', $this->question_text);
        $stmt->bindParam(':question_type', $this->question_type);
        $stmt->bindParam(':difficulty', $this->difficulty);
        $stmt->bindParam(':course_id', $this->course_id);
        $stmt->bindParam(':instructor_id', $this->instructor_id);
        $stmt->bindParam(':explanation', $this->explanation);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // Add options for MCQ question
    public function addOptions($options) {
        if(empty($options) || !is_array($options)) {
            return false;
        }

        $query = "INSERT INTO " . $this->options_table . "
                  (question_id, option_letter, option_text, is_correct) 
                  VALUES (:question_id, :option_letter, :option_text, :is_correct)";

        $stmt = $this->conn->prepare($query);

        foreach($options as $option) {
            $stmt->bindParam(':question_id', $this->id);
            $stmt->bindParam(':option_letter', $option['letter']);
            $stmt->bindParam(':option_text', $option['text']);
            $stmt->bindParam(':is_correct', $option['is_correct']);
            $stmt->execute();
        }

        return true;
    }

    // Get question by ID with options
    public function getQuestionById($id) {
        $query = "SELECT q.*, c.course_code, c.course_name,
                         u.first_name as instructor_first, u.last_name as instructor_last
                  FROM " . $this->table_name . " q
                  LEFT JOIN courses c ON q.course_id = c.id
                  LEFT JOIN users u ON q.instructor_id = u.id
                  WHERE q.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if($question) {
            // Get options for this question
            $options_query = "SELECT * FROM " . $this->options_table . " 
                              WHERE question_id = :question_id 
                              ORDER BY option_letter";
            $options_stmt = $this->conn->prepare($options_query);
            $options_stmt->bindParam(':question_id', $id);
            $options_stmt->execute();
            $question['options'] = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $question;
    }

    // Get all questions for instructor
    public function getInstructorQuestions($instructor_id, $course_id = null, $difficulty = null) {
        $query = "SELECT q.*, c.course_code, c.course_name,
                         (SELECT COUNT(*) FROM " . $this->exam_questions_table . " 
                          WHERE question_id = q.id) as times_used
                  FROM " . $this->table_name . " q
                  LEFT JOIN courses c ON q.course_id = c.id
                  WHERE q.instructor_id = :instructor_id";

        if($course_id && $course_id !== 'all') {
            $query .= " AND q.course_id = :course_id";
        }
        if($difficulty && $difficulty !== 'all') {
            $query .= " AND q.difficulty = :difficulty";
        }

        $query .= " ORDER BY q.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instructor_id', $instructor_id);

        if($course_id && $course_id !== 'all') {
            $stmt->bindParam(':course_id', $course_id);
        }
        if($difficulty && $difficulty !== 'all') {
            $stmt->bindParam(':difficulty', $difficulty);
        }

        $stmt->execute();
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get options for each question
        foreach($questions as &$question) {
            $options_query = "SELECT * FROM " . $this->options_table . " 
                              WHERE question_id = :question_id 
                              ORDER BY option_letter";
            $options_stmt = $this->conn->prepare($options_query);
            $options_stmt->bindParam(':question_id', $question['id']);
            $options_stmt->execute();
            $question['options'] = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $questions;
    }

    // Update question
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET question_text=:question_text, difficulty=:difficulty,
                      course_id=:course_id, explanation=:explanation
                  WHERE id=:id AND instructor_id=:instructor_id";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->question_text = htmlspecialchars(strip_tags($this->question_text));
        $this->explanation = htmlspecialchars(strip_tags($this->explanation ?? ''));

        // Bind parameters
        $stmt->bindParam(':question_text', $this->question_text);
        $stmt->bindParam(':difficulty', $this->difficulty);
        $stmt->bindParam(':course_id', $this->course_id);
        $stmt->bindParam(':explanation', $this->explanation);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':instructor_id', $this->instructor_id);

        return $stmt->execute();
    }

    // Update options
    public function updateOptions($options) {
        // Delete old options
        $delete_query = "DELETE FROM " . $this->options_table . " 
                         WHERE question_id = :question_id";
        $delete_stmt = $this->conn->prepare($delete_query);
        $delete_stmt->bindParam(':question_id', $this->id);
        $delete_stmt->execute();

        // Add new options
        return $this->addOptions($options);
    }

    // Delete question
    public function delete() {
        // Options will be deleted automatically due to CASCADE
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE id = :id AND instructor_id = :instructor_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':instructor_id', $this->instructor_id);
        
        return $stmt->execute();
    }

    // Add question to exam
    public function addToExam($exam_id, $question_id, $order_num, $marks = 2) {
        $query = "INSERT INTO " . $this->exam_questions_table . "
                  (exam_id, question_id, question_order, marks)
                  VALUES (:exam_id, :question_id, :question_order, :marks)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->bindParam(':question_id', $question_id);
        $stmt->bindParam(':question_order', $order_num);
        $stmt->bindParam(':marks', $marks);
        
        return $stmt->execute();
    }

    // Remove question from exam
    public function removeFromExam($exam_id, $question_id) {
        $query = "DELETE FROM " . $this->exam_questions_table . "
                  WHERE exam_id = :exam_id AND question_id = :question_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->bindParam(':question_id', $question_id);
        
        return $stmt->execute();
    }

    // Get questions for an exam
    public function getExamQuestions($exam_id) {
        $query = "SELECT q.*, eq.question_order, eq.marks,
                         (SELECT COUNT(*) FROM " . $this->options_table . " 
                          WHERE question_id = q.id) as options_count
                  FROM " . $this->exam_questions_table . " eq
                  JOIN " . $this->table_name . " q ON eq.question_id = q.id
                  WHERE eq.exam_id = :exam_id
                  ORDER BY eq.question_order";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->execute();

        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get options for each question
        foreach($questions as &$question) {
            if($question['options_count'] > 0) {
                $options_query = "SELECT * FROM " . $this->options_table . " 
                                  WHERE question_id = :question_id 
                                  ORDER BY option_letter";
                $options_stmt = $this->conn->prepare($options_query);
                $options_stmt->bindParam(':question_id', $question['id']);
                $options_stmt->execute();
                $question['options'] = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return $questions;
    }

    // Import multiple questions (for CSV import)
    /**
     * Import an array of questions; returns counts and inserted IDs.
     * Optionally accepts an exam_id to immediately attach each inserted question.
     *
     * @param array $questions
     * @param int $instructor_id
     * @param int|null $exam_id
     * @return array ['success'=>int, 'failed'=>int, 'ids'=>array]
     */
    public function importQuestions($questions, $instructor_id, $exam_id = null) {
        $success_count = 0;
        $failed_count = 0;
        $inserted_ids = [];

        foreach($questions as $q) {
            $this->question_text = $q['question'];
            $this->question_type = $q['type'] ?? 'MCQ';
            $this->difficulty = $q['difficulty'] ?? 'Medium';
            $this->course_id = $q['course_id'];
            $this->instructor_id = $instructor_id;
            $this->explanation = $q['explanation'] ?? '';

            if($this->create()) {
                // Add options if provided
                if(!empty($q['options']) && is_array($q['options'])) {
                    $this->addOptions($q['options']);
                }
                $success_count++;
                $inserted_ids[] = $this->id;

                // Attach to exam if requested
                if($exam_id) {
                    // determine next order number
                    $order_query = "SELECT COALESCE(MAX(question_order),0) + 1 as next_order FROM exam_questions WHERE exam_id = :exam_id";
                    $order_stmt = $this->conn->prepare($order_query);
                    $order_stmt->bindParam(':exam_id', $exam_id);
                    $order_stmt->execute();
                    $ord_row = $order_stmt->fetch(PDO::FETCH_ASSOC);
                    $next_order = $ord_row ? $ord_row['next_order'] : 1;
                    $this->addToExam($exam_id, $this->id, $next_order, 0);
                }
            } else {
                $failed_count++;
            }
        }

        return [
            'success' => $success_count,
            'failed' => $failed_count,
            'ids' => $inserted_ids
        ];
    }

    // Get question counts by course
    public function getCountsByCourse($instructor_id) {
        $query = "SELECT c.course_code, c.course_name, COUNT(q.id) as count
                  FROM courses c
                  LEFT JOIN " . $this->table_name . " q ON c.id = q.course_id 
                      AND q.instructor_id = :instructor_id
                  GROUP BY c.id
                  ORDER BY count DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instructor_id', $instructor_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>