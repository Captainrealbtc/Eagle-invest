<?php
session_start();
if (!isset($_SESSION['user_id'])) {
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
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_name = trim($_POST['card_name'] ?? '');
    $card_amount = trim($_POST['card_amount'] ?? '');
    $card_code = trim($_POST['card_code'] ?? '');
    $card_category = trim($_POST['card_category'] ?? '');
    
    // Validate fields
    if ($card_name === '' || $card_amount === '' || $card_code === '' || $card_category === '') {
        $error = 'All fields are required.';
    } elseif (!is_numeric($card_amount) || $card_amount <= 0) {
        $error = 'Invalid card amount.';
    } elseif (!isset($_FILES['card_image']) || $_FILES['card_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Card image upload failed.';
    } else {
        // Validate file
        $file = $_FILES['card_image'];
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
            $new_name = 'giftcard_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $upload_dir = 'uploads/giftcards/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $dest = $upload_dir . $new_name;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $error = 'Failed to save uploaded image.';
            } else {
                // Insert into DB
                $stmt = $conn->prepare("INSERT INTO giftcard_deposits (user_id, card_name, card_amount, card_code, card_category, card_image) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isdsss', $user_id, $card_name, $card_amount, $card_code, $card_category, $dest);
                if ($stmt->execute()) {
                    $success = true;
                    // Admin notification
                    $admin_msg = "User ID $user_id submitted a gift card deposit: $card_name, Amount: $card_amount, Code: $card_code.";
                    $admin_stmt = $conn->prepare("INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)");
                    $admin_stmt->bind_param('iss', $user_id, $_SESSION['username'], $admin_msg);
                    $admin_stmt->execute();
                    $admin_stmt->close();
                } else {
                    $error = 'Failed to submit gift card deposit.';
                }
                $stmt->close();
            }
        }
    }
}
$conn->close();

if ($success) {
    echo '<div style="color:#0ECB81;text-align:center;margin-top:40px;">Gift card deposit submitted successfully! Awaiting admin approval.<br><a href="deposit.php">Back to Deposit</a></div>';
} else {
    echo '<div style="color:#F6465D;text-align:center;margin-top:40px;">' . htmlspecialchars($error) . '<br><a href="deposit.php">Back to Deposit</a></div>';
}
