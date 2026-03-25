<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../middleware/auth.php';
require_once '../../models/Question.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$raw = file_get_contents("php://input");
$data = json_decode($raw);

if(json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success"=>false,"message"=>"Invalid JSON: " . json_last_error_msg(), "raw"=>$raw]);
    exit();
}

if(isset($data->questions) && is_array($data->questions) && count($data->questions) > 0) {
    
    $question = new Question();
    
    // Format questions for import
    $questions_to_import = [];
    foreach($data->questions as $q) {
        $questions_to_import[] = [
            'question' => $q->question,
            'type' => $q->type ?? 'MCQ',
            'difficulty' => $q->difficulty ?? 'Medium',
            'course_id' => $q->course_id,
            'explanation' => $q->explanation ?? '',
            'options' => isset($q->options) ? (array)$q->options : []
        ];
    }
    
    // allow attaching to an exam if exam_id provided
    $exam_id = isset($data->exam_id) ? intval($data->exam_id) : null;
    $result = $question->importQuestions($questions_to_import, $user['id'], $exam_id);
    
    echo json_encode([
        "success" => true,
        "message" => "Import completed",
        "imported" => $result['success'],
        "failed" => $result['failed'],
        "ids" => $result['ids']
    ]);
    
} else {
    http_response_code(400);
    $detail = "No questions to import";
    if(isset($data->questions) && is_array($data->questions) && count($data->questions) === 0) {
        $detail = "Empty questions array";
    }
    error_log("import-questions.php called with invalid questions: " . json_encode($data));
    echo json_encode(["success" => false, "message" => $detail]);
}
?>