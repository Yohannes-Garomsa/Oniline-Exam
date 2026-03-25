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

$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : null;
if (!$exam_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing exam ID']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get exam info and verify attempt exists
$stmt = $conn->prepare("
    SELECT a.id as attempt_id, a.start_time, e.*, c.course_code
    FROM exam_attempts a
    JOIN exams e ON a.exam_id = e.id
    JOIN courses c ON e.course_id = c.id
    WHERE a.exam_id = ? AND a.student_id = ? AND a.status = 'in_progress'
");
$stmt->execute([$exam_id, $user['id']]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No active attempt found']);
    exit;
}

// Get questions for this exam (with options if MCQ)
$query = "
    SELECT q.id, q.question_text, q.question_type,
           qo.id as option_id, qo.option_letter, qo.option_text, qo.is_correct
    FROM exam_questions eq
    JOIN questions q ON eq.question_id = q.id
    LEFT JOIN question_options qo ON q.id = qo.question_id
    WHERE eq.exam_id = ?
    ORDER BY eq.question_order, qo.id
";
$stmt = $conn->prepare($query);
$stmt->execute([$exam_id]);
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
            'text' => $row['option_text'],
            'is_correct' => (bool)$row['is_correct'] // may hide from student in actual exam
        ];
    }
}

// For security, we might want to hide correct answers from student before submission
// We'll remove is_correct for now
foreach ($questions as &$q) {
    foreach ($q['options'] as &$opt) {
        unset($opt['is_correct']);
    }
}

echo json_encode([
    'success' => true,
    'exam' => [
        'id' => $attempt['id'],
        'title' => $attempt['exam_title'],
        'course_code' => $attempt['course_code'],
        'duration' => $attempt['duration_minutes'],
        'total_marks' => $attempt['total_marks'],
        'start_time' => $attempt['start_time'],
        'time_remaining' => max(0, strtotime($attempt['start_time']) + ($attempt['duration_minutes'] * 60) - time())
    ],
    'questions' => array_values($questions)
]);
?>