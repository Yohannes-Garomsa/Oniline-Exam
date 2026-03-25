<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['exam_id']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing exam ID or password']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Check exam exists and is active
$stmt = $conn->prepare("
    SELECT id, exam_password FROM exams
    WHERE id = ? AND status = 'active'
      AND available_from <= NOW() AND available_until >= NOW()
");
$stmt->execute([$data['exam_id']]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    echo json_encode(['success' => false, 'message' => 'Exam not available']);
    exit;
}

// Verify password (if exam has a password)
if (!empty($exam['exam_password']) && $data['password'] !== $exam['exam_password']) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    exit;
}

// Check if student already has an attempt
$stmt = $conn->prepare("SELECT id FROM exam_attempts WHERE exam_id = ? AND student_id = ? AND status IN ('in_progress','submitted','graded')");
$stmt->execute([$data['exam_id'], $user['id']]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'You have already attempted this exam']);
    exit;
}

// Create a new attempt record
$stmt = $conn->prepare("INSERT INTO exam_attempts (exam_id, student_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')");
$stmt->execute([$data['exam_id'], $user['id']]);
$attempt_id = $conn->lastInsertId();

echo json_encode(['success' => true, 'attempt_id' => $attempt_id, 'message' => 'Password verified']);
?>