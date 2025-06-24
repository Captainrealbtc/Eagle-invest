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

// Fetch all users
$users = [];
$res = $conn->query('SELECT id, username, email FROM users ORDER BY username');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && isset($_POST['recipients'])) {
    $message = trim($_POST['message']);
    $recipients = $_POST['recipients'];
    $notif_type = $_POST['notif_type'] ?? 'notification';
    if ($message === '') {
        $msg = 'Message cannot be empty!';
    } elseif (empty($recipients)) {
        $msg = 'Please select at least one user.';
    } else {
        $is_onscreen = ($notif_type === 'onscreen') ? 1 : 0;
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, message, is_onscreen) VALUES (?, ?, ?)');
        foreach ($recipients as $uid) {
            $stmt->bind_param('isi', $uid, $message, $is_onscreen);
            $stmt->execute();
        }
        $stmt->close();
        if ($notif_type === 'onscreen') {
            $msg = 'Onscreen message sent!';
        } else {
            $msg = 'Notification sent!';
        }
    }
}
// Fetch all notifications sent by admin (grouped by message and date)
$conn = new mysqli($host, $user, $pass, $db);
$sent_notifications = [];
$sql = "SELECT n.message, n.created_at, n.is_read, u.username FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT 100";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sent_notifications[] = $row;
    }
}
$conn->close();
?>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Send notifications | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; }
        .admin-bar { background: #23262F; padding: 18px 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #2B3139; }
        .admin-info { font-size: 1.1rem; color: #F0B90B; font-weight: bold; }
        .notify-container { max-width: 520px; margin: 40px auto; background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 36px 36px 28px 36px; }
        .notify-title { color: #F0B90B; font-size: 1.5rem; font-weight: bold; margin-bottom: 18px; text-align: center; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; margin-bottom: 8px; color: #B7BDC6; }
        .form-group textarea { width: 100%; padding: 12px; border-radius: 6px; border: none; background: #181A20; color: #EAECEF; font-size: 1rem; min-height: 80px; }
        .form-group select, .form-group input[type=checkbox] { margin-right: 8px; }
        .notify-btn { width: 100%; background: #F0B90B; color: #181A20; border: none; border-radius: 6px; padding: 14px 0; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .notify-btn:hover { background: #b38a00; }
        .msg { color: #0ECB81; text-align: center; margin-bottom: 18px; font-weight: bold; }
        .error { color: #F6465D; }
        .user-list { max-height: 180px; overflow-y: auto; background: #181A20; border-radius: 8px; padding: 10px; margin-bottom: 18px; }
        .user-list label { display: block; margin-bottom: 6px; color: #EAECEF; font-size: 1rem; }
        .user-list label input { margin-right: 8px; }
        .select-all { margin-bottom: 12px; color: #F0B90B; font-weight: bold; cursor: pointer; }
        .sent-table { margin: 40px auto 0 auto; max-width: 900px; background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 24px; }
        .sent-table table { width: 100%; border-collapse: collapse; }
        .sent-table th, .sent-table td { padding: 10px 8px; text-align: left; }
        .sent-table th { background: #1E2026; color: #F0B90B; }
        .sent-table tr:nth-child(even) { background: #20232B; }
        .status-read { color: #0ECB81; font-weight: bold; }
        .status-unread { color: #F6465D; font-weight: bold; }
    </style>
    <script>
    function toggleSelectAll(source) {
        var checkboxes = document.querySelectorAll('.user-checkbox');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }
    </script>
</head>
<body>
    <div class="admin-bar">
        <div class="admin-info"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?></div>
        <div style="color:#B7BDC6;">Send Notification</div>
    </div>
    <div class="notify-container">
        <div class="notify-title"><i class="fa-solid fa-bell"></i> Send Notification</div>
        <?php if ($msg): ?>
            <div class="msg <?php echo ($msg !== 'Notification sent!') ? 'error' : ''; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" required placeholder="Enter notification message..."></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" onclick="toggleSelectAll(this)"> Select All Users</label>
                <div class="user-list">
                    <?php foreach ($users as $u): ?>
                        <label><input type="checkbox" class="user-checkbox" name="recipients[]" value="<?php echo $u['id']; ?>"> <?php echo htmlspecialchars($u['username'] . ' (' . $u['email'] . ')'); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="notify-btn" type="submit" name="notif_type" value="notification">Send Notification</button>
            <button class="notify-btn" type="submit" name="notif_type" value="onscreen" style="margin-top:10px;background:#0ECB81;">Send Onscreen Message</button>
        </form>
    </div>
    <div class="sent-table">
        <h2 style="color:#F0B90B;text-align:center;margin-bottom:18px;font-size:1.2rem;">Sent Notifications</h2>
        <table>
            <tr>
                <th>Message</th>
                <th>Recipient</th>
                <th>Date Sent</th>
                <th>Status</th>
            </tr>
            <?php if (empty($sent_notifications)): ?>
                <tr><td colspan="4" style="text-align:center; color:#B7BDC6;">No notifications sent yet.</td></tr>
            <?php else: ?>
                <?php foreach ($sent_notifications as $n): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($n['message']); ?></td>
                        <td><?php echo htmlspecialchars($n['username']); ?></td>
                        <td><?php echo $n['created_at']; ?></td>
                        <td class="status-<?php echo $n['is_read'] ? 'read' : 'unread'; ?>"><?php echo $n['is_read'] ? 'Read' : 'Unread'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>
