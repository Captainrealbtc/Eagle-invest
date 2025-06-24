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

// Handle reply
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_id'], $_POST['reply'])) {
    $complaint_id = intval($_POST['complaint_id']);
    $reply = trim($_POST['reply']);
    if ($reply !== '') {
        // Update complaint with reply and set status to resolved
        $stmt = $conn->prepare('UPDATE complaints SET reply = ?, replied_at = NOW(), status = "resolved" WHERE id = ?');
        $stmt->bind_param('si', $reply, $complaint_id);
        $stmt->execute();
        $stmt->close();
        // Get user_id for notification
        $stmt = $conn->prepare('SELECT user_id FROM complaints WHERE id = ?');
        $stmt->bind_param('i', $complaint_id);
        $stmt->execute();
        $stmt->bind_result($user_id);
        $stmt->fetch();
        $stmt->close();
        // Send notification to user
        $msg = "Admin replied to your complaint: " . (strlen($reply) > 60 ? substr($reply,0,60).'...' : $reply);
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $stmt->bind_param('is', $user_id, $msg);
        $stmt->execute();
        $stmt->close();
        $actionMsg = 'Reply sent to user.';
    }
}

// Fetch all complaints with usernames
$complaints = [];
$sql = 'SELECT c.*, u.username FROM complaints c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC';
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $complaints[] = $row;
    }
}
$conn->close();
?>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users Complains | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; }
        .admin-bar { background: #23262F; padding: 18px 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #2B3139; }
        .admin-info { font-size: 1.1rem; color: #F0B90B; font-weight: bold; }
        .admin-title { font-size: 1.5rem; color: #F0B90B; font-weight: bold; margin: 40px 0 20px 0; text-align: center; }
        .section-placeholder { background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 40px 30px; max-width: 1100px; margin: 40px auto; color: #B7BDC6; font-size: 1.1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; }
        th { background: #1E2026; color: #F0B90B; }
        tr:nth-child(even) { background: #20232B; }
        .status-pending { color: orange; font-weight: bold; }
        .status-resolved { color: #0ECB81; font-weight: bold; }
        .status-rejected { color: #F6465D; font-weight: bold; }
        .reply-form textarea { width: 100%; padding: 10px; border-radius: 6px; border: none; background: #181A20; color: #EAECEF; font-size: 1rem; min-height: 60px; }
        .reply-btn { background: #F0B90B; color: #181A20; border: none; border-radius: 6px; padding: 8px 18px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: background 0.2s; margin-top: 8px; }
        .reply-btn:hover { background: #b38a00; }
    </style>
</head>
<body>
    <div class="admin-bar">
        <div class="admin-info"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?></div>
        <div style="color:#B7BDC6;">User Complaints Control</div>
    </div>
    <div class="admin-title">All User Complaints</div>
    <?php if ($actionMsg): ?>
        <div style="color:#0ECB81; text-align:center; font-weight:bold; margin-bottom:18px;"> <?php echo htmlspecialchars($actionMsg); ?> </div>
    <?php endif; ?>
    <div class="section-placeholder">
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Subject</th>
                <th>Message</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Reply</th>
            </tr>
            <?php if (empty($complaints)): ?>
                <tr><td colspan="7" style="text-align:center; color:#B7BDC6;">No complaints found.</td></tr>
            <?php else: ?>
                <?php foreach ($complaints as $c): ?>
                    <tr>
                        <td>#<?php echo $c['id']; ?></td>
                        <td><?php echo $c['username'] !== null ? htmlspecialchars($c['username']) : '<span style="color:#B7BDC6;">-</span>'; ?></td>
                        <td><?php echo htmlspecialchars($c['subject']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($c['message'])); ?></td>
                        <td class="status-<?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></td>
                        <td><?php echo $c['created_at']; ?></td>
                        <td>
                            <?php if ($c['reply']): ?>
                                <div style="color:#0ECB81;"><b>Replied:</b><br><?php echo nl2br(htmlspecialchars($c['reply'])); ?><br><span style="font-size:0.95em;color:#B7BDC6;">at <?php echo $c['replied_at']; ?></span></div>
                            <?php elseif ($c['status'] === 'pending'): ?>
                                <form method="post" class="reply-form">
                                    <textarea name="reply" required placeholder="Type your reply..."></textarea>
                                    <input type="hidden" name="complaint_id" value="<?php echo $c['id']; ?>">
                                    <button class="reply-btn" type="submit">Send Reply</button>
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
