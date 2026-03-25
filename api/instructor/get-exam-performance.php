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
$report = new Report();
$performance = $report->getExamPerformance($user['id'], $exam_id);

echo json_encode([
    "success" => true,
    "performance" => $performance
]);
?>