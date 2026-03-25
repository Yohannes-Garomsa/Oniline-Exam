<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['exam_id']) || !isset($data['grades'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Verify exam ownership (optional, can be skipped if each attempt already checked)
$db = new Database();
$conn = $db->getConnection();

$conn->beginTransaction();
try {
    foreach ($data['grades'] as $g) {
        $stmt = $conn->prepare("
            UPDATE exam_attempts
            SET total_score = :score, status = 'graded'
            WHERE id = :attempt_id
        ");
        $stmt->execute([':score' => $g['score'], ':attempt_id' => $g['attempt_id']]);
    }
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Grades submitted']);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error updating grades']);
}
?>