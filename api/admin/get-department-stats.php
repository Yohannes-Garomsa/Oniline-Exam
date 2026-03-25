<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../middleware/auth.php';
require_once '../../models/Report.php';

$user = getAuthenticatedUser();
if(!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$report = new Report();
$stats = $report->getDepartmentStats();

echo json_encode([
    "success" => true,
    "departments" => $stats
]);
?>