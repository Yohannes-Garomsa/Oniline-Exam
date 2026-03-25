<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

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

$stmt = $conn->prepare("
    SELECT e.id as exam_id, e.exam_title, COUNT(a.id) as pending_count
    FROM exams e
    JOIN exam_attempts a ON e.id = a.exam_id
    WHERE e.instructor_id = ? AND a.status = 'submitted'
    GROUP BY e.id
");
$stmt->execute([$user['id']]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'exams' => $exams]);
?>