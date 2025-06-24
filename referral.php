<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Generate referral link (using user ID)
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$referral_link = $base_url . "/registration.php?ref=" . urlencode($user_id);

// Optionally, count referred users (if you track this in your registration logic)
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
$ref_count = 0;
if (!$conn->connect_error) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE referrer_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($ref_count);
    $stmt->fetch();
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Referral Program</title>
    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; }
        .ref-container { max-width: 480px; margin: 60px auto; background: #23262F; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 40px 30px; }
        .ref-title { color: #F0B90B; font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 30px; }
        .ref-link-box { background: #181A20; border-radius: 8px; padding: 16px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; }
        .ref-link { color: #0ECB81; font-size: 1.05rem; word-break: break-all; }
        .copy-btn { background: #F0B90B; color: #181A20; border: none; border-radius: 6px; padding: 8px 18px; font-weight: bold; cursor: pointer; margin-left: 12px; }
        .copy-btn:hover { background: #b38a00; }
        .ref-count { color: #0ECB81; text-align: center; font-size: 1.1rem; margin-top: 18px; }
    </style>
</head>
<body>
    <div class="ref-container">
        <div class="ref-title"><i class="fa-solid fa-link"></i> Referral Program</div>
        <div class="ref-link-box">
            <span class="ref-link" id="refLink"><?php echo htmlspecialchars($referral_link); ?></span>
            <button class="copy-btn" onclick="copyRefLink()">Copy</button>
        </div>
        <div style="color:#B7BDC6; text-align:center; margin-bottom:10px;">Share this link with your friends. When they register, you'll get credit for the referral!</div>
        <div class="ref-count">Total Referrals: <?php echo $ref_count; ?></div>
        <a href="dashboard.php" style="display:block;text-align:center;margin-top:24px;color:#F0B90B;text-decoration:underline;">&larr; Back to Dashboard</a>
    </div>
    <script>
    function copyRefLink() {
        var refLink = document.getElementById('refLink').innerText;
        navigator.clipboard.writeText(refLink).then(function() {
            alert('Referral link copied to clipboard!');
        });
    }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</body>
</html>
