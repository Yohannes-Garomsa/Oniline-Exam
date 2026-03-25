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

// Fetch courses with instructor names
$query = "SELECT c.*, 
                 CONCAT(u.first_name, ' ', u.last_name) as instructor_name
          FROM courses c
          LEFT JOIN users u ON c.instructor_id = u.id
          ORDER BY c.created_at DESC";
$stmt = $conn->query($query);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'courses' => $courses]);
?>