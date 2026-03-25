<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=exam_results.csv");

require_once '../../middleware/auth.php';
require_once '../../models/Report.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'instructor') {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : null;

if(!$exam_id) {
    http_response_code(400);
    echo "Exam ID required";
    exit();
}

$report = new Report();
$csv = $report->exportExamResults($exam_id, $user['id']);

if($csv) {
    echo $csv;
} else {
    http_response_code(403);
    echo "Access denied";
}
?>