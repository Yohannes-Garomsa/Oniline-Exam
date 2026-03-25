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

$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : null;
if (!$exam_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing exam ID']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Verify exam ownership
$stmt = $conn->prepare("SELECT id FROM exams WHERE id = ? AND instructor_id = ?");
$stmt->execute([$exam_id, $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get question performance
$stmt = $conn->prepare("
    SELECT q.id, q.question_text, q.question_type,
           COUNT(sa.id) as times_answered,
           SUM(CASE WHEN sa.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
           AVG(sa.marks_obtained) as avg_marks
    FROM exam_questions eq
    JOIN questions q ON eq.question_id = q.id
    LEFT JOIN student_answers sa ON sa.question_id = q.id AND sa.attempt_id IN (SELECT id FROM exam_attempts WHERE exam_id = ?)
    WHERE eq.exam_id = ?
    GROUP BY q.id
");
$stmt->execute([$exam_id, $exam_id]);
$performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'performance' => $performance]);