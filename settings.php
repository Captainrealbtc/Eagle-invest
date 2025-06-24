<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: registration.php');
    exit();
}
$user_id = $_SESSION['user_id'];

$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed.');
}

$msg = '';
// Fetch current user info
$stmt = $conn->prepare('SELECT username, wallet_address FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $wallet_address);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_username'])) {
        $new_username = trim($_POST['username']);
        if ($new_username === '') {
            $msg = 'Username cannot be empty.';
        } else {
            $stmt = $conn->prepare('UPDATE users SET username = ? WHERE id = ?');
            $stmt->bind_param('si', $new_username, $user_id);
            if ($stmt->execute()) {
                $_SESSION['username'] = $new_username;
                $msg = 'Username updated successfully!';
                // Admin notification
                $admin_msg = "User $new_username updated their username.";
                $admin_stmt = $conn->prepare('INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)');
                $admin_stmt->bind_param('iss', $user_id, $new_username, $admin_msg);
                $admin_stmt->execute();
                $admin_stmt->close();
                header('Location: dashboard.php');
                exit();
            } else {
                $msg = 'Failed to update username.';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_password'])) {
        $old_pw = $_POST['old_password'];
        $new_pw = $_POST['new_password'];
        $confirm_pw = $_POST['confirm_password'];
        $stmt = $conn->prepare('SELECT password, username FROM users WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($plain_pw, $current_username);
        $stmt->fetch();
        $stmt->close();
        if ($old_pw !== $plain_pw) {
            $msg = 'Old password is incorrect.';
        } elseif ($new_pw === '' || strlen($new_pw) < 6) {
            $msg = 'New password must be at least 6 characters.';
        } elseif ($new_pw !== $confirm_pw) {
            $msg = 'New passwords do not match.';
        } else {
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $new_pw, $user_id);
            if ($stmt->execute()) {
                $msg = 'Password updated successfully!';
                // Admin notification
                $admin_msg = "User $current_username updated their password.";
                $admin_stmt = $conn->prepare('INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)');
                $admin_stmt->bind_param('iss', $user_id, $current_username, $admin_msg);
                $admin_stmt->execute();
                $admin_stmt->close();
                header('Location: dashboard.php');
                exit();
            } else {
                $msg = 'Failed to update password.';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_wallet'])) {
        $new_wallet = trim($_POST['wallet_address']);
        $stmt = $conn->prepare('UPDATE users SET wallet_address = ? WHERE id = ?');
        $stmt->bind_param('si', $new_wallet, $user_id);
        if ($stmt->execute()) {
            $msg = 'Wallet address updated!';
            // Admin notification
            $admin_msg = "User $username updated their wallet address.";
            $admin_stmt = $conn->prepare('INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)');
            $admin_stmt->bind_param('iss', $user_id, $username, $admin_msg);
            $admin_stmt->execute();
            $admin_stmt->close();
            header('Location: dashboard.php');
            exit();
        } else {
            $msg = 'Failed to update wallet address.';
        }
        $stmt->close();
    }
    // Refresh user info
    $stmt = $conn->prepare('SELECT username, wallet_address FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($username, $wallet_address);
    $stmt->fetch();
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings | Eagle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; }
        .settings-container { max-width: 520px; margin: 40px auto; background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 36px 36px 28px 36px; }
        .settings-title { color: #F0B90B; font-size: 1.5rem; font-weight: bold; margin-bottom: 18px; text-align: center; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; margin-bottom: 8px; color: #B7BDC6; }
        .form-group input { width: 100%; padding: 12px; border-radius: 6px; border: none; background: #181A20; color: #EAECEF; font-size: 1rem; }
        .settings-btn { width: 100%; background: #F0B90B; color: #181A20; border: none; border-radius: 6px; padding: 14px 0; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: background 0.2s; margin-bottom: 10px; }
        .settings-btn:hover { background: #b38a00; }
        .msg { color: #0ECB81; text-align: center; margin-bottom: 18px; font-weight: bold; }
        .error { color: #F6465D; }
         a.dashboard-link { color: #F0B90B; text-decoration: underline; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="settings-title"><i class="fa-solid fa-gear"></i> User Settings</div>
        <?php if ($msg): ?>
            <div class="msg <?php echo (strpos($msg, 'fail') !== false || strpos($msg, 'incorrect') !== false) ? 'error' : ''; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <form method="post" style="margin-bottom:30px;">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required maxlength="50">
            </div>
            <button class="settings-btn" type="submit" name="update_username">Update Username</button>
        </form>
        <form method="post" style="margin-bottom:30px;">
            <div class="form-group">
                <label for="old_password">Old Password</label>
                <input type="password" id="old_password" name="old_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button class="settings-btn" type="submit" name="update_password">Change Password</button>
        </form>
        <form method="post">
            <div class="form-group">
                <label for="wallet_address">Wallet Address</label>
                <input type="text" id="wallet_address" name="wallet_address" value="<?php echo htmlspecialchars($wallet_address ?? ''); ?>" maxlength="255">
            </div>
            <button class="settings-btn" type="submit" name="update_wallet">Update Wallet Address</button>
             <a href="dashboard.php" class="dashboard-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </form>
    </div>
</body>
</html>
