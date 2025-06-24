<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: registration.php');
    exit();
}

// Check latest verification status
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed.');
}
$user_id = $_SESSION['user_id'];
$latest_status = null;
$stmt = $conn->prepare('SELECT status FROM verifications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($latest_status);
    $stmt->fetch();
    $stmt->close();
}
$conn->close();

// Redirect to dashboard if pending
if ($latest_status === 'pending') {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Redirecting...</title>';
    echo '<meta http-equiv="refresh" content="3;url=dashboard.php">';
    echo '</head><body style="background:#23262F;color:#F0B90B;font-family:Arial,sans-serif;text-align:center;padding-top:80px;">';
    echo '<div style="font-size:1.3rem;">You have a pending verification. Please wait for admin review before submitting another document.<br>Redirecting to dashboard...</div>';
    echo '<script>setTimeout(function(){window.location.href="dashboard.php";},3000);</script>';
    echo '</body></html>';
    exit();
}

// Handle form submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $latest_status !== 'pending') {
    $user_id = $_SESSION['user_id'];
    $document_type = $_POST['document_type'] ?? '';
    $allowed_types = ['tax id', 'drivers license', 'government issued card', 'permit card'];
    
    if (!in_array($document_type, $allowed_types)) {
        $error = 'Invalid document type.';
    } elseif (!isset($_FILES['document_image']) || $_FILES['document_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid image file.';
    } else {
        $file = $_FILES['document_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $valid_exts = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'bmp', 'webp', 'tiff', 'svg');
        if (!in_array($ext, $valid_exts)) {
            $error = 'Unsupported file format.';
        } else {
            $upload_dir = 'uploads/verification_docs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                // Save to DB
                $conn = new mysqli($host, $user, $pass, $db);
                if ($conn->connect_error) {
                    $error = 'Database connection failed.';
                } else {
                    $stmt = $conn->prepare('INSERT INTO verifications (user_id, document_type, document_image) VALUES (?, ?, ?)');
                    $stmt->bind_param('iss', $user_id, $document_type, $filename);
                    if ($stmt->execute()) {
                        $success = true;
                        // Notify admin
                        $admin_msg = "User {$_SESSION['username']} submitted a verification document (type: $document_type).";
                        $admin_conn = new mysqli($host, $user, $pass, $db);
                        if (!$admin_conn->connect_error) {
                            $admin_stmt = $admin_conn->prepare('INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)');
                            $admin_stmt->bind_param('iss', $user_id, $_SESSION['username'], $admin_msg);
                            $admin_stmt->execute();
                            $admin_stmt->close();
                            $admin_conn->close();
                        }
                    } else {
                        $error = 'Failed to submit verification.';
                    }
                    $stmt->close();
                    $conn->close();
                }
            } else {
                $error = 'Failed to upload file.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Verification</title>
    <style>
        body { font-family: Arial, sans-serif; background: black; }
        .verify-container { max-width: 400px; margin: 60px auto; background: #23262F; padding: 30px 30px 20px 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { color:rgb(228, 188, 13); text-align: center; }
        label { display: block; margin: 18px 0 8px 0; font-weight: bold; }
        select, input[type=file] { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; margin-bottom: 18px; }
        .submit-btn { width: 100%; background: rgb(228, 188, 13); color: #fff; border: none; border-radius: 50px; padding: 12px 0; font-size: 1.1rem; font-weight: bold; cursor: pointer; margin-top: 10px; transition: background 0.2s; }
        .submit-btn:hover { background: #388e6c; }
        .msg-success { color: green; text-align: center; margin-bottom: 10px; }
        .msg-error { color: red; text-align: center; margin-bottom: 10px; }
        .box-shadow {color: rgb(228, 188, 13) border: 1px}
    </style>
</head>
<body>
    <div class="verify-container">
        <h2>Verify Your Account</h2>
        <?php if ($success): ?>
            <meta http-equiv="refresh" content="2;url=dashboard.php">
            <div class="msg-success">Verification document submitted successfully! Pending review.<br>Redirecting to dashboard...</div>
        <?php elseif ($error): ?>
            <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($latest_status === 'pending'): ?>
            <div class="msg-error" style="color:orange;">You have a pending verification. Please wait for admin review before submitting another document.</div>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <label for="document_type">Document Type</label>
            <select name="document_type" id="document_type" required>
                <option value="">-- Select Document Type --</option>
                <option value="tax id">Tax ID</option>
                <option value="drivers license">Drivers License</option>
                <option value="government issued card">Government Issued Card</option>
                <option value="permit card">Permit Card</option>
            </select>
            <label for="document_image">Upload Document Image</label>
            <input type="file" name="document_image" id="document_image" accept="image/*" required>
            <button type="submit" class="submit-btn">Submit</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
