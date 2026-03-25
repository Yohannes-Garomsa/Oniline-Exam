<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT e.*, c.course_code FROM exams e LEFT JOIN courses c ON e.course_id = c.id WHERE e.instructor_id = ? ORDER BY e.created_at DESC");
$stmt->execute([$user['id']]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'exams' => $exams]);
?>