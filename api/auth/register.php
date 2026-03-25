<?php
// Enable CORS and JSON response
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");

// For debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// --- Common required fields ---
$required = ['first_name', 'last_name', 'email', 'password', 'role'];
foreach ($required as $field) {
    if (empty($data->$field)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

// Validate email
if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate role
$allowedRoles = ['student', 'instructor', 'admin'];
if (!in_array($data->role, $allowedRoles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

// Optional: password strength (min length)
if (strlen($data->password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

// --- Create User object and populate common properties ---
$user = new User();
$user->first_name = trim($data->first_name);
$user->last_name  = trim($data->last_name);
$user->email      = trim($data->email);
$user->password   = $data->password;      // will be hashed inside register()
$user->role       = $data->role;
$user->department = trim($data->department ?? '');
$user->phone      = trim($data->phone ?? '');

// --- Role‑specific fields ---
if ($data->role === 'student') {
    // Required for student
    if (empty($data->student_id) || empty($data->year) || empty($data->section)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'student_id, year and section are required for students']);
        exit;
    }
    $user->user_id       = trim($data->student_id);      // maps to users.user_id
    $user->student_id    = trim($data->student_id);      // for students table
    $user->year_of_study = (int)$data->year;
    $user->section       = trim($data->section);
    // GPA is optional, default NULL
    $user->gpa           = isset($data->gpa) ? (float)$data->gpa : null;
}

if ($data->role === 'instructor') {
    if (empty($data->employee_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'employee_id is required for instructors']);
        exit;
    }
    $user->user_id          = trim($data->employee_id);   // maps to users.user_id
    $user->employee_id      = trim($data->employee_id);   // for instructors table
    $user->qualification    = trim($data->qualification ?? '');
    $user->experience_years = isset($data->experience) ? (int)$data->experience : 0;
}

if ($data->role === 'admin') {
    // Admin only needs common fields; no extra table
    if (empty($data->admin_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'admin_id is required for admins']);
        exit;
    }
    $user->user_id = trim($data->admin_id);
}

// --- Attempt registration ---
if ($user->register()) {
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "User registered successfully",
        "role"    => $user->role
    ]);
} else {
    // register() should return false if email already exists or other DB error
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Registration failed – email may already be registered"
    ]);
}