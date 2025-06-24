<?php
// Database connection settings
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root'; // Change if needed
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Database connection failed.');
}

// Handle password reset request (step 1)
if (isset($_POST['request_reset'])) {
    $email = $_POST['email'] ?? '';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $token, $expires]);
        // Send the reset link via email if the user exists
        // Immediately show the reset form for this token
        $show_reset_form = true;
        // Set $token globally so the form uses it
        $GLOBALS['token'] = $token;
        $message = "Please enter your new password below.";
    } else {
        $message = "If the email exists, a reset link has been sent.";
    }
}

// Handle password reset (step 2)
if (isset($_POST['reset_password'])) {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    if ($password !== $confirm_password) {
        $reset_error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $reset_error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0');
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        if ($reset) {
            // Store password as plain text (not recommended)
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$password, $reset['user_id']]);
            $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
            $stmt->execute([$reset['id']]);
            $reset_success = 'Password has been reset. You may now <a href="login.php">login</a>.';
        } else {
            $reset_error = 'Invalid or expired token.';
        }
    }
}

// If token is present in URL, show password reset form
$show_reset_form = false;
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0');
    $stmt->execute([$token]);
    if ($stmt->fetch()) {
        $show_reset_form = true;
    } else {
        $reset_error = 'Invalid or expired token.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 400px; margin: 60px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; border-radius: 4px; border: 1px solid #ccc; }
        button { width: 100%; padding: 10px; background: #4EA685; color: #fff; border: none; border-radius: 4px; font-size: 16px; }
        .message { margin: 10px 0; color: green; }
        .error { margin: 10px 0; color: red; }
    </style>
</head>
<body>
<div class="container">
    <h2>Reset Password</h2>
    <?php if (!empty($message)) echo '<div class="message">' . $message . '</div>'; ?>
    <?php if (!empty($reset_error)) echo '<div class="error">' . $reset_error . '</div>'; ?>
    <?php if (!empty($reset_success)) echo '<div class="message">' . $reset_success . '</div>'; ?>

    <?php if ($show_reset_form): ?>
        <form method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="password" name="password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit" name="reset_password">Set New Password</button>
        </form>
    <?php else: ?>
        <form method="post">
            <input type="email" name="email" placeholder="Enter your email" required>
            <button type="submit" name="request_reset">Request Reset Link</button>
        </form>
    <?php endif; ?>
    <p><a href="login.php">Back to Login</a></p>
</div>
</body>
</html>
