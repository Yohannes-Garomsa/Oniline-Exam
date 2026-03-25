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

$exam_id = $_GET['exam_id'] ?? null;
if (!$exam_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing exam ID']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Verify exam belongs to instructor
$stmt = $conn->prepare("SELECT id FROM exams WHERE id = ? AND instructor_id = ?");
$stmt->execute([$exam_id, $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get questions created by instructor that are NOT already in the exam
$query = "
    SELECT q.id, q.question_text, q.question_type, q.difficulty
    FROM questions q
    WHERE q.instructor_id = ?
      AND q.id NOT IN (
          SELECT question_id FROM exam_questions WHERE exam_id = ?
      )
    ORDER BY q.created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->execute([$user['id'], $exam_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'questions' => $questions]);
?>