<?php
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if (!$conn->connect_error) {
    $conn->query("UPDATE admin_notification SET is_read = 1 WHERE is_read = 0");
    $conn->close();
}
http_response_code(204);
