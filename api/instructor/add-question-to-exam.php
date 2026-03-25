<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

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
if (!$data || !isset($data['exam_id']) || !isset($data['question_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Verify exam belongs to instructor
$stmt = $conn->prepare("SELECT id FROM exams WHERE id = ? AND instructor_id = ?");
$stmt->execute([$data['exam_id'], $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Verify question belongs to instructor
$stmt = $conn->prepare("SELECT id FROM questions WHERE id = ? AND instructor_id = ?");
$stmt->execute([$data['question_id'], $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Question not yours']);
    exit;
}

// Insert into exam_questions
$query = "INSERT INTO exam_questions (exam_id, question_id, question_order, marks) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->execute([
    $data['exam_id'],
    $data['question_id'],
    $data['order'] ?? 0,
    $data['marks'] ?? 2
]);

echo json_encode(['success' => true, 'message' => 'Question added to exam']);
?>