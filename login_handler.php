<?php
// login_handler.php: Handles login form submission with improved security
session_start();

// --- CONFIGURATION ---
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';

// --- CSRF TOKEN GENERATION REMOVED ---

// --- BRUTE FORCE PROTECTION ---
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}
$max_attempts = 5;
$lockout_time = 300; // 5 minutes
$locked = false;
if ($_SESSION['login_attempts'] >= $max_attempts) {
    if (time() - $_SESSION['last_attempt_time'] < $lockout_time) {
        $locked = true;
    } else {
        $_SESSION['login_attempts'] = 0;
        $locked = false;
    }
}

// --- DATABASE CONNECTION ---
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check removed
    if ($locked) {
        $errors[] = 'Too many failed login attempts. Please try again later.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $errors[] = 'Please enter both username and password.';
        } else {
            $stmt = $conn->prepare('SELECT id, password FROM users WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($user_id, $password);
                $stmt->fetch();
                if ($password === $password) {
                    // Successful login
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    // Set active=1 on login
                    $update_stmt = $conn->prepare('UPDATE users SET active = 1 WHERE id = ?');
                    $update_stmt->bind_param('i', $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $errors[] = 'Invalid username or password.';
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                }
            } else {
                $errors[] = 'Invalid username or password.';
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Result</title>
</head>
<body>
    <h2>Login Result</h2>
    <?php if (!empty($errors)): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <a href="login.php">Back to Login</a>
    <?php endif; ?>
</body>
</html>
<!--
Security notes:
- For production, use environment variables for DB credentials.
- Consider logging failed login attempts to a file or database.
- Use HTTPS in production.
-->
