<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../middleware/auth.php';
require_once '../../models/user.php';

// Verify admin authentication
$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Validate required fields
$required = ['first_name', 'last_name', 'email', 'password', 'role'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

// Create new User object
$newUser = new User();
$newUser->first_name = $data['first_name'];
$newUser->last_name  = $data['last_name'];
$newUser->email      = $data['email'];
$newUser->password   = $data['password']; // will be hashed in register()
$newUser->role       = $data['role'];
$newUser->phone      = $data['phone'] ?? '';
$newUser->department = $data['department'] ?? '';
$newUser->status     = $data['status'] ?? 'active';

// Role-specific fields
if ($newUser->role === 'student') {
    $newUser->student_id    = $data['student_id'] ?? '';
    $newUser->year_of_study = $data['year'] ?? 1;
    $newUser->section       = $data['section'] ?? 'A';
    $newUser->gpa           = $data['gpa'] ?? null;
} elseif ($newUser->role === 'instructor') {
    $newUser->employee_id      = $data['employee_id'] ?? '';
    $newUser->qualification    = $data['qualification'] ?? '';
    $newUser->experience_years = $data['experience'] ?? 0;
} elseif ($newUser->role === 'admin') {
    // If no user_id provided, generate one
    if (empty($data['user_id'])) {
        $newUser->user_id = 'ADMIN' . rand(100, 999);
    } else {
        $newUser->user_id = $data['user_id'];
    }
}

// Attempt registration (includes password hashing)
if ($newUser->register()) {
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'User created successfully']);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User creation failed. Email may already exist.']);
}