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
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$required = ['exam_title', 'course_id', 'instructor_id', 'duration_minutes', 'total_marks', 'passing_score'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

$database = new Database();
$conn = $database->getConnection();

$query = "INSERT INTO exams (
            exam_title, course_id, instructor_id, description, exam_type,
            total_marks, passing_score, duration_minutes, available_from,
            available_until, randomize_questions, show_results, attempts_allowed,
            status, exam_password
          ) VALUES (
            :exam_title, :course_id, :instructor_id, :description, :exam_type,
            :total_marks, :passing_score, :duration_minutes, :available_from,
            :available_until, :randomize_questions, :show_results, :attempts_allowed,
            :status, :exam_password
          )";
$stmt = $conn->prepare($query);
$stmt->bindParam(':exam_title', $data['exam_title']);
$stmt->bindParam(':course_id', $data['course_id']);
$stmt->bindParam(':instructor_id', $data['instructor_id']);
$stmt->bindParam(':description', $data['description']);
$stmt->bindParam(':exam_type', $data['exam_type']);
$stmt->bindParam(':total_marks', $data['total_marks']);
$stmt->bindParam(':passing_score', $data['passing_score']);
$stmt->bindParam(':duration_minutes', $data['duration_minutes']);
$stmt->bindParam(':available_from', $data['available_from']);
$stmt->bindParam(':available_until', $data['available_until']);
$stmt->bindParam(':randomize_questions', $data['randomize_questions'], PDO::PARAM_BOOL);
$stmt->bindParam(':show_results', $data['show_results']);
$stmt->bindParam(':attempts_allowed', $data['attempts_allowed']);
$stmt->bindParam(':status', $data['status']);
$stmt->bindParam(':exam_password', $data['exam_password']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Exam created']);
} else {
    echo json_encode(['success' => false, 'message' => 'Creation failed']);
}
?>