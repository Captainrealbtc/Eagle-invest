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
$username = htmlspecialchars($_SESSION['username']);

// Fetch wallet address
$wallet_address = '';
$stmt = $conn->prepare('SELECT wallet_address FROM users WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($wallet_address);
    $stmt->fetch();
    $stmt->close();
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Wallet | EagleInvest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; }
        .container { max-width: 420px; margin: 60px auto; background: #23262F; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 40px 30px; text-align: center; }
        .wallet-title { color: #F0B90B; font-size: 2rem; font-weight: bold; margin-bottom: 24px; }
        .wallet-label { color: #B7BDC6; font-size: 1.1rem; margin-bottom: 10px; }
        .wallet-address { background: #181A20; color: #F0B90B; font-size: 1.1rem; padding: 14px 10px; border-radius: 8px; word-break: break-all; margin-bottom: 18px; border: 1px solid #2B3139; }
        .no-wallet { color: #F6465D; font-size: 1.1rem; margin-bottom: 18px; }
        a.dashboard-link { color: #F0B90B; text-decoration: underline; font-size: 1rem; }
        @media (max-width: 600px) { .container { max-width: 98vw; padding: 18px 4vw; } .wallet-title { font-size: 1.3rem; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="wallet-title"><i class="fa-solid fa-wallet"></i> My Wallet</div>
        <div class="wallet-label">Wallet Address:</div>
        <?php if ($wallet_address): ?>
            <div class="wallet-address"><?php echo htmlspecialchars($wallet_address); ?></div>
        <?php else: ?>
            <div class="no-wallet">No wallet address added yet. You can add one by clicking "Setting"
                 on your dashboard.</div>
        <?php endif; ?>
        <a href="dashboard.php" class="dashboard-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</body>
</html>
