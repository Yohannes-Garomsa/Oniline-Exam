<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../middleware/auth.php';
require_once '../../models/Grading.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->attempt_id) && !empty($data->grades)) {
    
    $grading = new Grading();
    $saved = $grading->saveGradingDraft($data->attempt_id, $data->grades);
    
    if($saved) {
        echo json_encode([
            "success" => true,
            "message" => "Draft saved"
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to save draft"]);
    }
    
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Attempt ID and grades required"]);
}
?>