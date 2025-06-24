<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ./admin_login.php');
    exit();
}
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';

$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed.');
}

// Handle approve/reject actions for crypto deposits
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_id'], $_POST['action'])) {
    $deposit_id = intval($_POST['deposit_id']);
    $action = $_POST['action'];
    $stmt = $conn->prepare('SELECT user_id, amount, coin_symbol FROM deposits WHERE id = ?');
    $stmt->bind_param('i', $deposit_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount, $coin_symbol);
    $fetchSuccess = $stmt->fetch();
    $stmt->close();
    if ($fetchSuccess) {
        if ($action === 'approve') {
            $stmt = $conn->prepare('UPDATE deposits SET status = "approved" WHERE id = ?');
            $stmt->bind_param('i', $deposit_id);
            $stmt->execute();
            $stmt->close();
            $msg = "Your deposit of " . number_format($amount,2) . " $coin_symbol has been approved.\n And will be credited into your main account balance. Thanks for trading with Us.";
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->bind_param('is', $user_id, $msg);
            $stmt->execute();
            $stmt->close();
            $actionMsg = 'Deposit approved.';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare('UPDATE deposits SET status = "rejected" WHERE id = ?');
            $stmt->bind_param('i', $deposit_id);
            $stmt->execute();
            $stmt->close();
            $msg = "Your deposit of " . number_format($amount,2) . " $coin_symbol was rejected.";
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->bind_param('is', $user_id, $msg);
            $stmt->execute();
            $stmt->close();
            $actionMsg = 'Deposit rejected.';
        }
    } else {
        $actionMsg = 'Error: Deposit not found.';
    }
}

// Handle approve/reject actions for P2P deposits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['p2p_id'], $_POST['p2p_action'])) {
    $p2p_id = intval($_POST['p2p_id']);
    $action = $_POST['p2p_action'];
    $stmt = $conn->prepare('SELECT user_id, amount FROM p2p_deposits WHERE id = ?');
    $stmt->bind_param('i', $p2p_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount);
    $fetchSuccess = $stmt->fetch();
    $stmt->close();
    if ($fetchSuccess) {
        if ($action === 'approve') {
            $stmt = $conn->prepare('UPDATE p2p_deposits SET status = "approved" WHERE id = ?');
            $stmt->bind_param('i', $p2p_id);
            $stmt->execute();
            $stmt->close();
            $msg = "Your P2P deposit of " . number_format($amount,2) . " has been approved and will be credited to your account.";
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->bind_param('is', $user_id, $msg);
            $stmt->execute();
            $stmt->close();
            $actionMsg = 'P2P deposit approved.';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare('UPDATE p2p_deposits SET status = "rejected" WHERE id = ?');
            $stmt->bind_param('i', $p2p_id);
            $stmt->execute();
            $stmt->close();
            $msg = "Your P2P deposit of " . number_format($amount,2) . " was rejected.";
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->bind_param('is', $user_id, $msg);
            $stmt->execute();
            $stmt->close();
            $actionMsg = 'P2P deposit rejected.';
        }
    } else {
        $actionMsg = 'Error: P2P deposit not found.';
    }
}

// Handle approve/reject actions for Gift Card deposits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['giftcard_id'], $_POST['giftcard_action'])) {
    $giftcard_id = intval($_POST['giftcard_id']);
    $action = $_POST['giftcard_action'];
    $stmt = $conn->prepare('SELECT user_id, card_amount, card_name FROM giftcard_deposits WHERE id = ?');
    $stmt->bind_param('i', $giftcard_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $card_amount, $card_name);
    $fetchSuccess = $stmt->fetch();
    $stmt->close();
    if ($fetchSuccess) {
        if ($action === 'approve') {
            $stmt = $conn->prepare('UPDATE giftcard_deposits SET status = "approved" WHERE id = ?');
            $stmt->bind_param('i', $giftcard_id);
            $stmt->execute();
            $stmt->close();
            $msg = "Your gift card deposit ($card_name, amount: " . number_format($card_amount,2) . ") has been approved and will be processed.";
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->bind_param('is', $user_id, $msg);
            $stmt->execute();
            $stmt->close();
            $actionMsg = 'Gift card deposit approved.';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare('UPDATE giftcard_deposits SET status = "rejected" WHERE id = ?');
            $stmt->bind_param('i', $giftcard_id);
            $stmt->execute();
            $stmt->close();
            $msg = "Your gift card deposit ($card_name, amount: " . number_format($card_amount,2) . ") was rejected.";
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->bind_param('is', $user_id, $msg);
            $stmt->execute();
            $stmt->close();
            $actionMsg = 'Gift card deposit rejected.';
        }
    } else {
        $actionMsg = 'Error: Gift card deposit not found.';
    }
}

// (removed duplicate connection code)

// Handle approve/reject actions
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_id'], $_POST['action'])) {
    $deposit_id = intval($_POST['deposit_id']);
    $action = $_POST['action'];
    // Get user_id and amount for notification
    $stmt = $conn->prepare('SELECT user_id, amount, coin_symbol FROM deposits WHERE id = ?');
    $stmt->bind_param('i', $deposit_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount, $coin_symbol);
    $fetchSuccess = $stmt->fetch();
    $stmt->close();
    if ($fetchSuccess) {
        if ($action === 'approve') {
            // Approve deposit
            $stmt = $conn->prepare('UPDATE deposits SET status = "approved" WHERE id = ?');
            $stmt->bind_param('i', $deposit_id);
            $stmt->execute();
            $stmt->close();
            // Notify user
            $msg = "Your deposit of " . number_format($amount,2) . " $coin_symbol has been approved.
             And will be credited into your main account balance. Thanks for trading with Us.";
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->bind_param('is', $user_id, $msg);
            $stmt->execute();
            $stmt->close();
            $actionMsg = 'Deposit approved.';
        } elseif ($action === 'reject') {
            // Reject deposit
            $stmt = $conn->prepare('UPDATE deposits SET status = "rejected" WHERE id = ?');
            $stmt->bind_param('i', $deposit_id);
            $stmt->execute();
            $stmt->close();
            // Notify user
            $msg = "Your deposit of " . number_format($amount,2) . " $coin_symbol was rejected.";
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->bind_param('is', $user_id, $msg);
            $stmt->execute();
            $stmt->close();
            $actionMsg = 'Deposit rejected.';
        }
    } else {
        $actionMsg = 'Error: Deposit not found.';
    }
}

// Fetch all deposits
$deposits = [];
$sql = 'SELECT d.*, u.username FROM deposits d LEFT JOIN users u ON d.user_id = u.id ORDER BY d.created_at DESC';
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $deposits[] = $row;
    }
}
// Fetch all P2P deposits
$p2p_deposits = [];
$sql = 'SELECT p.*, u.username FROM p2p_deposits p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC';
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $p2p_deposits[] = $row;
    }
}
// Fetch all gift card deposits
$giftcards = [];
$sql = 'SELECT g.*, u.username FROM giftcard_deposits g LEFT JOIN users u ON g.user_id = u.id ORDER BY g.created_at DESC';
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $giftcards[] = $row;
    }
}
// (moved $conn->close() to end of file)
?>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Users Deposit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; }
        .admin-title { font-size: 1.5rem; color: #F0B90B; font-weight: bold; margin: 40px 0 20px 0; text-align: center; }
        .admin-bar { background: #23262F; padding: 18px 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #2B3139; }
        .admin-info { font-size: 1.1rem; color: #F0B90B; font-weight: bold; }
        .section-placeholder { background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 40px 30px; max-width: 1100px; margin: 40px auto; color: #B7BDC6; font-size: 1.1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; }
        th { background: #1E2026; color: #F0B90B; }
        tr:nth-child(even) { background: #20232B; }
        .status-approved { color: #0ECB81; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .status-rejected { color: #F6465D; font-weight: bold; }
        .action-btn { border: none; border-radius: 5px; padding: 6px 16px; font-size: 1rem; cursor: pointer; margin-right: 8px; }
        .approve-btn { background: #0ECB81; color: #fff; }
        .reject-btn { background: #F6465D; color: #fff; }
        .approve-btn:hover { background: #099e5c; }
        .reject-btn:hover { background: #a8322a; }
        .img-thumb { max-width: 120px; max-height: 80px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="admin-bar">
        <div class="admin-info"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?></div>
        <div style="color:#B7BDC6;">User Deposits Control</div>
    </div>
    <div class="admin-title">All User Deposits</div>
    <div style="text-align:center; margin-bottom:18px;">
        <a href="?show=p2p" class="action-btn" style="background:#F0B90B;color:#181A20;">P2P Deposits</a>
        <a href="?show=giftcard" class="action-btn" style="background:#F0B90B;color:#181A20;">Gift Card Deposits</a>
        <a href="?" class="action-btn" style="background:#23262F;color:#F0B90B;">All Deposits</a>
    </div>
    <?php if ($actionMsg): ?>
        <div style="color:#F0B90B; text-align:center; font-weight:bold; margin-bottom:18px;"> <?php echo htmlspecialchars($actionMsg); ?> </div>
    <?php endif; ?>
    <div class="section-placeholder">
        <?php $show = $_GET['show'] ?? ''; ?>
        <?php if ($show === '' || $show === 'crypto'): ?>
        <h3 style="color:#F0B90B;">Crypto Deposits</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Amount</th>
                <th>Coin</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php if (empty($deposits)): ?>
                <tr><td colspan="7" style="text-align:center; color:#B7BDC6;">No deposits found.</td></tr>
            <?php else: ?>
                <?php foreach ($deposits as $dep): ?>
                    <tr>
                        <td>#<?php echo $dep['id']; ?></td>
                        <td><?php echo $dep['username'] !== null ? htmlspecialchars($dep['username']) : '<span style="color:#B7BDC6;">-</span>'; ?></td>
                        <td> <?php echo number_format($dep['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($dep['coin_symbol']); ?></td>
                        <td class="status-<?php echo strtolower($dep['status']); ?>"><?php echo ucfirst($dep['status']); ?></td>
                        <td><?php echo $dep['created_at']; ?></td>
                        <td>
                            <?php if ($dep['status'] === 'pending'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="deposit_id" value="<?php echo $dep['id']; ?>">
                                    <button name="action" value="approve" class="action-btn approve-btn">Approve</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="deposit_id" value="<?php echo $dep['id']; ?>">
                                    <button name="action" value="reject" class="action-btn reject-btn">Reject</button>
                                </form>
                            <?php else: ?>
                                <span style="color:#B7BDC6;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
        <?php endif; ?>
        <?php if ($show === 'p2p'): ?>
        <h3 style="color:#F0B90B;">P2P Deposits</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Amount</th>
                <th>Bank Name</th>
                <th>Account Number</th>
                <th>Account Name</th>
                <th>Branch Code</th>
                <th>Bank Address</th>
                <th>Payment Proof</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php if (empty($p2p_deposits)): ?>
                <tr><td colspan="12" style="text-align:center; color:#B7BDC6;">No P2P deposits found.</td></tr>
            <?php else: ?>
                <?php foreach ($p2p_deposits as $p): ?>
                <tr>
                    <td>#<?php echo $p['id']; ?></td>
                    <td><?php echo $p['username'] !== null ? htmlspecialchars($p['username']) : '<span style="color:#B7BDC6;">-</span>'; ?></td>
                    <td><?php echo number_format($p['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($p['bank_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['account_number']); ?></td>
                    <td><?php echo htmlspecialchars($p['account_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['branch_code']); ?></td>
                    <td><?php echo htmlspecialchars($p['bank_address']); ?></td>
                    <td><?php if ($p['payment_proof']): ?><a href="../<?php echo $p['payment_proof']; ?>" target="_blank"><img src="../<?php echo $p['payment_proof']; ?>" class="img-thumb"></a><?php endif; ?></td>
                    <td class="status-<?php echo strtolower($p['status']); ?>"><?php echo ucfirst($p['status']); ?></td>
                    <td><?php echo $p['created_at']; ?></td>
                    <td>
                        <?php if ($p['status'] === 'pending'): ?>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="p2p_action" value="approve">
                            <input type="hidden" name="p2p_id" value="<?php echo $p['id']; ?>">
                            <button class="action-btn approve-btn" type="submit">Approve</button>
                        </form>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="p2p_action" value="reject">
                            <input type="hidden" name="p2p_id" value="<?php echo $p['id']; ?>">
                            <button class="action-btn reject-btn" type="submit">Reject</button>
                        </form>
                        <?php else: ?>
                            <span style="color:#B7BDC6;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
        <?php endif; ?>
        <?php if ($show === 'giftcard'): ?>
        <h3 style="color:#F0B90B;">Gift Card Deposits</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Card Name</th>
                <th>Amount</th>
                <th>Code</th>
                <th>Category</th>
                <th>Image</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php if (empty($giftcards)): ?>
                <tr><td colspan="10" style="text-align:center; color:#B7BDC6;">No gift card deposits found.</td></tr>
            <?php else: ?>
                <?php foreach ($giftcards as $g): ?>
                <tr>
                    <td>#<?php echo $g['id']; ?></td>
                    <td><?php echo $g['username'] !== null ? htmlspecialchars($g['username']) : '<span style="color:#B7BDC6;">-</span>'; ?></td>
                    <td><?php echo htmlspecialchars($g['card_name']); ?></td>
                    <td><?php echo number_format($g['card_amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($g['card_code']); ?></td>
                    <td><?php echo htmlspecialchars($g['card_category']); ?></td>
                    <td><?php if ($g['card_image']): ?><a href="../<?php echo $g['card_image']; ?>" target="_blank"><img src="../<?php echo $g['card_image']; ?>" class="img-thumb"></a><?php endif; ?></td>
                    <td class="status-<?php echo strtolower($g['status']); ?>"><?php echo ucfirst($g['status']); ?></td>
                    <td><?php echo $g['created_at']; ?></td>
                    <td>
                        <?php if ($g['status'] === 'pending'): ?>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="giftcard_action" value="approve">
                            <input type="hidden" name="giftcard_id" value="<?php echo $g['id']; ?>">
                            <button class="action-btn approve-btn" type="submit">Approve</button>
                        </form>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="giftcard_action" value="reject">
                            <input type="hidden" name="giftcard_id" value="<?php echo $g['id']; ?>">
                            <button class="action-btn reject-btn" type="submit">Reject</button>
                        </form>
                        <?php else: ?>
                            <span style="color:#B7BDC6;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
        <?php endif; ?>
    </div>
<?php $conn->close(); ?>
</body>
</html>
