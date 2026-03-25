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
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$query = "UPDATE users SET first_name = :first_name, last_name = :last_name, phone = :phone WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':first_name', $data['first_name']);
$stmt->bindParam(':last_name', $data['last_name']);
$stmt->bindParam(':phone', $data['phone']);
$stmt->bindParam(':id', $user['id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Profile updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>