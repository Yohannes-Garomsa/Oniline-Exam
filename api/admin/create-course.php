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

$required = ['course_code', 'course_name', 'department', 'credits', 'instructor_id'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

$database = new Database();
$conn = $database->getConnection();

$query = "INSERT INTO courses (course_code, course_name, department, credits, instructor_id, description)
          VALUES (:course_code, :course_name, :department, :credits, :instructor_id, :description)";
$stmt = $conn->prepare($query);
$stmt->bindParam(':course_code', $data['course_code']);
$stmt->bindParam(':course_name', $data['course_name']);
$stmt->bindParam(':department', $data['department']);
$stmt->bindParam(':credits', $data['credits']);
$stmt->bindParam(':instructor_id', $data['instructor_id']);
$stmt->bindParam(':description', $data['description']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Course created']);
} else {
    echo json_encode(['success' => false, 'message' => 'Creation failed']);
}
?>