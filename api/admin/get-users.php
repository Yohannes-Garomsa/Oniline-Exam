<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

require_once '../../middleware/auth.php';
require_once '../../models/user.php';

$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Fetch all users (you may want to join with students/instructors tables)
$query = "SELECT u.id, u.user_id, u.first_name, u.last_name, u.email, u.phone, u.role, u.department, u.status, u.created_at,
                 s.year_of_study, s.section, s.gpa,
                 i.qualification, i.experience_years
          FROM users u
          LEFT JOIN students s ON u.id = s.user_id
          LEFT JOIN instructors i ON u.id = i.user_id
          ORDER BY u.created_at DESC";

$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'users' => $users]);