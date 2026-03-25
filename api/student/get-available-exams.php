<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Get exams that are active, within time range, and student hasn't exhausted attempts
$query = "
    SELECT e.id, e.exam_title as title, e.exam_type, e.duration_minutes as duration,
           e.total_marks, e.passing_score, e.available_until,
           c.course_code, c.course_name,
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as question_count,
           CASE
               WHEN e.available_until < NOW() THEN 'expired'
               WHEN e.available_from > NOW() THEN 'upcoming'
               ELSE 'available'
           END as availability,
           CASE
               WHEN e.available_until < DATE_ADD(NOW(), INTERVAL 1 DAY) THEN 'urgent'
               WHEN e.available_until < DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 'today'
               ELSE 'upcoming'
           END as urgency,
           CASE
               WHEN e.available_until < NOW() THEN '#dc3545'
               WHEN e.available_until < DATE_ADD(NOW(), INTERVAL 1 DAY) THEN '#fee2e2'
               WHEN e.available_until < DATE_ADD(NOW(), INTERVAL 3 DAY) THEN '#fff3cd'
               ELSE '#d1e7dd'
           END as status_color,
           CASE
               WHEN e.available_until < NOW() THEN 'Expired'
               WHEN e.available_until < DATE_ADD(NOW(), INTERVAL 1 DAY) THEN 'Due Today'
               WHEN e.available_until < DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 'Due Tomorrow'
               ELSE 'Available'
           END as status_text,
           DATE_FORMAT(e.available_until, '%b %e, %Y') as due_date
    FROM exams e
    JOIN courses c ON e.course_id = c.id
    WHERE e.status = 'active'
      AND e.available_from <= NOW()
      AND e.available_until >= NOW()
      AND NOT EXISTS (
          SELECT 1 FROM exam_attempts ea
          WHERE ea.exam_id = e.id AND ea.student_id = ? AND ea.status IN ('submitted', 'graded')
      )
    ORDER BY e.available_until ASC
";

$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'exams' => $exams]);
?>