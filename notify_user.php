<?php
function notify_user($user_id, $message) {
    $host = 'localhost';
    $db   = 'user_auth_db';
    $user = 'root';
    $pass = '';
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) return false;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->bind_param('is', $user_id, $message);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    return true;
}
