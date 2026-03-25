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
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Optional: update email/password if provided
if (isset($data['email'])) {
    // ensure email is not used by another user
    $check = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
    $check->bindParam(':email', $data['email']);
    $check->bindParam(':id', $data['id']);
    $check->execute();
    if ($check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already in use']);
        exit();
    }
}

// start building update statement dynamically
$fields = [
    'first_name' => $data['first_name'],
    'last_name'  => $data['last_name'],
    'role'       => $data['role'],
    'department' => $data['department'],
    'status'     => $data['status'],
];
if (isset($data['email'])) {
    $fields['email'] = $data['email'];
}
if (!empty($data['password'])) {
    $fields['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
}

$setClauses = [];
foreach ($fields as $column => $value) {
    $setClauses[] = "$column = :$column";
}
$query = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = :id";
$stmt = $conn->prepare($query);
foreach ($fields as $column => $value) {
    $stmt->bindParam(':' . $column, $fields[$column]);
}
$stmt->bindParam(':id', $data['id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'User updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}