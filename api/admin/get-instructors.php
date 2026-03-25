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

$query = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role = 'instructor' ORDER BY first_name";
$stmt = $conn->query($query);
$instructors = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'instructors' => $instructors]);
?>