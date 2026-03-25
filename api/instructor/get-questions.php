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

// Get questions with course info
$stmt = $conn->prepare("
    SELECT q.*, c.course_code 
    FROM questions q
    LEFT JOIN courses c ON q.course_id = c.id
    WHERE q.instructor_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$user['id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each MCQ question, fetch its options
foreach ($questions as &$q) {
    if ($q['question_type'] === 'MCQ') {
        $optStmt = $conn->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id");
        $optStmt->execute([$q['id']]);
        $q['options'] = $optStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $q['options'] = [];
    }
}

echo json_encode(['success' => true, 'questions' => $questions]);
?>