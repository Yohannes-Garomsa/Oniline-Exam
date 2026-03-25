<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../middleware/auth.php';
require_once '../../models/Report.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : null;

if(!$exam_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Exam ID required"]);
    exit();
}

$report = new Report();
$students = $report->getStudentPerformance($exam_id, $user['id']);

if($students !== false) {
    echo json_encode([
        "success" => true,
        "students" => $students
    ]);
} else {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access denied"]);
}
?>