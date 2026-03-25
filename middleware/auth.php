<?php
// Start session if not already started
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Get the currently authenticated user (from session or Bearer token)
 * @return array|false Associative array with 'id', 'role', 'name' or false if not authenticated
 */
function getAuthenticatedUser() {
    // Check session first (for web)
    if(isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
        return [
            'id' => $_SESSION['user_id'],
            'role' => $_SESSION['user_role'],
            'name' => $_SESSION['user_name'] ?? ''
        ];
    }
    
    // Check Bearer token (for API)
    $headers = getallheaders();
    if(isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            return validateApiToken($token);
        }
    }
    
    return false;
}

/**
 * Validate an API token against the database
 * @param string $token
 * @return array|false
 */
function validateApiToken($token) {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Clean expired tokens (optional but good practice)
    $cleanup = $conn->prepare("DELETE FROM api_tokens WHERE expires_at < NOW()");
    $cleanup->execute();
    
    // Validate token
    $query = "SELECT t.user_id, u.role, u.first_name 
              FROM api_tokens t
              JOIN users u ON t.user_id = u.id
              WHERE t.token = :token AND (t.expires_at IS NULL OR t.expires_at > NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'id' => $row['user_id'],
            'role' => $row['role'],
            'name' => $row['first_name'] ?? ''
        ];
    }
    return false;
}

/**
 * Require a specific role for the current user; exits with 403 if not authorized
 * @param string $role
 * @return array The authenticated user data
 */
function requireRole($role) {
    $user = getAuthenticatedUser();
    if(!$user || $user['role'] !== $role) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit();
    }
    return $user;
}

/**
 * Generate a new API token for a user (to be used after login, for example)
 * @param int $userId
 * @param int $expiresInDays
 * @return string The generated token
 */
function generateApiToken($userId, $expiresInDays = 30) {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Generate a secure random token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresInDays days"));
    
    $query = "INSERT INTO api_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':token', $token);
    $stmt->bindParam(':expires_at', $expiresAt);
    $stmt->execute();
    
    return $token;
}