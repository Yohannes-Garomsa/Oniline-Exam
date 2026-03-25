<?php
header("Content-Type: application/json");

require_once '../../config/database.php';
require_once '../../middleware/auth.php';

$user = getAuthenticatedUser();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success"=>false,"message"=>"Unauthorized"]);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$stats = [];

/* total users */
$stmt = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

/* students */
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='student'");
$stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

/* instructors */
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='instructor'");
$stats['total_instructors'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

/* admins */
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='admin'");
$stats['total_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

/* exams */
$stmt = $conn->query("SELECT COUNT(*) as total FROM exams");
$stats['total_exams'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

/* active exams */
$stmt = $conn->query("SELECT COUNT(*) as total FROM exams WHERE status='active'");
$stats['active_exams'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo json_encode([
    "success" => true,
    "stats" => $stats
]);
?>