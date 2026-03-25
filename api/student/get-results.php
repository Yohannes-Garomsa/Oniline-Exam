<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../middleware/auth.php';
require_once '../../models/ExamAttempt.php';
require_once '../../models/Exam.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$attempt_id = isset($_GET['attempt_id']) ? $_GET['attempt_id'] : null;

if($attempt_id) {
    // Get specific attempt result
    $attempt = new ExamAttempt();
    $exam_model = new Exam();
    
    // Get attempt details
    $attempt_query = "SELECT a.*, e.exam_title, e.total_marks, e.passing_score,
                             c.course_code, c.course_name
                      FROM exam_attempts a
                      JOIN exams e ON a.exam_id = e.id
                      JOIN courses c ON e.course_id = c.id
                      WHERE a.id = :attempt_id AND a.student_id = :student_id";
    
    $conn = $attempt->conn; // reuse connection
    $stmt = $conn->prepare($attempt_query);
    $stmt->bindParam(':attempt_id', $attempt_id);
    $stmt->bindParam(':student_id', $user['id']);
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Result not found"]);
        exit();
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get answers with questions
    $answers_query = "SELECT sa.*, q.question_text, q.question_type,
                             (SELECT option_text FROM question_options 
                              WHERE question_id = q.id AND is_correct = 1 LIMIT 1) as correct_answer
                      FROM student_answers sa
                      JOIN questions q ON sa.question_id = q.id
                      WHERE sa.attempt_id = :attempt_id";
    
    $answers_stmt = $conn->prepare($answers_query);
    $answers_stmt->bindParam(':attempt_id', $attempt_id);
    $answers_stmt->execute();
    $result['answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true,
        "result" => $result
    ]);
    
} else {
    // Get all results for student
    $query = "SELECT a.*, e.exam_title, e.total_marks, c.course_code
              FROM exam_attempts a
              JOIN exams e ON a.exam_id = e.id
              JOIN courses c ON e.course_id = c.id
              WHERE a.student_id = :student_id AND a.status = 'submitted'
              ORDER BY a.end_time DESC";
    
    $attempt = new ExamAttempt();
    $stmt = $attempt->conn->prepare($query);
    $stmt->bindParam(':student_id', $user['id']);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true,
        "results" => $results
    ]);
}
?>