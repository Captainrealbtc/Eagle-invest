<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: registration.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed.');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject'], $_POST['message'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    if ($subject === '' || $message === '') {
        $msg = 'Subject and message are required.';
    } else {
        $stmt = $conn->prepare('INSERT INTO complaints (user_id, subject, message) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $user_id, $subject, $message);
        if ($stmt->execute()) {
            $msg = 'Complaint submitted successfully!';
            // Admin notification
            $admin_msg = "User $username submitted a complaint: $subject.";
            $admin_stmt = $conn->prepare('INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)');
            $admin_stmt->bind_param('iss', $user_id, $username, $admin_msg);
            $admin_stmt->execute();
            $admin_stmt->close();
        } else {
            $msg = 'Failed to submit complaint.';
        }
        $stmt->close();
    }
}
// Fetch user's previous complaints
$complaints = [];
$stmt = $conn->prepare('SELECT subject, message, status, created_at FROM complaints WHERE user_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complaints | Eagle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; }
        .complaint-container { max-width: 520px; margin: 40px auto; background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 36px 36px 28px 36px; }
        .complaint-title { color: #F0B90B; font-size: 1.5rem; font-weight: bold; margin-bottom: 18px; text-align: center; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; margin-bottom: 8px; color: #B7BDC6; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border-radius: 6px; border: none; background: #181A20; color: #EAECEF; font-size: 1rem; }
        .form-group textarea { min-height: 80px; }
        .complaint-btn { width: 100%; background: #F0B90B; color: #181A20; border: none; border-radius: 6px; padding: 14px 0; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .complaint-btn:hover { background: #b38a00; }
        .msg { color: #0ECB81; text-align: center; margin-bottom: 18px; font-weight: bold; }
        .error { color: #F6465D; }
        .complaint-history { margin-top: 40px; background: #181A20; border-radius: 10px; padding: 18px 18px 10px 18px; }
        .history-title { color: #F0B90B; font-weight: bold; margin-bottom: 10px; }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 8px 10px; text-align: left; }
        .history-table th { color: #B7BDC6; font-weight: 500; border-bottom: 1px solid #23262F; }
        .history-table td { color: #EAECEF; border-bottom: 1px solid #23262F; }
        .history-table tr:last-child td { border-bottom: none; }
        .status-pending { color: orange; font-weight: bold; }
        .status-resolved { color: #0ECB81; font-weight: bold; }
        .status-rejected { color: #F6465D; font-weight: bold; }
    </style>
</head>
<body>
    <div class="complaint-container">
        <div class="complaint-title"><i class="fa-solid fa-comment-dots"></i> Submit a Complaint</div>
        <?php if ($msg): ?>
            <div class="msg <?php echo ($msg !== 'Complaint submitted successfully!') ? 'error' : ''; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" required></textarea>
            </div>
            <button class="complaint-btn" type="submit">Send Complaint</button>
        </form>
    </div>
    <?php if (count($complaints) > 0): ?>
    <div class="complaint-history">
        <div class="history-title">Your Previous Complaints</div>
        <table class="history-table">
            <tr><th>Date</th><th>Subject</th><th>Status</th></tr>
            <?php foreach ($complaints as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                <td class="status-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</body>
</html>
