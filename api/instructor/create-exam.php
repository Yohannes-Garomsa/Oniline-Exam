<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../middleware/auth.php';
require_once '../../models/Exam.php';

$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['exam_title']) || empty($data['course_id']) || empty($data['duration_minutes']) || empty($data['total_marks'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$exam = new Exam();
$exam->exam_title = $data['exam_title'];
$exam->course_id = $data['course_id'];
$exam->instructor_id = $user['id'];
$exam->description = $data['description'] ?? '';
$exam->exam_type = $data['exam_type'] ?? 'Quiz';
$exam->total_marks = $data['total_marks'];
$exam->passing_score = $data['passing_score'] ?? 50;
$exam->duration_minutes = $data['duration_minutes'];
$exam->available_from = $data['available_from'] ?? date('Y-m-d H:i:s');
$exam->available_until = $data['available_until'] ?? date('Y-m-d H:i:s', strtotime('+7 days'));
$exam->randomize_questions = $data['randomize_questions'] ?? false;
$exam->show_results = $data['show_results'] ?? 'immediate';
$exam->attempts_allowed = $data['attempts_allowed'] ?? 1;
$exam->status = $data['status'] ?? 'draft';
$exam->exam_password = $data['exam_password'] ?? null;

if ($exam->create()) {
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Exam created', 'exam_id' => $exam->id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to create exam']);
}