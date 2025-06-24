<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $host = 'localhost';
    $db   = 'user_auth_db';
    $user = 'root';
    $pass = '';
    $conn = new mysqli($host, $user, $pass, $db);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare('UPDATE users SET active = 0 WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
}
session_unset();
session_destroy();
header('Location: admin_login.php');
exit();
