<?php
// register_handler.php: Handles registration form submission

// Database connection parameters
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';

// Connect to MySQL
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Validate input
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    // Referral logic
    $referrer_id = null;
    if (isset($_GET['ref'])) {
        $referrer_id = intval($_GET['ref']);
    } elseif (isset($_POST['ref'])) {
        $referrer_id = intval($_POST['ref']);
    }

    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Check for existing username/email
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Username or email already exists.';
        }
        $stmt->close();
    }

    // Register user
    if (empty($errors)) {
        // Store password as plain text (not recommended for production)
        $stmt = $conn->prepare('INSERT INTO users (username, email, password, referrer_id) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('sssi', $username, $email, $password, $referrer_id);
        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            if ($referrer_id) {
                // Notify referrer
                $msg = "You referred a new user: $username!";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif_stmt->bind_param('is', $referrer_id, $msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                // Notify admin (with referral info)
                $ref_username = '';
                $ref_user_stmt = $conn->prepare('SELECT username FROM users WHERE id = ?');
                $ref_user_stmt->bind_param('i', $referrer_id);
                $ref_user_stmt->execute();
                $ref_user_stmt->bind_result($ref_username);
                $ref_user_stmt->fetch();
                $ref_user_stmt->close();
                $admin_msg = "User $username registered using referral from user $ref_username.";
                $admin_stmt = $conn->prepare("INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)");
                $admin_stmt->bind_param('iss', $referrer_id, $username, $admin_msg);
                $admin_stmt->execute();
                $admin_stmt->close();
            }
            // Always notify admin of new registration
            $admin_msg2 = "New user registered: $username ($email)";
            $admin_stmt2 = $conn->prepare("INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)");
            $admin_stmt2->bind_param('iss', $new_user_id, $username, $admin_msg2);
            $admin_stmt2->execute();
            $admin_stmt2->close();
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Registration Success</title>';
            echo '<meta http-equiv="refresh" content="2;url=registration.php">';
            echo '</head><body style="text-align:center;">';
            echo '<h2 style="color:green;">Registration successful!</h2>';
            echo '<p>Redirecting to login page...</p>';
            echo '</body></html>';
            exit();
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Result</title>
</head>
<body>
    <h2>Registration Result</h2>
    <?php if (!empty($errors)): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <a href="registration.php">Back to Registration</a>
    <?php endif; ?>
</body>
</html>
