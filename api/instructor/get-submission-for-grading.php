<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../middleware/auth.php';
require_once '../../models/Grading.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$attempt_id = isset($_GET['attempt_id']) ? $_GET['attempt_id'] : null;

if(!$attempt_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Attempt ID required"]);
    exit();
}

$grading = new Grading();
$submission = $grading->getSubmissionForGrading($attempt_id, $user['id']);

if($submission) {
    echo json_encode([
        "success" => true,
        "submission" => $submission
    ]);
} else {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access denied"]);
}
?>