<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

// Fetch exams with course code and instructor name
$query = "SELECT e.*, 
                 c.course_code,
                 CONCAT(u.first_name, ' ', u.last_name) as instructor_name
          FROM exams e
          LEFT JOIN courses c ON e.course_id = c.id
          LEFT JOIN users u ON e.instructor_id = u.id
          ORDER BY e.created_at DESC";
$stmt = $conn->query($query);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'exams' => $exams]);
?>