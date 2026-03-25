<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
session_start();
session_destroy();
echo json_encode(['success' => true]);
?>