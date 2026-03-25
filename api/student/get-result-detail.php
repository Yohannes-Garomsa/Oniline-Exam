<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Enable error reporting for debugging (remove in production)
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

// Verify attempt belongs to student and fetch exam info
$stmt = $conn->prepare("
    SELECT a.*, e.exam_title, e.total_marks, c.course_code, c.course_name
    FROM exam_attempts a
    JOIN exams e ON a.exam_id = e.id
    JOIN courses c ON e.course_id = c.id
    WHERE a.id = ? AND a.student_id = ?
");
$stmt->execute([$attempt_id, $user['id']]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$attempt) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Attempt not found or not owned by you']);
    exit;
}

// Get all questions for this exam with options and student answers
$query = "
    SELECT q.id, q.question_text, q.question_type, eq.marks,
           qo.id as option_id, qo.option_letter, qo.option_text, qo.is_correct as option_correct,
           sa.selected_option, sa.marks_obtained as student_marks, sa.is_correct as student_correct
    FROM exam_questions eq
    JOIN questions q ON eq.question_id = q.id
    LEFT JOIN question_options qo ON q.id = qo.question_id
    LEFT JOIN student_answers sa ON sa.question_id = q.id AND sa.attempt_id = ?
    WHERE eq.exam_id = ?
    ORDER BY eq.question_order, qo.id
";
$stmt = $conn->prepare($query);
$stmt->execute([$attempt_id, $attempt['exam_id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    // No questions found for this exam – might be empty
    echo json_encode([
        'success' => false,
        'message' => 'No questions found for this exam.'
    ]);
    exit;
}

// Group by question
$questions = [];
foreach ($rows as $row) {
    $qid = $row['id'];
    if (!isset($questions[$qid])) {
        $questions[$qid] = [
            'id' => $row['id'],
            'question_text' => $row['question_text'],
            'question_type' => $row['question_type'],
            'marks' => $row['marks'],
            'options' => [],
            'user_answer' => null,
            'correct_answer' => null
        ];
    }
    if ($row['option_id']) {
        $questions[$qid]['options'][] = [
            'id' => $row['option_id'],
            'letter' => $row['option_letter'],
            'text' => $row['option_text'],
            'is_correct' => (bool)$row['option_correct']
        ];
        if ($row['option_correct']) {
            $questions[$qid]['correct_answer'] = [
                'letter' => $row['option_letter'],
                'text' => $row['option_text']
            ];
        }
    }
    if ($row['selected_option']) {
        // This row contains student answer info; only one per question
        $questions[$qid]['user_answer'] = [
            'letter' => $row['selected_option'],
            'marks_obtained' => $row['student_marks'],
            'is_correct' => (bool)$row['student_correct']
        ];
    }
}

echo json_encode([
    'success' => true,
    'exam' => [
        'exam_title' => $attempt['exam_title'],
        'course_code' => $attempt['course_code'],
        'course_name' => $attempt['course_name'],
        'total_marks' => $attempt['total_marks']
    ],
    'attempt' => [
        'total_score' => $attempt['total_score'],
        'percentage' => $attempt['percentage'],
        'passed' => (bool)$attempt['passed'],
        'end_time' => $attempt['end_time']
    ],
    'questions' => array_values($questions)
]);