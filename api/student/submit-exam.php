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
if (!$data || !isset($data['attempt_id']) || !isset($data['answers'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$conn->beginTransaction();

try {
    // Verify attempt belongs to student and is in progress
    $stmt = $conn->prepare("
        SELECT a.id, a.exam_id, e.total_marks
        FROM exam_attempts a
        JOIN exams e ON a.exam_id = e.id
        WHERE a.id = ? AND a.student_id = ? AND a.status = 'in_progress'
    ");
    $stmt->execute([$data['attempt_id'], $user['id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$attempt) {
        throw new Exception('Invalid attempt');
    }

    $exam_id = $attempt['exam_id'];

    // Get all questions for this exam with options (including letter)
    $stmt = $conn->prepare("
        SELECT q.id, q.question_type, qo.id as option_id, qo.option_letter, qo.is_correct, eq.marks
        FROM exam_questions eq
        JOIN questions q ON eq.question_id = q.id
        LEFT JOIN question_options qo ON q.id = qo.question_id
        WHERE eq.exam_id = ?
    ");
    $stmt->execute([$exam_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build maps
    $correctMap = [];        // question_id => correct option_id
    $marksMap = [];          // question_id => marks
    $optionLetterMap = [];   // option_id => letter
    $questionTypeMap = [];   // question_id => type

    foreach ($rows as $row) {
        $qid = $row['id'];
        $marksMap[$qid] = $row['marks'];
        $questionTypeMap[$qid] = $row['question_type'];
        if ($row['option_id']) {
            $optionLetterMap[$row['option_id']] = $row['option_letter'];
            if ($row['is_correct']) {
                $correctMap[$qid] = $row['option_id'];
            }
        }
    }

    $totalScore = 0;
    $maxPossible = array_sum($marksMap);

    // Insert student answers and calculate score
    $insertStmt = $conn->prepare("
        INSERT INTO student_answers (attempt_id, question_id, selected_option, marks_obtained, is_correct)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($data['answers'] as $ans) {
        $qid = $ans['question_id'];
        $selectedOptionId = $ans['selected_option_id'] ?? null;
        $marks = $marksMap[$qid] ?? 0;
        $isCorrect = false;
        $selectedLetter = null;

        // Determine if answer is correct and get the letter
        if ($selectedOptionId && isset($correctMap[$qid]) && $correctMap[$qid] == $selectedOptionId) {
            $isCorrect = true;
            $totalScore += $marks;
        }

        // Get the letter for the selected option
        if ($selectedOptionId && isset($optionLetterMap[$selectedOptionId])) {
            $selectedLetter = $optionLetterMap[$selectedOptionId];
        }

        // For True/False, the letter is stored (e.g., 'A' for True, 'B' for False)
        $insertStmt->execute([
            $data['attempt_id'],
            $qid,
            $selectedLetter,
            $isCorrect ? $marks : 0,
            $isCorrect ? 1 : 0
        ]);
    }

    // Calculate percentage and passed status
    $percentage = $maxPossible > 0 ? round(($totalScore / $maxPossible) * 100, 2) : 0;
    // You might want to fetch the actual passing score from the exam table
    $passingScore = 50; // default
    $passed = $percentage >= $passingScore ? 1 : 0;

    // Update attempt record
    $updateStmt = $conn->prepare("
        UPDATE exam_attempts
        SET end_time = NOW(), total_score = ?, percentage = ?, passed = ?, status = 'graded'
        WHERE id = ?
    ");
    $updateStmt->execute([$totalScore, $percentage, $passed, $data['attempt_id']]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'score' => $totalScore,
        'percentage' => $percentage,
        'passed' => (bool)$passed,
        'message' => 'Exam submitted successfully'
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Submission failed: ' . $e->getMessage()]);
}