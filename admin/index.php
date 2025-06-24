<?php
session_start();
// For demo, assume admin is logged in. In production, add proper admin authentication.
$admin_name = 'Admin';

// Database connection (adjust as needed)
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Fetch dashboard stats
$total_users = 0;
$total_deposits = 0;
$pending_withdrawals = 0;
$total_complaints = 0;

// Total users
$res = $conn->query('SELECT COUNT(*) AS cnt FROM users');
if ($row = $res->fetch_assoc()) $total_users = $row['cnt'];
// Total deposits
$res = $conn->query('SELECT SUM(amount) AS total FROM deposits WHERE status = "approved"');
if ($row = $res->fetch_assoc()) $total_deposits = $row['total'] ?? 0;
// Pending withdrawals
$res = $conn->query('SELECT COUNT(*) AS cnt FROM withdrawals WHERE status = "pending"');
if ($row = $res->fetch_assoc()) $pending_withdrawals = $row['cnt'];
// Complaints
$res = $conn->query('SELECT COUNT(*) AS cnt FROM complaints');
if ($row = $res->fetch_assoc()) $total_complaints = $row['cnt'];
$conn->close();
?>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Index | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; }
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 220px;
            background: #23262F; padding-top: 30px; display: flex; flex-direction: column; z-index: 100;
        }
        .sidebar .logo { color: #F0B90B; font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 40px; letter-spacing: 2px; }
        .sidebar nav { flex: 1; }
        .sidebar nav a {
            display: flex; align-items: center; color: #EAECEF; text-decoration: none;
            padding: 16px 30px; font-size: 1.08rem; transition: background 0.2s, color 0.2s;
        }
        .sidebar nav a.active, .sidebar nav a:hover { background: #1E2026; color: #F0B90B; }
        .sidebar nav a i { margin-right: 16px; font-size: 1.2rem; }
        .sidebar .logout {
            margin: 30px 30px 0 30px; background: #F6465D; color: #fff; border: none;
            border-radius: 6px; padding: 12px 0; font-size: 1rem; cursor: pointer; text-align: center;
            text-decoration: none; display: block; transition: background 0.2s;
        }
        .sidebar .logout:hover { background: #a8322a; }
        .main-content { margin-left: 220px; min-height: 100vh; background: #181A20; }
        .top-bar {
            background: #23262F; padding: 0 40px; height: 70px; display: flex; align-items: center;
            justify-content: space-between; border-bottom: 1px solid #2B3139; position: sticky; top: 0; z-index: 10;
        }
        .admin-greeting { font-size: 1.2rem; font-weight: 500; color: #fff; }
        .dashboard-widgets {
            display: flex; flex-wrap: wrap; gap: 28px; justify-content: center; margin-top: 40px; margin-bottom: 30px;
        }
        .widget-card {
            background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            padding: 28px 32px 22px 32px; min-width: 180px; min-height: 120px; display: flex;
            flex-direction: column; align-items: center; justify-content: center; transition: box-shadow 0.2s, background 0.2s; position: relative;
        }
        .widget-card:hover { background: #1E2026; box-shadow: 0 4px 16px rgba(240,185,11,0.10); }
        .widget-icon { font-size: 2.1rem; margin-bottom: 10px; color: #F0B90B; }
        .widget-title { font-size: 1.08rem; color: #B7BDC6; margin-bottom: 6px; }
        .widget-value { font-size: 1.3rem; color: #fff; font-weight: bold; }
        @media (max-width: 900px) {
            .sidebar { width: 60px; }
            .sidebar .logo { font-size: 1.2rem; }
            .sidebar nav a span { display: none; }
            .main-content { margin-left: 60px; }
            .top-bar { padding: 0 10px; }
            .dashboard-widgets { gap: 12px; }
            .widget-card { min-width: 120px; padding: 18px 10px; }
        }
        @media (max-width: 600px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .top-bar { padding: 0 5px; }
            .dashboard-widgets { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><i class="fa-solid fa-coins"></i> EagleAdmin</div>
        <nav>
            <a href="#" class="active"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a>
            <a href="users.php"><i class="fa-solid fa-users"></i> <span>Users</span></a>
            <a href="deposits.php"><i class="fa-solid fa-arrow-down"></i> <span>Deposits</span></a>
            <a href="withdrawals.php"><i class="fa-solid fa-arrow-up"></i> <span>Withdrawals</span></a>
            <a href="complaints.php"><i class="fa-solid fa-comment-dots"></i> <span>Complaints</span></a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i> <span>Settings</span></a>
        </nav>
        <a class="logout" href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="top-bar">
            <div class="admin-greeting">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</div>
            <div style="color:#F0B90B;font-weight:bold;font-size:1.1rem;">Admin Panel</div>
        </div>
        <div class="dashboard-widgets">
            <div class="widget-card">
                <div class="widget-icon"><i class="fa-solid fa-users"></i></div>
                <div class="widget-title">Total Users</div>
                <div class="widget-value"><?php echo $total_users; ?></div>
            </div>
            <div class="widget-card">
                <div class="widget-icon"><i class="fa-solid fa-arrow-down"></i></div>
                <div class="widget-title">Total Deposits</div>
                <div class="widget-value">$<?php echo number_format($total_deposits, 2); ?></div>
            </div>
            <div class="widget-card">
                <div class="widget-icon"><i class="fa-solid fa-arrow-up"></i></div>
                <div class="widget-title">Pending Withdrawals</div>
                <div class="widget-value"><?php echo $pending_withdrawals; ?></div>
            </div>
            <div class="widget-card">
                <div class="widget-icon"><i class="fa-solid fa-comment-dots"></i></div>
                <div class="widget-title">Complaints</div>
                <div class="widget-value"><?php echo $total_complaints; ?></div>
            </div>
        </div>
    </div>
</body>
</html>
