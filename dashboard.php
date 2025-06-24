<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: registration.php');
    exit();
}

// Database connection parameters
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

// Fetch verification status from verifications table
$verification_status = null;
$stmt = $conn->prepare('SELECT status FROM verifications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($verification_status);
    $stmt->fetch();
    $stmt->close();
}
// $conn->close(); // Do not close here, close at the end after all queries

// Verification status display
if ($verification_status === 'approved') {
    $status_text = '<span style="color:#0ECB81;font-weight:bold;">Verified</span>';
    $status_link = $status_text;
} elseif ($verification_status === 'pending') {
    $status_text = '<span style="color:#F0B90B;font-weight:bold;">Pending</span>';
    $status_link = $status_text;
} else {
    $status_text = '<span style="color:#F6465D;font-weight:bold;">Not Verified</span>';
    $status_link = '<a href="verification.php" style="color:#F6465D;text-decoration:underline;">' . $status_text . '</a>';
}

// Handle profile picture upload
$profile_pic_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $upload_dir = 'uploads/profile_pics/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $file = $_FILES['profile_pic'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $new_name = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $dest = $upload_dir . $new_name;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $conn->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
                $stmt->bind_param('si', $new_name, $user_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['profile_pic'] = $new_name;
                $profile_pic_msg = '<span style="color:#0ECB81;">Profile picture updated!</span>';
            } else {
                $profile_pic_msg = '<span style="color:#F6465D;">Failed to upload image.</span>';
            }
        } else {
            $profile_pic_msg = '<span style="color:#F6465D;">Invalid file type. Only JPG, PNG, GIF allowed.</span>';
        }
    } else {
        $profile_pic_msg = '<span style="color:#F6465D;">Error uploading file.</span>';
    }
}

// Fetch user balance, profile picture, and bonus_awarded
$user_balance = 0;
$profile_pic = '';
$bonus_awarded = 0;
$balance_sql = "SELECT balance, profile_pic, bonus_awarded FROM users WHERE id = ? LIMIT 1";
if ($stmt = $conn->prepare($balance_sql)) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($user_balance, $profile_pic, $bonus_awarded);
    $stmt->fetch();
    $stmt->close();
}
if (!$profile_pic && isset($_SESSION['profile_pic'])) {
    $profile_pic = $_SESSION['profile_pic'];
}
// Handle bonus accept/reject via AJAX
if (isset($_GET['bonus_action']) && in_array($_GET['bonus_action'], ['accept','reject'])) {
    if ($bonus_awarded == 0) {
        if ($_GET['bonus_action'] === 'accept') {
            $conn->query("UPDATE users SET balance = balance + 10, bonus_awarded = 1 WHERE id = $user_id");
        } else {
            $conn->query("UPDATE users SET bonus_awarded = 2 WHERE id = $user_id");
        }
    }
    $conn->close();
    exit;
}

// Fetch latest deposit for the user
$deposit_amount = 0;
$deposit_coin = '';
$deposit_status = '';
$deposit_display = '';
$deposit_color = '#fff';
$deposit_pending = false;
$deposit_approved = false;
$deposit_sql = "SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
if ($stmt = $conn->prepare($deposit_sql)) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $deposit_amount = $row['amount'];
        $deposit_coin = $row['coin_symbol'];
        $deposit_status = $row['status'];
        if ($deposit_status === 'pending') {
            $deposit_display = '<span style="color:#F6465D;">Pending: ' . htmlspecialchars($deposit_amount) . ' ' . htmlspecialchars($deposit_coin) . '</span>';
            $deposit_color = '#F6465D';
            $deposit_pending = true;
        } elseif ($deposit_status === 'approved') {
            $deposit_display = '<span style="color:#0ECB81;">' . htmlspecialchars($deposit_amount) . ' ' . htmlspecialchars($deposit_coin) . '</span>';
            $deposit_color = '#0ECB81';
            $deposit_approved = true;
        } else {
            $deposit_display = '<span style="color:#B7BDC6;">0.00</span>';
        }
    } else {
        $deposit_display = '<span style="color:#B7BDC6;">0.00</span>';
    }
    $stmt->close();
} else {
    $deposit_display = '<span style="color:#B7BDC6;">0.00</span>';
}

// Fetch latest withdrawal for the user
$withdrawal_amount = 0;
$withdrawal_coin = '';
$withdrawal_status = '';
$withdrawal_display = '';
$withdrawal_color = '#fff';
$withdrawal_pending = false;
$withdrawal_approved = false;
$withdrawal_sql = "SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
if ($stmt = $conn->prepare($withdrawal_sql)) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $withdrawal_amount = $row['amount'];
        $withdrawal_coin = isset($row['coin_symbol']) ? $row['coin_symbol'] : '';
        $withdrawal_status = $row['status'];
        if ($withdrawal_status === 'pending') {
            $withdrawal_display = '<span style="color:#F6465D;">Pending: ' . htmlspecialchars($withdrawal_amount) . ' ' . htmlspecialchars($withdrawal_coin) . '</span>';
            $withdrawal_color = '#F6465D';
            $withdrawal_pending = true;
        } elseif ($withdrawal_status === 'approved') {
            $withdrawal_display = '<span style="color:#0ECB81;">' . htmlspecialchars($withdrawal_amount) . ' ' . htmlspecialchars($withdrawal_coin) . '</span>';
            $withdrawal_color = '#0ECB81';
            $withdrawal_approved = true;
        } else {
            $withdrawal_display = '<span style="color:#B7BDC6;">0.00</span>';
        }
    } else {
        $withdrawal_display = '<span style="color:#B7BDC6;">0.00</span>';
    }
    $stmt->close();
} else {
    $withdrawal_display = '<span style="color:#B7BDC6;">0.00</span>';
}

// Notifications: fetch all unread notifications for this user
$notifications = [];
$notif_badge = false;
$notif_sql = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
if ($stmt = $conn->prepare($notif_sql)) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notif_badge = true;
        $notifications[] = [ 'msg' => htmlspecialchars($row['message']), 'time' => $row['created_at'], 'id' => $row['id'] ];
    }
    $stmt->close();
}
// Onscreen message: fetch latest unread onscreen notification
$onscreen_msg = null;
$onscreen_id = null;
$onscreen_sql = "SELECT id, message, created_at FROM notifications WHERE user_id = ? AND is_onscreen = 1 AND is_read = 0 ORDER BY created_at DESC LIMIT 1";
if ($stmt = $conn->prepare($onscreen_sql)) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($onscreen_id, $onscreen_message, $onscreen_created_at);
    if ($stmt->fetch()) {
        $onscreen_msg = [ 'id' => $onscreen_id, 'message' => $onscreen_message, 'created_at' => $onscreen_created_at ];
    }
    $stmt->close();
}
// Mark notifications as read when the bell is clicked (AJAX)
if (isset($_GET['mark_read']) && $_GET['mark_read'] == 1) {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    $conn->close();
    exit;
}
// Mark onscreen message as read via AJAX
if (isset($_GET['mark_onscreen']) && isset($_GET['onscreen_id'])) {
    $onscreen_id = intval($_GET['onscreen_id']);
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $onscreen_id AND user_id = $user_id");
    $conn->close();
    exit;
}

// Fetch latest complaint reply for popup
$complaint_reply = null;
$conn2 = new mysqli($host, $user, $pass, $db);
if ($conn2->connect_error) {
    $complaint_reply = null;
} else {
    $stmt = $conn2->prepare('SELECT id, subject, reply, replied_at FROM complaints WHERE user_id = ? AND reply IS NOT NULL AND reply != "" AND status = "resolved" ORDER BY replied_at DESC LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($cid, $csubject, $creply, $creplied_at);
    if ($stmt->fetch()) {
        $complaint_reply = [ 'id' => $cid, 'subject' => $csubject, 'reply' => $creply, 'replied_at' => $creplied_at ];
    }
    $stmt->close();
    $conn2->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Binance Style Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .notification-wrapper {
            position: relative;
        }
        .notification-icon {
            position: relative;
            font-size: 1.5rem;
            color: #EAECEF;
            cursor: pointer;
            transition: color 0.2s;
        }
        .notification-icon:hover {
            color: #F0B90B;
        }
        .notif-badge {
            position: absolute;
            top: -6px;
            right: -8px;
            background: #F6465D;
            color: #fff;
            font-size: 0.75rem;
            border-radius: 50%;
            padding: 2px 6px;
            font-weight: bold;
            display: inline-block;
        }
        .notification-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 32px;
            background: #23262F;
            color: #EAECEF;
            min-width: 260px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.18);
            z-index: 1000;
            padding: 0 0 10px 0;
        }
        .notification-dropdown.active {
            display: block;
        }
        .notif-title {
            font-weight: bold;
            color: #F0B90B;
            padding: 12px 18px 8px 18px;
            border-bottom: 1px solid #2B3139;
        }
        .user-notif-item {
            padding: 14px 18px 10px 18px;
            border-bottom: 1px solid #2B3139;
            transition: background 0.18s;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .user-notif-item:last-child {
            border-bottom: none;
        }
        .user-notif-item:hover {
            background: #1E2026;
        }
        .user-notif-msg {
            font-size: 1.05rem;
            color: #EAECEF;
            font-weight: 500;
            word-break: break-word;
        }
        .user-notif-time {
            font-size: 0.88rem;
            color: #B7BDC6;
            margin-top: 2px;
            font-style: italic;
        }
        .notif-empty {
            padding: 18px;
            color: #B7BDC6;
            text-align: center;
        }
    </style>
    <script>
        // Notification dropdown toggle and mark as read
        document.addEventListener('DOMContentLoaded', function() {
            var bell = document.getElementById('notifBell');
            var dropdown = document.getElementById('notifDropdown');
            bell.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('active');
                // Mark notifications as read via AJAX
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '?mark_read=1', true);
                xhr.send();
                document.getElementById('notifBadge').style.display = 'none';
            });
            document.addEventListener('click', function() {
                dropdown.classList.remove('active');
            });
        });
    </script>
    <<style>
    
    
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #181A20;
            color: #EAECEF;
        }
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
        .main-content { margin-left: 200px; min-height: 100vh; background: #181A20; transition: margin-left 0.2s; }
        .sidebar.collapsed ~ .main-content { margin-left: 48px; }
        .top-bar {
            background: #23262F;
            padding: 0 40px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #2B3139;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .greeting {
            font-size: 1.2rem;
            font-weight: 500;
            color: #fff;
        }
        .verification-status {
            font-size: 1rem;
            font-weight: 500;
            background: #1E2026;
            padding: 8px 22px;
            border-radius: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            color: #F0B90B;
        }
        .dashboard-box {
            margin: 40px auto;
            background: #23262F;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            padding: 40px 30px;
            max-width: 600px;
            text-align: center;
        }
        .dashboard-box h1 {
            color: #F0B90B;
            font-size: 2.2rem;
            margin-bottom: 18px;
        }
        .dashboard-box p {
            color: #B7BDC6;
            font-size: 1.1rem;
        }
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
            .top-bar { padding: 0 5px; flex-direction: column; align-items: flex-start; height: auto; }
            .greeting { font-size: 1.1rem; }
            .dashboard-widgets { flex-direction: column; align-items: center; gap: 10px; margin-top: 20px; }
            .widget-card { min-width: 90vw; max-width: 98vw; padding: 12px 4vw; font-size: 0.95rem; }
            .widget-icon { font-size: 1.5rem; }
            .widget-title { font-size: 1rem; }
            .widget-value { font-size: 1.1rem; }
            form[enctype="multipart/form-data"] { width: 98vw; }
            .notification-dropdown { min-width: 90vw; left: 0; right: auto; }
        }

        .greeting{
            color: rgb(288, 188, 13);
            font-size: 40px;
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
    <div class="sidebar" id="sidebar">
        <div class="sidebar-toggle" id="sidebarToggle" style="position:absolute;top:18px;right:10px;cursor:pointer;font-size:1.5rem;color:#F0B90B;z-index:101;">
            <i class="fa fa-bars"></i>
        </div>
        <div class="logo"><i class="fa-solid fa-coins"></i> Eagle<br>Invest</div>
        <nav>
            <a href="#" class="active"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a>
            <a href="deposit.php"><i class="fa-solid fa-arrow-down"></i> <span>Deposit</span></a>
            <a href="withdrawal.php"><i class="fa-solid fa-arrow-up"></i> <span>Withdrawal</span></a>
            <a href="recent_transactions.php"><i class="fa-solid fa-list"></i> <span>Recent Transactions</span></a>
            <a href="complaints.php"><i class="fa-solid fa-comment-dots"></i> <span>Complaints</span></a>
            <a href="wallet.php"><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a>
            <a href="referral.php"><i class="fa-solid fa-users"></i> <span>Referral</span></a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i> <span>Settings</span></a>
        </nav>
        <a class="logout" href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="top-bar">
            <div style="display:flex;align-items:center;gap:18px;">
                <div style="width:56px;height:56px;">
                    <?php if ($profile_pic && file_exists('uploads/profile_pics/' . $profile_pic)): ?>
                        <img src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" style="width:56px;height:56px;object-fit:cover;border-radius:50%;border:2px solid #F0B90B;">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>&background=23262F&color=F0B90B&size=56" alt="Default Avatar" style="width:56px;height:56px;object-fit:cover;border-radius:50%;border:2px solid #F0B90B;">
                    <?php endif; ?>
                </div>
                <div class="greeting">Welcome, <?php echo $username; ?>!</div>
            </div>
            <div style="display: flex; align-items: center; gap: 24px;">
                <div class="notification-wrapper">
                    <div class="notification-icon" id="notifBell">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notif-badge" id="notifBadge" style="<?php echo $notif_badge ? '' : 'display:none;'; ?>"><?php echo count($notifications); ?></span>
                    </div>
                    <div class="notification-dropdown" id="notifDropdown">
                        <div class="notif-title">Notifications</div>
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notif-item user-notif-item">
                                    <div class="user-notif-msg"><?php echo $notif['msg']; ?></div>
                                    <div class="user-notif-time">
                                        <?php echo htmlspecialchars($notif['time']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notif-empty">No new notifications</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="verification-status">Verification Status: <?php echo $status_link; ?></div>
            </div>
        </div>
        <?php if ($profile_pic_msg && !($profile_pic && file_exists('uploads/profile_pics/' . $profile_pic))): ?>
            <div style="text-align:center;margin:18px 0 0 0;"> <?php echo $profile_pic_msg; ?> </div>
        <?php endif; ?>
        <?php if (!($profile_pic && file_exists('uploads/profile_pics/' . $profile_pic))): ?>
        <form method="post" enctype="multipart/form-data" style="margin:18px 0 0 0;text-align:center;">
            <label for="profile_pic" style="color:#F0B90B;font-weight:bold;">Upload Profile Picture:</label>
            <input type="file" name="profile_pic" id="profile_pic" accept="image/*" style="margin-left:10px;">
            <button type="submit" style="background:#F0B90B;color:#181A20;border:none;border-radius:6px;padding:6px 18px;font-weight:bold;cursor:pointer;margin-left:10px;">Upload</button>
        </form>
       
           
        <?php endif; ?>
            <!-- TradingView Widget BEGIN -->
<div class="tradingview-widget-container">
  <div class="tradingview-widget-container__widget"></div>
  <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js" async>
  {
  "symbols": [
    {
      "proName": "FOREXCOM:SPXUSD",
      "title": "S&P 500 Index"
    },
    {
      "proName": "FOREXCOM:NSXUSD",
      "title": "US 100 Cash CFD"
    },
    {
      "proName": "FX_IDC:EURUSD",
      "title": "EUR to USD"
    },
    {
      "proName": "BITSTAMP:BTCUSD",
      "title": "Bitcoin"
    },
    {
      "proName": "BITSTAMP:ETHUSD",
      "title": "Ethereum"
    }
  ],
  "showSymbolLogo": true,
  "isTransparent": false,
  "displayMode": "adaptive",
  "colorTheme": "dark",
  "locale": "en"
}
  </script>
</div>
<!-- TradingView Widget END --> 
            
        
        
        <div class="dashboard-widgets" style="display: flex; flex-wrap: wrap; gap: 28px; justify-content: center; margin-top: 40px;">
            <div class="widget-card balance">
                <div class="widget-icon"><i class="fa-solid fa-wallet"></i></div>
                <div class="widget-title">Balance</div>
                <div class="widget-value" style="color:#0ECB81;font-weight:bold;">
                    $<?php echo number_format($user_balance, 2); ?>
                </div>
            </div>
            <div class="widget-card deposit">
                <div class="widget-icon"><i class="fa-solid fa-arrow-down"></i></div>
                <div class="widget-title">Deposit</div>
                <div class="widget-value" style="color:<?php echo $deposit_color; ?>;font-weight:bold;">
                    <?php echo $deposit_display; ?>
                </div>
            </div>
            <div class="widget-card withdrawal">
                <div class="widget-icon"><i class="fa-solid fa-arrow-up"></i></div>
                <div class="widget-title">Withdrawal</div>
                <div class="widget-value" style="color:<?php echo $withdrawal_color; ?>;font-weight:bold;">
                    <?php echo $withdrawal_display; ?>
                </div>
            </div>
            <div class="widget-card trade">
                <div class="widget-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div class="widget-title">Trade</div>
                <div class="widget-value">0</div>
            </div>
            <div class="widget-card forex">
                <div class="widget-icon"><i class="fa-solid fa-dollar-sign"></i></div>
                <div class="widget-title">Forex</div>
                <div class="widget-value">0</div>
            </div>
            <div class="widget-card indices">
                <div class="widget-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="widget-title">Indices</div>
                <div class="widget-value">0</div>
            </div>
            <div class="widget-card crypto">
                <div class="widget-icon"><i class="fa-brands fa-bitcoin"></i></div>
                <div class="widget-title">Crypto</div>
                <div class="widget-value">10 Coins</div>
            </div>
        </div>

        <script src="https://widgets.coingecko.com/gecko-coin-price-chart-widget.js"></script>
<gecko-coin-price-chart-widget locale="en" dark-mode="true" outlined="true" initial-currency="usd"></gecko-coin-price-chart-widget>
        <!-- TradingView Widget BEGIN -->
<div class="tradingview-widget-container">
  <div class="tradingview-widget-container__widget"></div>
  
  <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-screener.js" async>
  {
  "width": "100%",
  "height": "550",
  "defaultColumn": "overview",
  "screener_type": "crypto_mkt",
  "displayCurrency": "USD",
  "colorTheme": "dark",
  "locale": "en"
}
  </script>
</div>
<!-- TradingView Widget END -->
    <style>
        .market-widget {
            background: #23262F;
            border-bottom: 1px solid #2B3139;
            padding: 0 0 0 220px;
            min-height: 54px;
            display: flex;
            align-items: center;
            overflow-x: auto;
        }
        .market-scroll {
            display: flex;
            gap: 32px;
            padding: 0 24px;
            width: 100%;
            overflow-x: auto;
        }
        .market-coin {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #181A20;
            border-radius: 18px;
            padding: 8px 18px;
            min-width: 180px;
            margin: 10px 0;
            font-size: 1.05rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .market-coin .coin {
            color: #F0B90B;
            font-weight: 600;
            margin-right: 8px;
        }
        .market-coin .price {
            color: #EAECEF;
            font-weight: 500;
        }
        .market-coin .change {
            font-weight: 600;
            margin-left: 8px;
        }
        .market-coin .change.up {
            color: #0ECB81;
        }
        .market-coin .change.down {
            color: #F6465D;
        }
        @media (max-width: 900px) {
            .market-widget { padding-left: 60px; }
        }
        @media (max-width: 600px) {
            .market-widget { padding-left: 0; }
            .market-scroll { gap: 12px; padding: 0 4px; }
            .market-coin { min-width: 120px; padding: 6px 8px; font-size: 0.95rem; }
        }
        .dashboard-widgets {
            margin-bottom: 30px;
        }
        .widget-card {
            background: #23262F;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            padding: 28px 32px 22px 32px;
            min-width: 180px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: box-shadow 0.2s, background 0.2s;
            position: relative;
        }
        .widget-card:hover {
            background: #1E2026;
            box-shadow: 0 4px 16px rgba(240,185,11,0.10);
        }
        .widget-icon {
            font-size: 2.1rem;
            margin-bottom: 10px;
            color: #F0B90B;
        }
        .widget-title {
            font-size: 1.08rem;
            color: #B7BDC6;
            margin-bottom: 6px;
        }
        .widget-value {
            font-size: 1.3rem;
            color: #fff;
            font-weight: bold;
        }
        @media (max-width: 900px) {
            .dashboard-widgets { gap: 12px; }
            .widget-card { min-width: 120px; padding: 18px 10px; }
        }
        @media (max-width: 600px) {
            .dashboard-widgets { flex-direction: column; align-items: center; }
        }
    </style>
<?php if ($complaint_reply): ?>
<script>
    // Hide modal if cookie is set
    function getCookie(name) {
        let match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }
    document.addEventListener('DOMContentLoaded', function() {
        if (getCookie('complaint_reply_closed')) {
            var modal = document.getElementById('complaintReplyModal');
            if (modal) modal.style.display = 'none';
        }
    });
</script>
<div id="complaintReplyModal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
    <div style="background:#23262F;padding:32px 28px;border-radius:12px;max-width:400px;width:90%;margin:auto;position:relative;">
        <div style="color:#F0B90B;font-size:1.2rem;font-weight:bold;margin-bottom:10px;">Admin Reply to Your Complaint</div>
        <div style="color:#B7BDC6;font-size:1.05rem;margin-bottom:8px;"><b>Subject:</b> <?php echo htmlspecialchars($complaint_reply['subject']); ?></div>
        <div style="color:#0ECB81;font-size:1.08rem;margin-bottom:12px;"><b>Reply:</b><br><?php echo nl2br(htmlspecialchars($complaint_reply['reply'])); ?></div>
        <div style="color:#B7BDC6;font-size:0.95rem;margin-bottom:10px;">at <?php echo $complaint_reply['replied_at']; ?></div>
        <button id="closeComplaintReplyModal" style="background:#F6465D;color:#fff;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;">Close</button>
    </div>
</div>
<script>
    document.getElementById('closeComplaintReplyModal').onclick = function() {
        document.getElementById('complaintReplyModal').style.display = 'none';
        document.cookie = 'complaint_reply_closed=1; path=/; max-age=31536000'; // 1 year
    };
</script>
<?php endif; ?>
<?php if ($onscreen_msg): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('closeOnscreenMsgModal');
        if (btn) {
            btn.onclick = function() {
                document.getElementById('onscreenMsgModal').style.display = 'none';
                // Mark as read via AJAX
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '?mark_onscreen=1&onscreen_id=<?php echo $onscreen_msg['id']; ?>', true);
                xhr.send();
            };
        }
    });
</script>
<div id="onscreenMsgModal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);z-index:9998;display:flex;align-items:center;justify-content:center;">
    <div style="background:#23262F;padding:32px 28px;border-radius:12px;max-width:400px;width:90%;margin:auto;position:relative;">
        <div style="color:#F0B90B;font-size:1.2rem;font-weight:bold;margin-bottom:10px;">Onscreen Message</div>
        <div style="color:#0ECB81;font-size:1.08rem;margin-bottom:12px;"><b>Message:</b><br><?php echo nl2br(htmlspecialchars($onscreen_msg['message'])); ?></div>
        <div style="color:#B7BDC6;font-size:0.95rem;margin-bottom:10px;">at <?php echo $onscreen_msg['created_at']; ?></div>
        <button id="closeOnscreenMsgModal" style="background:#0ECB81;color:#181A20;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;">Close</button>
    </div>
</div>
<?php endif; ?>
<?php if ($bonus_awarded == 0): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var acceptBtn = document.getElementById('acceptBonusBtn');
        var rejectBtn = document.getElementById('rejectBonusBtn');
        if (acceptBtn) {
            acceptBtn.onclick = function() {
                document.getElementById('bonusModal').style.display = 'none';
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '?bonus_action=accept', true);
                xhr.send();
                alert('Congratulations! $10 bonus has been added to your balance.');
                location.reload();
            };
        }
        if (rejectBtn) {
            rejectBtn.onclick = function() {
                document.getElementById('bonusModal').style.display = 'none';
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '?bonus_action=reject', true);
                xhr.send();
            };
        }
    });
</script>
<div id="bonusModal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);z-index:9997;display:flex;align-items:center;justify-content:center;">
    <div style="background:#23262F;padding:32px 28px;border-radius:12px;max-width:400px;width:90%;margin:auto;position:relative;">
        <div style="color:#F0B90B;font-size:1.2rem;font-weight:bold;margin-bottom:10px;">Congratulations!</div>
        <div style="color:#0ECB81;font-size:1.08rem;margin-bottom:12px;">You have been awarded a <b>$10 bonus</b> for registering.<br>Would you like to accept it?</div>
        <div style="display:flex;gap:18px;justify-content:center;">
            <button id="acceptBonusBtn" style="background:#0ECB81;color:#181A20;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;font-weight:bold;">Accept</button>
            <button id="rejectBonusBtn" style="background:#F6465D;color:#fff;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;font-weight:bold;">Reject</button>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- Referral Link Footer -->
    <div style="background:#23262F;padding:24px 0 18px 0;margin-top:60px;text-align:center;border-radius:0 0 12px 12px;box-shadow:0 -2px 8px rgba(0,0,0,0.10);">
        <?php
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $referral_link = $base_url . "/registration.php?ref=" . urlencode($user_id);
        ?>
        <div style="color:#F0B90B;font-size:1.1rem;font-weight:bold;margin-bottom:8px;">Your Referral Link</div>
        <div style="display:inline-flex;align-items:center;background:#181A20;border-radius:8px;padding:10px 16px;">
            <span id="refLink" style="color:#0ECB81;font-size:1rem;word-break:break-all;"> <?php echo htmlspecialchars($referral_link); ?> </span>
            <button onclick="copyRefLink()" style="background:#F0B90B;color:#181A20;border:none;border-radius:6px;padding:6px 14px;font-weight:bold;cursor:pointer;margin-left:12px;">Copy</button>
        </div>
        <div style="color:#B7BDC6;font-size:0.98rem;margin-top:8px;">Share this link with your friends to earn rewards!</div>
    </div>
    <script>
    function copyRefLink() {
        var refLink = document.getElementById('refLink').innerText.trim();
        navigator.clipboard.writeText(refLink).then(function() {
            alert('Referral link copied to clipboard!');
        });
    }
    </script>
</body>
</html>
