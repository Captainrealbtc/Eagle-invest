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

// Handle approve/reject actions
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdrawal_id'], $_POST['action'])) {
    $withdrawal_id = intval($_POST['withdrawal_id']);
    $action = $_POST['action'];
    // Get user_id, amount, method for notification
    $stmt = $conn->prepare('SELECT user_id, amount, method FROM withdrawals WHERE id = ?');
    $stmt->bind_param('i', $withdrawal_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount, $method);
    $stmt->fetch();
    $stmt->close();
    if ($action === 'approve') {
        $stmt = $conn->prepare('UPDATE withdrawals SET status = "approved" WHERE id = ?');
        $stmt->bind_param('i', $withdrawal_id);
        $stmt->execute();
        $stmt->close();
        // Notify user
        $msg = "Your withdrawal of $" . number_format($amount,2) . " via " . ucfirst($method) . " has been approved, and
        will reflect on your specified method after been confirmed by the blockchain networks. Thanks for trading with Us.";
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $stmt->bind_param('is', $user_id, $msg);
        $stmt->execute();
        $stmt->close();
        $actionMsg = 'Withdrawal approved.';
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare('UPDATE withdrawals SET status = "rejected" WHERE id = ?');
        $stmt->bind_param('i', $withdrawal_id);
        $stmt->execute();
        $stmt->close();
        // Notify user
        $msg = "Your withdrawal of $" . number_format($amount,2) . " via " . ucfirst($method) . " was rejected. ";
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $stmt->bind_param('is', $user_id, $msg);
        $stmt->execute();
        $stmt->close();
        $actionMsg = 'Withdrawal rejected.';
    }
}

// Fetch all withdrawals
$withdrawals = [];
$sql = 'SELECT w.*, u.username FROM withdrawals w LEFT JOIN users u ON w.user_id = u.id ORDER BY w.created_at DESC';
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $withdrawals[] = $row;
    }
}

// Fetch recently approved withdrawals (last 10)
$recent_approved = [];
$sql = 'SELECT w.amount, u.username FROM withdrawals w LEFT JOIN users u ON w.user_id = u.id WHERE w.status = "approved" ORDER BY w.updated_at DESC, w.created_at DESC LIMIT 10';
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_approved[] = $row;
    }
}
$conn->close();
?>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Users Withdrawals</title>
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
        .recent-approved { background: #1E2026; border-radius: 10px; padding: 18px 24px; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto; }
        .recent-approved-title { color: #F0B90B; font-size: 1.2rem; font-weight: bold; margin-bottom: 12px; }
        .recent-approved-list { list-style: none; padding: 0; margin: 0; }
        .recent-approved-list li { color: #0ECB81; font-weight: bold; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="admin-bar">
        <div class="admin-info"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?></div>
        <div style="color:#B7BDC6;">User Withdrawals Control</div>
    </div>
    <div class="admin-title">All User Withdrawals</div>
    <?php if ($actionMsg): ?>
        <div style="color:#F0B90B; text-align:center; font-weight:bold; margin-bottom:18px;"> <?php echo htmlspecialchars($actionMsg); ?> </div>
    <?php endif; ?>
    <div class="recent-approved">
        <div class="recent-approved-title"><i class="fa-solid fa-check-circle"></i> Recently Approved Withdrawals</div>
        <ul class="recent-approved-list">
            <?php if (empty($recent_approved)): ?>
                <li style="color:#B7BDC6;">No recent approved withdrawals.</li>
            <?php else: ?>
                <?php foreach ($recent_approved as $ra): ?>
                    <li><?php echo $ra['username'] !== null ? htmlspecialchars($ra['username']) : '<span style="color:#B7BDC6;">-</span>'; ?> - $<?php echo number_format($ra['amount'], 2); ?></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
    <div class="section-placeholder">
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Details</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php if (empty($withdrawals)): ?>
                <tr><td colspan="7" style="text-align:center; color:#B7BDC6;">No withdrawals found.</td></tr>
            <?php else: ?>
                <?php foreach ($withdrawals as $wd): ?>
                    <tr>
                        <td>#<?php echo $wd['id']; ?></td>
                        <td><?php echo $wd['username'] !== null ? htmlspecialchars($wd['username']) : '<span style="color:#B7BDC6;">-</span>'; ?></td>
                        <td>$<?php echo number_format($wd['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($wd['method'])); ?></td>
                        <td style="font-size:0.98em;color:#B7BDC6;max-width:220px;word-break:break-word;"><?php echo nl2br(htmlspecialchars($wd['details'])); ?></td>
                        <td class="status-<?php echo strtolower($wd['status']); ?>"><?php echo ucfirst($wd['status']); ?></td>
                        <td><?php echo $wd['created_at']; ?></td>
                        <td>
                            <?php if ($wd['status'] === 'pending'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="withdrawal_id" value="<?php echo $wd['id']; ?>">
                                    <button name="action" value="approve" class="action-btn approve-btn">Approve</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="withdrawal_id" value="<?php echo $wd['id']; ?>">
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
    </div>
</body>
</html>
