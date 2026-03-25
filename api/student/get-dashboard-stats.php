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

// Get student info (GPA, etc.)
$stmt = $conn->prepare("SELECT gpa, year_of_study, section FROM students WHERE user_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total exams taken (graded attempts)
$stmt = $conn->prepare("SELECT COUNT(*) as taken FROM exam_attempts WHERE student_id = ? AND status = 'graded'");
$stmt->execute([$student_id]);
$examsTaken = $stmt->fetch(PDO::FETCH_ASSOC)['taken'];

// Get average score
$stmt = $conn->prepare("SELECT AVG(percentage) as avg FROM exam_attempts WHERE student_id = ? AND status = 'graded'");
$stmt->execute([$student_id]);
$avgScore = round($stmt->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0, 2);

// Get available exams count (exams that are active, within time range, and student hasn't attempted/used all attempts)
$stmt = $conn->prepare("
    SELECT COUNT(*) as available FROM exams e
    WHERE e.status = 'active'
      AND e.available_from <= NOW()
      AND e.available_until >= NOW()
      AND NOT EXISTS (
          SELECT 1 FROM exam_attempts ea
          WHERE ea.exam_id = e.id AND ea.student_id = ? AND ea.status IN ('submitted', 'graded')
      )
");
$stmt->execute([$student_id]);
$availableExams = $stmt->fetch(PDO::FETCH_ASSOC)['available'];

// Get completed exams (graded)
$stmt = $conn->prepare("SELECT COUNT(*) as completed FROM exam_attempts WHERE student_id = ? AND status = 'graded'");
$stmt->execute([$student_id]);
$completedExams = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];

// Get upcoming exams (active, not yet started, within next 7 days)
$stmt = $conn->prepare("
    SELECT COUNT(*) as upcoming FROM exams e
    WHERE e.status = 'active'
      AND e.available_from > NOW()
      AND e.available_from <= DATE_ADD(NOW(), INTERVAL 7 DAY)
");
$stmt->execute();
$upcomingExams = $stmt->fetch(PDO::FETCH_ASSOC)['upcoming'];

// Get due today count
$stmt = $conn->prepare("
    SELECT COUNT(*) as due FROM exams e
    WHERE e.status = 'active'
      AND e.available_from <= NOW()
      AND e.available_until >= NOW()
      AND DATE(e.available_until) = CURDATE()
");
$stmt->execute();
$dueToday = $stmt->fetch(PDO::FETCH_ASSOC)['due'];

// Get next deadline
$stmt = $conn->prepare("
    SELECT e.exam_title, e.available_until FROM exams e
    WHERE e.status = 'active' AND e.available_until >= NOW()
    ORDER BY e.available_until ASC LIMIT 1
");
$stmt->execute();
$next = $stmt->fetch(PDO::FETCH_ASSOC);
$nextDeadline = $next ? date('M j, g:i A', strtotime($next['available_until'])) : 'None';

// Get average time spent per exam (in minutes)
$stmt = $conn->prepare("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_time
    FROM exam_attempts WHERE student_id = ? AND end_time IS NOT NULL
");
$stmt->execute([$student_id]);
$avgTime = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_time'] ?? 0);

echo json_encode([
    'success' => true,
    'gpa' => $student['gpa'] ?? 0,
    'examsTaken' => (int)$examsTaken,
    'avgScore' => (float)$avgScore,
    'availableExams' => (int)$availableExams,
    'completedExams' => (int)$completedExams,
    'upcomingExams' => (int)$upcomingExams,
    'avgTime' => (int)$avgTime,
    'dueToday' => (int)$dueToday,
    'nextDeadline' => $nextDeadline
]);
?>