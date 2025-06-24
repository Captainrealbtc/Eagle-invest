<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ./admin_login.php');
    exit();
}

$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed.');
}

$wallets = [];
$sql = 'SELECT id, username, email, wallet_address FROM users WHERE wallet_address IS NOT NULL AND wallet_address != "" ORDER BY id DESC';
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $wallets[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Wallets | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; margin:0; }
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 230px; background: #23262F; padding-top: 30px; display: flex; flex-direction: column; z-index: 100; }
        .sidebar .logo { color: #F0B90B; font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 40px; letter-spacing: 2px; }
        .sidebar nav { flex: 1; }
        .sidebar nav a { display: flex; align-items: center; color: #EAECEF; text-decoration: none; padding: 16px 30px; font-size: 1.08rem; transition: background 0.2s, color 0.2s; }
        .sidebar nav a.active, .sidebar nav a:hover { background: #1E2026; color: #F0B90B; }
        .sidebar nav a i { margin-right: 16px; font-size: 1.2rem; }
        .sidebar .logout { margin: 30px 30px 0 30px; background: #F6465D; color: #fff; border: none; border-radius: 6px; padding: 12px 0; font-size: 1rem; cursor: pointer; text-align: center; text-decoration: none; display: block; transition: background 0.2s; }
        .sidebar .logout:hover { background: #a8322a; }
        .main-content { margin-left: 230px; min-height: 100vh; background: #181A20; }
        .container { max-width: 900px; margin: 40px auto; background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 36px 24px; }
        .title { color: #F0B90B; font-size: 2rem; font-weight: bold; margin-bottom: 24px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 12px 10px; text-align: left; }
        th { background: #1E2026; color: #F0B90B; }
        tr:nth-child(even) { background: #20232B; }
        .wallet-address { color: #0ECB81; font-family: monospace; word-break: break-all; }
        @media (max-width: 900px) { .sidebar { width: 60px; } .sidebar .logo { font-size: 1.2rem; } .sidebar nav a span { display: none; } .main-content { margin-left: 60px; } }
        @media (max-width: 700px) {
            .container { max-width: 98vw; padding: 12px 2vw; }
            table, th, td { font-size: 0.98rem; }
        }
        @media (max-width: 600px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><i class="fa-solid fa-coins"></i> Eagle<br>Invest</div>
        <nav>
            <a href="admin_dashboard.php?page=dashboard"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a>
            <a href="admin_users.php"><i class="fa-solid fa-users"></i> <span>Users</span></a>
            <a href="users_deposit.php"><i class="fa-solid fa-arrow-down"></i> <span>User Deposits</span></a>
            <a href="user_withdrawal.php"><i class="fa-solid fa-arrow-up"></i> <span>User Withdrawals</span></a>
            <a href="user_verifications.php"><i class="fa-solid fa-user-check"></i> <span>User Verifications</span></a>
            <a href="send_notification.php"><i class="fa-solid fa-bell"></i> <span>Send Notification</span></a>
            <a href="complaints_admin.php"><i class="fa-solid fa-comment-dots"></i> <span>User Complaints</span></a>
            <a href="user_wallet.php" class="active"><i class="fa-solid fa-wallet"></i> <span>User Wallets</span></a>
        </nav>
        <a class="logout" href="../logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content" style="margin-left:230px;min-height:100vh;background:#181A20;">
        <div class="container">
            <div class="title"><i class="fa-solid fa-wallet"></i> User Wallets</div>
            <?php if (empty($wallets)): ?>
                <div style="color:#F6465D;text-align:center;font-size:1.1rem;">No user wallets found.</div>
            <?php else: ?>
            <table>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Wallet Address</th>
                </tr>
                <?php foreach ($wallets as $w): ?>
                <tr>
                    <td>#<?php echo $w['id']; ?></td>
                    <td><?php echo htmlspecialchars($w['username']); ?></td>
                    <td><?php echo htmlspecialchars($w['email']); ?></td>
                    <td class="wallet-address"><?php echo htmlspecialchars($w['wallet_address']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
