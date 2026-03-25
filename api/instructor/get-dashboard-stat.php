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

// Total exams by this instructor
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM exams WHERE instructor_id = ?");
$stmt->execute([$user['id']]);
$totalExams = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active exams
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM exams WHERE instructor_id = ? AND status = 'active'");
$stmt->execute([$user['id']]);
$activeExams = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total questions
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM questions WHERE instructor_id = ?");
$stmt->execute([$user['id']]);
$totalQuestions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total distinct students who attempted any exam of this instructor
$stmt = $conn->prepare("SELECT COUNT(DISTINCT a.student_id) as total FROM exam_attempts a JOIN exams e ON a.exam_id = e.id WHERE e.instructor_id = ?");
$stmt->execute([$user['id']]);
$totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent 5 exams
$stmt = $conn->prepare("SELECT e.*, c.course_code FROM exams e LEFT JOIN courses c ON e.course_id = c.id WHERE e.instructor_id = ? ORDER BY e.created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$recentExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'totalExams' => $totalExams,
    'activeExams' => $activeExams,
    'totalQuestions' => $totalQuestions,
    'totalStudents' => $totalStudents,
    'recentExams' => $recentExams
]);
?>