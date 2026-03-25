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

$attempt_id = isset($_GET['attempt_id']) ? $_GET['attempt_id'] : null;
if (!$attempt_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing attempt ID']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Verify attempt belongs to student and is in progress
$stmt = $conn->prepare("
    SELECT a.id, a.start_time, e.id as exam_id, e.exam_title, e.duration_minutes, c.course_code
    FROM exam_attempts a
    JOIN exams e ON a.exam_id = e.id
    JOIN courses c ON e.course_id = c.id
    WHERE a.id = ? AND a.student_id = ? AND a.status = 'in_progress'
");
$stmt->execute([$attempt_id, $user['id']]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$attempt) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid attempt or not in progress']);
    exit;
}

// Get questions with options (hide correct answers)
$query = "
    SELECT q.id, q.question_text, q.question_type,
           qo.id as option_id, qo.option_letter, qo.option_text
    FROM exam_questions eq
    JOIN questions q ON eq.question_id = q.id
    LEFT JOIN question_options qo ON q.id = qo.question_id
    WHERE eq.exam_id = ?
    ORDER BY eq.question_order, qo.id
";
$stmt = $conn->prepare($query);
$stmt->execute([$attempt['exam_id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group questions and options
$questions = [];
foreach ($rows as $row) {
    $qid = $row['id'];
    if (!isset($questions[$qid])) {
        $questions[$qid] = [
            'id' => $row['id'],
            'question_text' => $row['question_text'],
            'question_type' => $row['question_type'],
            'options' => []
        ];
    }
    if ($row['option_id']) {
        $questions[$qid]['options'][] = [
            'id' => $row['option_id'],
            'letter' => $row['option_letter'],
            'text' => $row['option_text']
        ];
    }
}

echo json_encode([
    'success' => true,
    'exam' => [
        'title' => $attempt['exam_title'],
        'course_code' => $attempt['course_code'],
        'duration' => $attempt['duration_minutes'],
        'start_time' => $attempt['start_time']
    ],
    'questions' => array_values($questions)
]);
?>