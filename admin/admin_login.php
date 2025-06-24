<?php
session_start();
// Redirect if already logged in
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // DB connection (should be moved to config)
    $host = 'localhost';
    $db   = 'user_auth_db';
    $user = 'root';
    $pass = '';
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        $error = 'Database connection failed.';
    } else {
        $stmt = $conn->prepare('SELECT id, name, email, password FROM admins WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($password === $row['password']) {
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_name'] = $row['name'];
                $_SESSION['admin_email'] = $row['email'];
                header('Location: admin_dashboard.php');
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | Eagle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; }
        .login-container { max-width: 400px; margin: 80px auto; background: #23262F; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 40px 30px; }
        .login-title { color: #F0B90B; font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; margin-bottom: 8px; color: #B7BDC6; }
        .form-group input { width: 100%; padding: 12px; border-radius: 6px; border: none; background: #181A20; color: #EAECEF; font-size: 1rem; }
        .form-group input:focus { outline: 2px solid #F0B90B; }
        .login-btn { width: 100%; background: #F0B90B; color: #181A20; border: none; border-radius: 6px; padding: 14px 0; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .login-btn:hover { background: #b38a00; }
        .error-msg { color: #F6465D; text-align: center; margin-bottom: 18px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-title"><i class="fa-solid fa-user-shield"></i> Admin Login</div>
        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button class="login-btn" type="submit">Login</button>
        </form>
    </div>
</body>
</html>
