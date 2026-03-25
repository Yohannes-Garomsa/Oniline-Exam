<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../middleware/auth.php';
require_once '../../models/ExamAttempt.php';
require_once '../../models/Exam.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->exam_id) && !empty($data->password)) {
    
    $exam_model = new Exam();
    $exam = $exam_model->getExamById($data->exam_id);
    
    if(!$exam) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Exam not found"]);
        exit();
    }
    
    // Verify exam password
    if(!empty($exam['exam_password']) && !password_verify($data->password, $exam['exam_password'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid exam password"]);
        exit();
    }
    
    // Check if exam is available
    $now = time();
    $available_from = strtotime($exam['available_from']);
    $available_until = strtotime($exam['available_until']);
    
    if($now < $available_from) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Exam not yet available"]);
        exit();
    }
    
    if($now > $available_until) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Exam has expired"]);
        exit();
    }
    
    $attempt = new ExamAttempt();
    $result = $attempt->startExam($data->exam_id, $user['id']);
    
    if($result['success']) {
        echo json_encode([
            "success" => true,
            "message" => $result['resume'] ? "Resuming exam" : "Exam started",
            "attempt_id" => $result['attempt_id'],
            "resume" => $result['resume']
        ]);
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => $result['message']]);
    }
    
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Exam ID and password required"]);
}
?>