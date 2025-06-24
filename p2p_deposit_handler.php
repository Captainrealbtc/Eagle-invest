<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: registration.php');
    exit();
}

$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$error = '';
$success = false;

// Fetch active P2P bank details
$p2p_bank = null;
$p2p_sql = "SELECT * FROM p2p_bank_details WHERE is_active = 1 LIMIT 1";
$p2p_res = $conn->query($p2p_sql);
if ($p2p_res && $p2p_res->num_rows > 0) {
    $p2p_bank = $p2p_res->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim($_POST['p2p_amount'] ?? '');
    if ($amount === '' || !is_numeric($amount) || $amount <= 0) {
        $error = 'Please enter a valid amount.';
    } elseif (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Payment proof upload failed.';
    } elseif (!$p2p_bank) {
        $error = 'No active P2P bank details.';
    } else {
        // Validate file
        $file = $_FILES['payment_proof'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_types)) {
            $error = 'Only JPG, PNG, GIF, or WEBP images are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error = 'Image size must be less than 2MB.';
        } else {
            // Save file
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_name = 'p2p_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $upload_dir = 'uploads/p2p_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $dest = $upload_dir . $new_name;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $error = 'Failed to save uploaded image.';
            } else {
                // Insert into DB
                $stmt = $conn->prepare("INSERT INTO p2p_deposits (user_id, username, amount, bank_name, account_number, account_name, branch_code, bank_address, payment_proof) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isdssssss', $user_id, $username, $amount, $p2p_bank['bank_name'], $p2p_bank['account_number'], $p2p_bank['account_name'], $p2p_bank['branch_code'], $p2p_bank['bank_address'], $dest);
                if ($stmt->execute()) {
                    $success = true;
                    // Admin notification
                    $admin_msg = "User $username submitted a P2P deposit of $amount. Awaiting approval.";
                    $admin_stmt = $conn->prepare("INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)");
                    $admin_stmt->bind_param('iss', $user_id, $username, $admin_msg);
                    $admin_stmt->execute();
                    $admin_stmt->close();
                } else {
                    $error = 'Failed to submit P2P deposit.';
                }
                $stmt->close();
            }
        }
    }
}
$conn->close();

if ($success) {
    echo '<div style="color:#0ECB81;text-align:center;margin-top:40px;">P2P deposit submitted successfully! Awaiting admin approval.<br><a href="deposit.php">Back to Deposit</a></div>';
} else {
    echo '<div style="color:#F6465D;text-align:center;margin-top:40px;">' . htmlspecialchars($error) . '<br><a href="deposit.php">Back to Deposit</a></div>';
}
