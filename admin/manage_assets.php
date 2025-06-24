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

$msg = '';
$barcodes_dir = '../barcodes/';
if (!is_dir($barcodes_dir)) {
    mkdir($barcodes_dir, 0777, true);
}

$section = $_GET['section'] ?? 'wallets';

// Handle P2P bank detail actions
if ($section === 'p2p') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_p2p') {
        $stmt = $conn->prepare("INSERT INTO p2p_bank_details (bank_name, account_number, account_name, branch_code, bank_address, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssi', $_POST['bank_name'], $_POST['account_number'], $_POST['account_name'], $_POST['branch_code'], $_POST['bank_address'], $_POST['is_active']);
        $stmt->execute();
        $stmt->close();
        $msg = 'P2P bank detail added.';
    } elseif (isset($_GET['activate']) && isset($_GET['id'])) {
        $conn->query("UPDATE p2p_bank_details SET is_active=0");
        $stmt = $conn->prepare("UPDATE p2p_bank_details SET is_active=1 WHERE id=?");
        $stmt->bind_param('i', $_GET['id']);
        $stmt->execute();
        $stmt->close();
        $msg = 'P2P bank detail activated.';
    } elseif (isset($_GET['delete']) && isset($_GET['id'])) {
        $stmt = $conn->prepare("DELETE FROM p2p_bank_details WHERE id=?");
        $stmt->bind_param('i', $_GET['id']);
        $stmt->execute();
        $stmt->close();
        $msg = 'P2P bank detail deleted.';
    }
    // Fetch all P2P bank details
    $p2p_banks = [];
    $res = $conn->query("SELECT * FROM p2p_bank_details ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) {
        $p2p_banks[] = $row;
    }
}

// Handle wallet address removal
if ($section === 'wallets' && isset($_GET['remove']) && isset($_GET['coin'])) {
    $coin = $_GET['coin'];
    $stmt = $conn->prepare('UPDATE deposit_addresses SET address = NULL WHERE coin_symbol = ?');
    $stmt->bind_param('s', $coin);
    $stmt->execute();
    $stmt->close();
    $msg = 'Wallet address removed.';
}
// Handle wallet address update
if ($section === 'wallets' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coin_symbol']) && isset($_POST['wallet_address'])) {
    $coin = $_POST['coin_symbol'];
    $wallet_address = trim($_POST['wallet_address']);
    $stmt = $conn->prepare('UPDATE deposit_addresses SET address = ? WHERE coin_symbol = ?');
    $stmt->bind_param('ss', $wallet_address, $coin);
    $stmt->execute();
    $stmt->close();
    $msg = 'Wallet address updated.';
}
// Handle barcode removal
if ($section === 'barcodes' && isset($_GET['remove']) && isset($_GET['coin'])) {
    $coin = $_GET['coin'];
    $stmt = $conn->prepare('SELECT barcode_image FROM deposit_addresses WHERE coin_symbol = ?');
    $stmt->bind_param('s', $coin);
    $stmt->execute();
    $stmt->bind_result($barcode_image);
    $stmt->fetch();
    $stmt->close();
    if ($barcode_image && file_exists($barcodes_dir . basename($barcode_image))) {
        unlink($barcodes_dir . basename($barcode_image));
    }
    $stmt = $conn->prepare('UPDATE deposit_addresses SET barcode_image = NULL WHERE coin_symbol = ?');
    $stmt->bind_param('s', $coin);
    $stmt->execute();
    $stmt->close();
    $msg = 'Barcode removed.';
}
// Handle barcode upload
if ($section === 'barcodes' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coin_symbol']) && isset($_FILES['barcode'])) {
    $coin = $_POST['coin_symbol'];
    $file = $_FILES['barcode'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'gif'];
        if (in_array($ext, $allowed)) {
            $filename = $coin . '_' . time() . '.' . $ext;
            $dest = $barcodes_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $barcode_path = 'barcodes/' . $filename;
                $stmt = $conn->prepare('UPDATE deposit_addresses SET barcode_image = ? WHERE coin_symbol = ?');
                $stmt->bind_param('ss', $barcode_path, $coin);
                $stmt->execute();
                $stmt->close();
                $msg = 'Barcode uploaded.';
            } else {
                $msg = 'Failed to upload file.';
            }
        } else {
            $msg = 'Invalid file type.';
        }
    } else {
        $msg = 'Error uploading file.';
    }
}
// Fetch all deposit addresses
$addresses = [];
$res = $conn->query('SELECT * FROM deposit_addresses ORDER BY coin_symbol');
while ($row = $res->fetch_assoc()) {
    $addresses[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Assets | Admin</title>
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
        .container { max-width: 1000px; margin: 40px auto; background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 36px 24px; }
        .title { color: #F0B90B; font-size: 2rem; font-weight: bold; margin-bottom: 24px; text-align: center; }
        .tabs { display: flex; gap: 18px; justify-content: center; margin-bottom: 30px; }
        .tab-btn { background: #23262F; color: #F0B90B; border: 2px solid #F0B90B; border-radius: 8px 8px 0 0; padding: 10px 32px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: background 0.2s, color 0.2s; }
        .tab-btn.active, .tab-btn:hover { background: #F0B90B; color: #181A20; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 12px 10px; text-align: left; }
        th { background: #1E2026; color: #F0B90B; }
        tr:nth-child(even) { background: #20232B; }
        .barcode-img { max-width: 90px; max-height: 90px; border-radius: 8px; background: #fff; border: 1px solid #2B3139; }
        .upload-form, .wallet-form { display: flex; align-items: center; gap: 10px; }
        .remove-btn { background: #F6465D; color: #fff; border: none; border-radius: 6px; padding: 6px 14px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .remove-btn:hover { background: #a8322a; }
        .msg { color: #0ECB81; text-align: center; margin-bottom: 18px; font-weight: bold; }
        @media (max-width: 900px) { .sidebar { width: 60px; } .sidebar .logo { font-size: 1.2rem; } .sidebar nav a span { display: none; } .main-content { margin-left: 60px; } }
        @media (max-width: 700px) { .container { max-width: 98vw; padding: 12px 2vw; } table, th, td { font-size: 0.98rem; } }
        @media (max-width: 600px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><i class="fa-solid fa-coins"></i> EagleInvest</div>
        <nav>
            <a href="admin_dashboard.php?page=dashboard"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a>
            <a href="users_deposit.php"><i class="fa-solid fa-arrow-down"></i> <span>User Deposits</span></a>
            <a href="user_withdrawal.php"><i class="fa-solid fa-arrow-up"></i> <span>User Withdrawals</span></a>
            <a href="user_verifications.php"><i class="fa-solid fa-user-check"></i> <span>User Verifications</span></a>
            <a href="send_notification.php"><i class="fa-solid fa-bell"></i> <span>Send Notification</span></a>
            <a href="complaints_admin.php"><i class="fa-solid fa-comment-dots"></i> <span>User Complaints</span></a>
            <a href="admin_dashboard.php?page=deposits"><i class="fa-solid fa-arrow-down"></i> <span>Deposits</span></a>
            <a href="admin_dashboard.php?page=withdrawals"><i class="fa-solid fa-arrow-up"></i> <span>Withdrawals</span></a>
            <a href="admin_dashboard.php?page=notifications"><i class="fa-regular fa-bell"></i> <span>Notifications</span></a>
            <a href="admin_dashboard.php?page=verifications"><i class="fa-solid fa-user-check"></i> <span>Verifications</span></a>
            <a href="admin_dashboard.php?page=complaints"><i class="fa-solid fa-comment-dots"></i> <span>Complaints</span></a>
            <a href="admin_dashboard.php?page=wallets"><i class="fa-solid fa-wallet"></i> <span>Wallets/Addresses</span></a>
            <a href="admin_users.php"><i class="fa-solid fa-users"></i> <span>Users</span></a>
            <a href="user_wallet.php"><i class="fa-solid fa-wallet"></i> <span>User Wallets</span></a>
            <a href="manage_assets.php" class="active"><i class="fa-solid fa-layer-group"></i> <span>Manage Assets</span></a>
        </nav>
        <a class="logout" href="../logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="container">
            <div class="title"><i class="fa-solid fa-layer-group"></i> Manage Assets</div>
            <div class="tabs">
                <a href="?section=wallets" class="tab-btn<?php if($section==='wallets') echo ' active'; ?>">Manage Wallets</a>
                <a href="?section=barcodes" class="tab-btn<?php if($section==='barcodes') echo ' active'; ?>">Manage Barcodes</a>
                <a href="?section=p2p" class="tab-btn<?php if($section==='p2p') echo ' active'; ?>">P2P Bank Details</a>
            </div>
            <?php if ($msg): ?><div class="msg"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
            <?php if ($section === 'wallets'): ?>
            <table>
                <tr>
                    <th>Coin</th>
                    <th>Wallet Address</th>
                    <th>Update</th>
                    <th>Remove</th>
                </tr>
                <?php foreach ($addresses as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['coin_name'] . ' (' . $row['coin_symbol'] . ')'); ?></td>
                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                    <td>
                        <form class="wallet-form" method="post" style="margin:0;">
                            <input type="hidden" name="coin_symbol" value="<?php echo htmlspecialchars($row['coin_symbol']); ?>">
                            <input type="text" name="wallet_address" value="<?php echo htmlspecialchars($row['address']); ?>" placeholder="Enter wallet address" required style="width:220px;">
                            <button type="submit" class="copy-btn">Update</button>
                        </form>
                    </td>
                    <td>
                        <?php if ($row['address']): ?>
                            <a href="?section=wallets&remove=1&coin=<?php echo urlencode($row['coin_symbol']); ?>" class="remove-btn" onclick="return confirm('Remove wallet address for <?php echo htmlspecialchars($row['coin_symbol']); ?>?');">Remove</a>
                        <?php else: ?>
                            <span style="color:#B7BDC6;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php elseif ($section === 'barcodes'): ?>
            <table>
                <tr>
                    <th>Coin</th>
                    <th>Barcode</th>
                    <th>Upload/Replace</th>
                    <th>Remove</th>
                </tr>
                <?php foreach ($addresses as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['coin_name'] . ' (' . $row['coin_symbol'] . ')'); ?></td>
                    <td>
                        <?php if ($row['barcode_image']): ?>
                            <img src="../<?php echo htmlspecialchars($row['barcode_image']); ?>" class="barcode-img" alt="Barcode">
                        <?php else: ?>
                            <span style="color:#F6465D;">No barcode</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form class="upload-form" method="post" enctype="multipart/form-data" style="margin:0;">
                            <input type="hidden" name="coin_symbol" value="<?php echo htmlspecialchars($row['coin_symbol']); ?>">
                            <input type="file" name="barcode" accept="image/*" required>
                            <button type="submit" class="copy-btn">Upload</button>
                        </form>
                    </td>
                    <td>
                        <?php if ($row['barcode_image']): ?>
                            <a href="?section=barcodes&remove=1&coin=<?php echo urlencode($row['coin_symbol']); ?>" class="remove-btn" onclick="return confirm('Remove barcode for <?php echo htmlspecialchars($row['coin_symbol']); ?>?');">Remove</a>
                        <?php else: ?>
                            <span style="color:#B7BDC6;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php elseif ($section === 'p2p'): ?>
            <div style="max-width:600px;margin:0 auto;">
                <form method="post" style="background:#23262F;padding:18px;border-radius:10px;margin-bottom:30px;">
                    <input type="hidden" name="action" value="add_p2p">
                    <label>Bank Name</label><input type="text" name="bank_name" required>
                    <label>Account Number</label><input type="text" name="account_number" required>
                    <label>Account Name</label><input type="text" name="account_name" required>
                    <label>Branch Code</label><input type="text" name="branch_code">
                    <label>Bank Address</label><input type="text" name="bank_address">
                    <label>Active</label><select name="is_active"><option value="1">Yes</option><option value="0">No</option></select>
                    <button class="copy-btn" type="submit">Add P2P Bank</button>
                </form>
                <table>
                    <tr><th>ID</th><th>Bank Name</th><th>Account Number</th><th>Account Name</th><th>Branch Code</th><th>Bank Address</th><th>Active</th><th>Actions</th></tr>
                    <?php foreach ($p2p_banks as $bank): ?>
                    <tr<?php if ($bank['is_active']) echo ' style="background:#0ECB81;color:#181A20;"'; ?>>
                        <td><?php echo $bank['id']; ?></td>
                        <td><?php echo htmlspecialchars($bank['bank_name']); ?></td>
                        <td><?php echo htmlspecialchars($bank['account_number']); ?></td>
                        <td><?php echo htmlspecialchars($bank['account_name']); ?></td>
                        <td><?php echo htmlspecialchars($bank['branch_code']); ?></td>
                        <td><?php echo htmlspecialchars($bank['bank_address']); ?></td>
                        <td><?php echo $bank['is_active'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <a href="?section=p2p&activate=1&id=<?php echo $bank['id']; ?>" class="copy-btn">Activate</a>
                            <a href="?section=p2p&delete=1&id=<?php echo $bank['id']; ?>" class="remove-btn" onclick="return confirm('Delete this bank detail?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
