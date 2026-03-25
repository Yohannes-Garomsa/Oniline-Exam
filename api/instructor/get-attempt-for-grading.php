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

$exam_id = $_GET['exam_id'] ?? null;
if (!$exam_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing exam ID']);
    exit;
}

// Verify exam belongs to instructor
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT id FROM exams WHERE id = ? AND instructor_id = ?");
$stmt->execute([$exam_id, $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get attempts with student info
$stmt = $conn->prepare("
    SELECT a.id, a.student_id, a.total_score, a.status,
           u.first_name, u.last_name, u.user_id
    FROM exam_attempts a
    JOIN users u ON a.student_id = u.id
    WHERE a.exam_id = ? AND a.status = 'submitted'
");
$stmt->execute([$exam_id]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'attempts' => $attempts]);
?>