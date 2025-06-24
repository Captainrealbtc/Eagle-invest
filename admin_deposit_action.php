<?php
// Simple admin deposit action page for approving/rejecting deposits and sending notifications
session_start();
// For real use, add admin authentication here

$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Notification function
require_once 'notify_user.php';
// Handle admin action
$action_success = false;
$action_error = '';
if (isset($_POST['deposit_id']) && isset($_POST['action'])) {
    $deposit_id = intval($_POST['deposit_id']);
    $action = $_POST['action'];
    if (!in_array($action, ['approved', 'rejected'])) {
        $action_error = 'Invalid action.';
    } else {
        // Get deposit info
        $stmt = $conn->prepare("SELECT user_id, username, coin_symbol, amount FROM deposits WHERE id = ?");
        $stmt->bind_param('i', $deposit_id);
        $stmt->execute();
        $stmt->bind_result($user_id, $username, $coin, $amount);
        if ($stmt->fetch()) {
            $stmt->close();
            // Update deposit status
            $stmt2 = $conn->prepare("UPDATE deposits SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt2->bind_param('si', $action, $deposit_id);
            if ($stmt2->execute()) {
                // Insert notification using notify_user function
                $message = "Your deposit of $amount $coin was " . ucfirst($action) . " by admin.";
                notify_user($user_id, $message);
                $action_success = true;
            } else {
                $action_error = 'Failed to update deposit.';
            }
            $stmt2->close();
        } else {
            $action_error = 'Deposit not found.';
            $stmt->close();
        }
    }
}

// Fetch all pending deposits
$pending = [];
$res = $conn->query("SELECT * FROM deposits WHERE status = 'pending' ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) {
    $pending[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Deposit Actions</title>
    <style>
        body { background: #181A20; color: #EAECEF; font-family: Arial, sans-serif; }
        .container { max-width: 700px; margin: 40px auto; background: #23262F; border-radius: 12px; padding: 30px; }
        h2 { color: #F0B90B; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; }
        th { color: #B7BDC6; border-bottom: 1px solid #2B3139; }
        td { border-bottom: 1px solid #2B3139; }
        tr:last-child td { border-bottom: none; }
        .actions { display: flex; gap: 10px; }
        .btn { padding: 7px 18px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .approve { background: #0ECB81; color: #fff; }
        .reject { background: #F6465D; color: #fff; }
        .msg-success { color: #0ECB81; text-align: center; margin-bottom: 10px; }
        .msg-error { color: #F6465D; text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Admin Deposit Actions</h2>
        <?php if ($action_success): ?>
            <div class="msg-success">Action completed and user notified!</div>
        <?php elseif ($action_error): ?>
            <div class="msg-error"><?php echo htmlspecialchars($action_error); ?></div>
        <?php endif; ?>
        <table>
            <tr><th>ID</th><th>User</th><th>Coin</th><th>Amount</th><th>Date</th><th>Actions</th></tr>
            <?php foreach ($pending as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['coin_symbol']); ?></td>
                    <td><?php echo htmlspecialchars($row['amount']); ?></td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td class="actions">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="deposit_id" value="<?php echo $row['id']; ?>">
                            <button class="btn approve" name="action" value="approved" type="submit">Approve</button>
                        </form>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="deposit_id" value="<?php echo $row['id']; ?>">
                            <button class="btn reject" name="action" value="rejected" type="submit">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pending)): ?>
                <tr><td colspan="6" style="text-align:center;color:#B7BDC6;">No pending deposits.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>
