<?php
class Database {
    private $host = "127.0.0.1";
    private $db_name = "online_exam_system";
    // use dedicated app user rather than root; credentials can be overridden via
    // environment variables or a separate config file if desired.
    private $username = "exam_app";
    private $password = "password123";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            // Log the error instead of echoing
            error_log("Database connection error: " . $e->getMessage());
            // Optionally, you could re-throw or return null
            // For a college project, you might still want to show a user-friendly message
            // but avoid exposing details.
            die("Database connection failed. Please check your configuration.");
        }
        return $this->conn;
    }
}