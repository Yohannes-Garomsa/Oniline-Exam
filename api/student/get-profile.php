<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$student_id = $user['id'];

// Get user info
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, department FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student info
$stmt = $conn->prepare("SELECT year_of_study, section, gpa FROM students WHERE user_id = ?");
$stmt->execute([$student_id]);
$studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// Get stats
$stmt = $conn->prepare("SELECT COUNT(*) as exams_taken, AVG(percentage) as avg_score FROM exam_attempts WHERE student_id = ? AND status = 'graded'");
$stmt->execute([$student_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'user' => $userInfo,
    'student' => $studentInfo,
    'stats' => $stats
]);
?>