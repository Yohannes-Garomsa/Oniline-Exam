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
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$query = "DELETE FROM courses WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $data['id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Course deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
?>