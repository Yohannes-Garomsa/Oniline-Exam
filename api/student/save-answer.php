<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../middleware/auth.php';
require_once '../../models/ExamAttempt.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->attempt_id) && !empty($data->question_id)) {
    
    $attempt = new ExamAttempt();
    
    // Verify attempt belongs to student
    $remaining = $attempt->getRemainingTime($data->attempt_id);
    if($remaining === false) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Invalid attempt"]);
        exit();
    }
    
    if($remaining <= 0) {
        // Auto-submit if time expired
        $attempt->autoSubmitExpired($data->attempt_id);
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Time expired"]);
        exit();
    }
    
    $saved = $attempt->saveAnswer(
        $data->attempt_id,
        $data->question_id,
        $data->selected_option ?? null,
        $data->answer_text ?? null
    );
    
    if($saved) {
        echo json_encode([
            "success" => true,
            "message" => "Answer saved",
            "remaining_time" => $remaining
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to save answer"]);
    }
    
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Attempt ID and Question ID required"]);
}
?>