<?php
session_start();
// Admin authentication check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ./admin_login.php');
    exit();
}
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
$page = $_GET['page'] ?? 'dashboard';

// Fetch analytics data
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';

// Removed automatic marking of notifications as read on page load

$conn = new mysqli($host, $user, $pass, $db);
$total_users = $total_deposits = $total_withdrawals = 0;
if (!$conn->connect_error) {
    $res = $conn->query("SELECT COUNT(*) as cnt FROM users");
    if ($row = $res->fetch_assoc()) $total_users = (int)$row['cnt'];
    $res = $conn->query("SELECT COUNT(*) as cnt FROM deposits");
    if ($row = $res->fetch_assoc()) $total_deposits = (int)$row['cnt'];
    $res = $conn->query("SELECT COUNT(*) as cnt FROM withdrawals");
    if ($row = $res->fetch_assoc()) $total_withdrawals = (int)$row['cnt'];
}
// Fetch admin notifications: show unread first, fallback to latest if none unread
$admin_notifs = [];
$admin_notif_badge = false;
if (!$conn->connect_error) {
    $admin_notifs = [];
    $admin_notif_badge = false;
    $res = $conn->query("SELECT id, username, message, created_at FROM admin_notification WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $admin_notifs[] = $row;
        }
    }
    if (count($admin_notifs) > 0) {
        $admin_notif_badge = true;
    } else {
        // Only run fallback if connection is still open and first query returned no results
        $res2 = $conn->query("SELECT id, username, message, created_at FROM admin_notification ORDER BY created_at DESC LIMIT 10");
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $admin_notifs[] = $row;
            }
        }
    }
    $conn->close();
}
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard | Eagle</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .section-placeholder {
    background: #23262F;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.10);
    padding: 40px 30px;
    max-width: 700px;
    margin: 40px auto;
    text-align: center;
    color: #B7BDC6;
    font-size: 1.2rem;
}
#siteAnalyticsChart {
    max-width: 100%;
    height: auto !important;
}
@media (max-width: 900px) {
    .sidebar { width: 60px; }
    .sidebar .logo { font-size: 1.2rem; }
    .sidebar nav a span { display: none; }
    .main-content { margin-left: 60px; }
    .top-bar { padding: 0 10px; }
}
@media (max-width: 600px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .top-bar { padding: 0 5px; }
    .section-placeholder { max-width: 98vw; padding: 18px 4vw; font-size: 1rem; }
    #siteAnalyticsChart { width: 98vw !important; height: auto !important; }
}
        body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #181A20; color: #EAECEF; }
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 200px; background: #23262F; padding-top: 16px; display: flex; flex-direction: column; z-index: 100; transition: width 0.2s; }
        .sidebar.collapsed { width: 48px; }
        .sidebar .logo { color: #F0B90B; font-size: 1.5rem; font-weight: bold; text-align: center; margin-bottom: 18px; letter-spacing: 2px; transition: font-size 0.2s, margin-bottom 0.2s; }
        .sidebar.collapsed .logo { font-size: 1rem; margin-bottom: 8px; }
        .sidebar nav { flex: 1; }
        .sidebar nav a { display: flex; align-items: center; color: #EAECEF; text-decoration: none; padding: 10px 18px; font-size: 1rem; transition: background 0.2s, color 0.2s, padding 0.2s; min-height: 36px; }
        .sidebar.collapsed nav a { padding: 10px 6px; }
        .sidebar nav a.active, .sidebar nav a:hover { background: #1E2026; color: #F0B90B; }
        .sidebar nav a i { margin-right: 10px; font-size: 1.1rem; transition: margin 0.2s; }
        .sidebar.collapsed nav a i { margin-right: 0; }
        .sidebar nav a span { transition: opacity 0.2s; }
        .sidebar.collapsed nav a span { opacity: 0; width: 0; overflow: hidden; }
        .sidebar .logout { margin: 16px 18px 0 18px; background: #F6465D; color: #fff; border: none; border-radius: 6px; padding: 8px 0; font-size: 0.95rem; cursor: pointer; text-align: center; text-decoration: none; display: block; transition: background 0.2s; }
        .sidebar.collapsed .logout { margin: 16px 4px 0 4px; font-size: 0.85rem; padding: 8px 0; }
        .main-content { margin-left: 230px; min-height: 100vh; background: #181A20; transition: margin-left 0.2s; }
        .sidebar.collapsed ~ .main-content { margin-left: 60px; }
        .top-bar { background: #23262F; padding: 0 40px; height: 70px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #2B3139; position: sticky; top: 0; z-index: 10; }
        .admin-info { font-size: 1.1rem; color: #F0B90B; font-weight: bold; }
        .admin-title { font-size: 1.5rem; color: #F0B90B; font-weight: bold; margin: 40px 0 20px 0; text-align: center; }
        .section-placeholder { background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 40px 30px; max-width: 700px; margin: 40px auto; text-align: center; color: #B7BDC6; font-size: 1.2rem; }
        @media (max-width: 900px) { .sidebar { width: 60px; } .sidebar .logo { font-size: 1.2rem; } .sidebar nav a span { display: none; } .main-content { margin-left: 60px; } .top-bar { padding: 0 10px; } }
        @media (max-width: 600px) { .sidebar { display: none; } .main-content { margin-left: 0; } .top-bar { padding: 0 5px; } }
    /* Admin notification bell and badge styling */
    .admin-bell {
        position: relative;
        font-size: 2.2rem !important;
        color: #F0B90B !important;
        cursor: pointer;
        transition: color 0.2s;
        margin-right: 8px;
        display: flex;
        align-items: center;
    }
    .admin-bell:hover {
        color: #FFD600 !important;
    }
    .admin-bell i.fa-bell {
        font-size: 2.2rem !important;
        color: #F0B90B !important;
    }
    .admin-badge {
        position: absolute;
        top: -8px;
        right: -10px;
        background: #F6465D !important;
        color: #fff !important;
        font-size: 1rem !important;
        border-radius: 50%;
        padding: 2px 8px;
        font-weight: bold;
        display: inline-block;
        border: 2px solid #23262F;
        box-shadow: 0 1px 4px rgba(0,0,0,0.12);
        z-index: 10;
    }

    /* Admin notification dropdown styling (unique and standard) */
    .admin-notif-dropdown {
        display: none;
        position: absolute;
        right: 0;
        top: 32px;
        background: #23262F;
        color: #EAECEF;
        min-width: 300px;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.22);
        z-index: 1000;
        padding: 0 0 10px 0;
        font-family: 'Segoe UI', Arial, sans-serif;
        max-height: 420px;
        overflow-y: auto;
    }
    .admin-notif-dropdown .notif-title {
        font-weight: bold;
        color: #F0B90B;
        padding: 14px 22px 10px 22px;
        border-bottom: 1px solid #2B3139;
        font-size: 1.08rem;
        background: #181A20;
        border-radius: 12px 12px 0 0;
    }
    .admin-notif-item {
        padding: 14px 22px 10px 22px;
        border-bottom: 1px solid #2B3139;
        transition: background 0.18s;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .admin-notif-item:last-child {
        border-bottom: none;
    }
    .admin-notif-item:hover {
        background: #1E2026;
    }
    .admin-notif-msg {
        font-size: 1.05rem;
        color: #EAECEF;
        font-weight: 500;
        word-break: break-word;
    }
    .admin-notif-time {
        font-size: 0.88rem;
        color: #B7BDC6;
        margin-top: 2px;
        font-style: italic;
    }
    .notif-empty {
        padding: 22px;
        color: #B7BDC6;
        text-align: center;
        font-size: 1.05rem;
    }
    @media (max-width: 600px) {
        .admin-notif-dropdown {
            min-width: 90vw;
            left: 0;
            right: auto;
            border-radius: 0 0 12px 12px;
        }
        .admin-notif-item {
            padding: 12px 4vw 8px 4vw;
        }
    }
</style>
</head>
<body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var toggle = document.getElementById('sidebarToggle');
        toggle.onclick = function() {
            sidebar.classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('collapsed');
        };
    });
</script>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <div class="sidebar" id="sidebar">
        <div class="sidebar-toggle" id="sidebarToggle" style="position:absolute;top:18px;right:10px;cursor:pointer;font-size:1.5rem;color:#F0B90B;z-index:101;">
            <i class="fa fa-bars"></i>
        </div>
        <div class="logo"><i class="fa-solid fa-coins"></i> Eagle<br>Invest</div>
        <nav>
            <a href="?page=dashboard" class="<?php if($page=='dashboard') echo 'active'; ?>"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a>
             <a href="admin_users.php" class="<?php if($page=='users') echo 'active'; ?>"><i class="fa-solid fa-users"></i> <span>Users</span></a>
            <a href="users_deposit.php"><i class="fa-solid fa-arrow-down"></i> <span>User Deposits</span></a>
            <a href="user_withdrawal.php"><i class="fa-solid fa-arrow-up"></i> <span>User Withdrawals</span></a>
            <a href="user_verifications.php"><i class="fa-solid fa-user-check"></i> <span>User Verifications</span></a>
            <a href="send_notification.php"><i class="fa-solid fa-bell"></i> <span>Send Notification</span></a>
            <a href="complaints_admin.php"><i class="fa-solid fa-comment-dots"></i> <span>User Complaints</span></a>
            <a href="user_wallet.php" class="<?php if($page=='wallets') echo 'active'; ?>"><i class="fa-solid fa-wallet"></i> <span>User Wallets</span></a>
            <a href="manage_assets.php"><i class="fa-solid fa-qrcode"></i> <span>Manage Barcodes/wallets</span></a>
            
        </nav>
        <a class="logout" href="../logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="top-bar">
            <div class="admin-info"><i class="fa-solid fa-user-shield"></i> <?php echo $admin_name; ?></div>
            <div style="display: flex; align-items: center; gap: 24px;">
                <div class="notification-wrapper">
                    <div class="notification-icon admin-bell" id="adminNotifBell">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notif-badge admin-badge" id="adminNotifBadge" style="<?php echo $admin_notif_badge ? '' : 'display:none;'; ?>"><?php echo count($admin_notifs); ?></span>
                    </div>
                    <div class="notification-dropdown admin-notif-dropdown" id="adminNotifDropdown" style="display:none;">
                        <div class="notif-title">Admin Notifications</div>
                        <?php if (count($admin_notifs) > 0): ?>
                            <?php foreach ($admin_notifs as $notif): ?>
                                <div class="notif-item admin-notif-item">
                                    <div class="admin-notif-msg"><b><?php echo htmlspecialchars($notif['username'] ?? ''); ?>:</b> <?php echo htmlspecialchars($notif['message'] ?? ''); ?></div>
                                    <div class="admin-notif-time">
                                        <?php echo htmlspecialchars($notif['created_at'] ?? ''); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notif-empty">No new notifications</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="color:#B7BDC6;">Admin Panel</div>
            </div>
        </div>
        <script>
        // Admin notification dropdown toggle and mark as read
        document.addEventListener('DOMContentLoaded', function() {
            var bell = document.getElementById('adminNotifBell');
            var dropdown = document.getElementById('adminNotifDropdown');
            bell.addEventListener('click', function(e) {
                e.stopPropagation();
                if (dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                } else {
                    dropdown.style.display = 'block';
                }
                // Mark admin notifications as read via AJAX
                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'mark_admin_notif_read.php', true);
                xhr.send();
                document.getElementById('adminNotifBadge').style.display = 'none';
            });
            document.addEventListener('click', function() {
                dropdown.style.display = 'none';
            });
        });
        </script>
        <?php
        // Section placeholders (replace with real content/pages as needed)
        if ($page == 'dashboard') {
            echo '<div class="admin-title">Admin Dashboard</div>';
            echo '<div class="section-placeholder" style="max-width:1100px;">
                <div style="font-size:1.1rem;margin-bottom:18px;">Welcome to the Binance-style admin dashboard.<br>Use the sidebar to manage all platform operations.</div>
                <canvas id="siteAnalyticsChart" width="500" height="500" style="max-width:100%;margin:0 auto;display:block;"></canvas>
            </div>';
            echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
            echo '<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>';
            echo "<script>
                const ctx = document.getElementById('siteAnalyticsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Users', 'Deposits', 'Withdrawals'],
                        datasets: [{
                            label: 'Website Analytics',
                            data: [{$total_users}, {$total_deposits}, {$total_withdrawals}],
                            backgroundColor: ['#F0B90B', '#0ECB81', '#F6465D']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: true, labels: { color: '#EAECEF', font: { size: 18 } } },
                            datalabels: {
                                color: '#181A20',
                                font: { weight: 'bold', size: 20 },
                                formatter: function(value, context) {
                                    return value;
                                }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            </script>";
        } elseif ($page == 'deposits') {
            echo '<div class="admin-title">Deposits</div><div class="section-placeholder">Deposit management page (implement details here).</div>';
        } elseif ($page == 'withdrawals') {
            echo '<div class="admin-title">Withdrawals</div><div class="section-placeholder">Withdrawal management page (implement details here).</div>';
        } elseif ($page == 'notifications') {
            echo '<div class="admin-title">Notifications</div><div class="section-placeholder">Notification management page (implement details here).</div>';
        } elseif ($page == 'verifications') {
            echo '<div class="admin-title">Verifications</div><div class="section-placeholder">User verification management page (implement details here).</div>';
        } elseif ($page == 'complaints') {
            echo '<div class="admin-title">Complaints</div><div class="section-placeholder">Complaints and support page (implement details here).</div>';
        } elseif ($page == 'wallets') {
            echo '<div class="admin-title">Wallets/Deposit Addresses</div><div class="section-placeholder">Wallet and deposit address management page (implement details here).</div>';
        } elseif ($page == 'users') {
            echo '<div class="admin-title">Users</div><div class="section-placeholder">User management page (implement details here).</div>';
        } else {
            echo '<div class="admin-title">Page Not Found</div><div class="section-placeholder">The requested admin page does not exist.</div>';
        }
        ?>
    </div>
    
</body>
</html>
