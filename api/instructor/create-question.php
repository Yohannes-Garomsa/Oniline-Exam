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
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

if (empty($data['question_text']) || empty($data['course_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Question text and course ID are required']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$query = "INSERT INTO questions (question_text, question_type, difficulty, course_id, instructor_id, explanation)
          VALUES (:question_text, :question_type, :difficulty, :course_id, :instructor_id, :explanation)";
$stmt = $conn->prepare($query);
$stmt->bindParam(':question_text', $data['question_text']);
$stmt->bindParam(':question_type', $data['question_type']);
$stmt->bindParam(':difficulty', $data['difficulty']);
$stmt->bindParam(':course_id', $data['course_id']);
$stmt->bindParam(':instructor_id', $user['id']);
$stmt->bindParam(':explanation', $data['explanation']);
$stmt->execute();

$question_id = $conn->lastInsertId();

// Handle options
if ($data['question_type'] === 'MCQ' && isset($data['options'])) {
    $insStmt = $conn->prepare("INSERT INTO question_options (question_id, option_letter, option_text, is_correct) VALUES (?, ?, ?, ?)");
    $letters = ['A', 'B', 'C', 'D'];
    foreach ($data['options'] as $index => $opt) {
        $letter = $letters[$index] ?? chr(65 + $index);
        $insStmt->execute([$question_id, $letter, $opt['text'], $opt['is_correct'] ? 1 : 0]);
    }
} elseif ($data['question_type'] === 'True/False' && isset($data['correct_answer'])) {
    $insStmt = $conn->prepare("INSERT INTO question_options (question_id, option_letter, option_text, is_correct) VALUES (?, ?, ?, ?)");
    $insStmt->execute([$question_id, 'A', 'True', $data['correct_answer'] === 'true' ? 1 : 0]);
    $insStmt->execute([$question_id, 'B', 'False', $data['correct_answer'] === 'false' ? 1 : 0]);
}

http_response_code(201);
echo json_encode(['success' => true, 'message' => 'Question created', 'question_id' => $question_id]);