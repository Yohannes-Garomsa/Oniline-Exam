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
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Verify ownership
$stmt = $conn->prepare("SELECT id FROM questions WHERE id = ? AND instructor_id = ?");
$stmt->execute([$data['id'], $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Update question table
$query = "UPDATE questions SET question_text=:question_text, question_type=:question_type,
          difficulty=:difficulty, course_id=:course_id, explanation=:explanation
          WHERE id=:id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':question_text', $data['question_text']);
$stmt->bindParam(':question_type', $data['question_type']);
$stmt->bindParam(':difficulty', $data['difficulty']);
$stmt->bindParam(':course_id', $data['course_id']);
$stmt->bindParam(':explanation', $data['explanation']);
$stmt->bindParam(':id', $data['id']);
$stmt->execute();

// Handle options for MCQ
if ($data['question_type'] === 'MCQ' && isset($data['options'])) {
    // Delete old options
    $delStmt = $conn->prepare("DELETE FROM question_options WHERE question_id = ?");
    $delStmt->execute([$data['id']]);

    // Insert new options
    $insStmt = $conn->prepare("INSERT INTO question_options (question_id, option_letter, option_text, is_correct) VALUES (?, ?, ?, ?)");
    $letters = ['A', 'B', 'C', 'D'];
    foreach ($data['options'] as $index => $opt) {
        $letter = $letters[$index] ?? chr(65 + $index);
        $insStmt->execute([$data['id'], $letter, $opt['text'], $opt['is_correct'] ? 1 : 0]);
    }
} elseif ($data['question_type'] === 'True/False' && isset($data['correct_answer'])) {
    // For True/False, we can store the correct answer in a separate table or as a special option.
    // Simpler: delete any existing options and insert one option with the correct value.
    $delStmt = $conn->prepare("DELETE FROM question_options WHERE question_id = ?");
    $delStmt->execute([$data['id']]);

    $insStmt = $conn->prepare("INSERT INTO question_options (question_id, option_letter, option_text, is_correct) VALUES (?, ?, ?, ?)");
    $insStmt->execute([$data['id'], 'A', 'True', $data['correct_answer'] === 'true' ? 1 : 0]);
    $insStmt->execute([$data['id'], 'B', 'False', $data['correct_answer'] === 'false' ? 1 : 0]);
}

echo json_encode(['success' => true, 'message' => 'Question updated']);