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

// Get recent graded attempts
$query = "
    SELECT a.id as attempt_id, a.total_score, a.percentage, a.passed, a.end_time as date_taken,
           e.exam_title, e.total_marks,
           c.course_code
    FROM exam_attempts a
    JOIN exams e ON a.exam_id = e.id
    JOIN courses c ON e.course_id = c.id
    WHERE a.student_id = ? AND a.status = 'graded'
    ORDER BY a.end_time DESC
    LIMIT 5
";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format date
foreach ($results as &$r) {
    $r['date_taken'] = date('M j, Y', strtotime($r['date_taken']));
    $r['score'] = $r['total_score'];
}

echo json_encode(['success' => true, 'results' => $results]);
?>