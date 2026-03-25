<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Allow frontend origin from Live Server or Localhost dynamically
$allowed_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : "http://localhost";
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . "/../../models/user.php";

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Extract and sanitize input
$email    = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$role     = trim($data['role'] ?? '');

// Basic validation
if (empty($email) || empty($password) || empty($role)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email, password and role are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

$allowedRoles = ['student', 'instructor', 'admin'];
if (!in_array($role, $allowedRoles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

// Create User object
$user = new User();

// Attempt login – this method should verify password and role
// After successful login
if ($user->login($email, $password, $role)) {
    $_SESSION['user_id']    = $user->id;
    $_SESSION['user_role']  = $user->role;
    $_SESSION['user_name']  = $user->first_name . ' ' . $user->last_name; // Store full name
    $_SESSION['user_email'] = $user->email;

    echo json_encode([
        'success' => true,
        'user' => [
            'id'         => $user->id,
            'user_id'    => $user->user_id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'role'       => $user->role,
            'department' => $user->department,
            'name'       => $user->first_name . ' ' . $user->last_name // Add full name
        ],
        'role' => $user->role
    ]);
}else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid credentials or inactive account'
    ]);
}