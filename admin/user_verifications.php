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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_id'], $_POST['action'])) {
    $verification_id = intval($_POST['verification_id']);
    $action = $_POST['action'];
    if ($action === 'approve') {
        // Approve verification
        $stmt = $conn->prepare('UPDATE verifications SET status = "approved" WHERE id = ?');
        $stmt->bind_param('i', $verification_id);
        $stmt->execute();
        $stmt->close();
        // Get user_id for notification
        $stmt = $conn->prepare('SELECT user_id FROM verifications WHERE id = ?');
        $stmt->bind_param('i', $verification_id);
        $stmt->execute();
        $stmt->bind_result($user_id);
        $stmt->fetch();
        $stmt->close();
        // Send notification to user
        if (!empty($user_id)) {
            $msg = "Your account verification has been approved. You can now access all features.";
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->bind_param('is', $user_id, $msg);
            $stmt->execute();
            $stmt->close();
            // Set verified field to 1 in users table
            $stmt = $conn->prepare('UPDATE users SET verified = 1 WHERE id = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
        }
        $actionMsg = 'Verification approved.';
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare('UPDATE verifications SET status = "rejected" WHERE id = ?');
        $stmt->bind_param('i', $verification_id);
        $stmt->execute();
        $stmt->close();
        $actionMsg = 'Verification rejected.';
    }
}

// Fetch all verifications
$verifications = [];
$sql = 'SELECT v.*, u.username, u.email FROM verifications v LEFT JOIN users u ON v.user_id = u.id ORDER BY v.submitted_at DESC';
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $verifications[] = $row;
    }
}
$conn->close();
?>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users Verifications | Admin</title>
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
        .status-approved { color: #0ECB81; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .status-rejected { color: #F6465D; font-weight: bold; }
        .action-btn { border: none; border-radius: 5px; padding: 6px 16px; font-size: 1rem; cursor: pointer; margin-right: 8px; }
        .approve-btn { background: #0ECB81; color: #fff; }
        .reject-btn { background: #F6465D; color: #fff; }
        .approve-btn:hover { background: #099e5c; }
        .reject-btn:hover { background: #a8322a; }
        .doc-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #F0B90B;
            background: #fff;
            display: block;
            margin: 0 auto;
        }
        .doc-link { color: #F0B90B; text-decoration: underline; font-size: 0.98em; }
    </style>
</head>
<body>
    <div class="admin-bar">
        <div class="admin-info"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?></div>
        <div style="color:#B7BDC6;">User Verifications Control</div>
    </div>
    <div class="admin-title">All User Verifications</div>
    <?php if ($actionMsg): ?>
        <div style="color:#F0B90B; text-align:center; font-weight:bold; margin-bottom:18px;"> <?php echo htmlspecialchars($actionMsg); ?> </div>
    <?php endif; ?>
    <div class="section-placeholder">
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Document Type</th>
                <th>Document Image</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
            <?php if (empty($verifications)): ?>
                <tr><td colspan="8" style="text-align:center; color:#B7BDC6;">No verifications found.</td></tr>
            <?php else: ?>
                <?php foreach ($verifications as $v): ?>
                    <tr>
                        <td>#<?php echo $v['id']; ?></td>
                        <td><?php echo $v['username'] !== null ? htmlspecialchars($v['username']) : '<span style="color:#B7BDC6;">-</span>'; ?></td>
                        <td><?php echo htmlspecialchars($v['email']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($v['document_type'])); ?></td>
                        <td style="text-align:center;">
                            <?php if ($v['document_image']): ?>
                                <a href="../uploads/verification_docs/<?php echo htmlspecialchars($v['document_image']); ?>" target="_blank" class="doc-link">
                                    <img src="../uploads/verification_docs/<?php echo htmlspecialchars($v['document_image']); ?>" class="doc-img" alt="Document Image">
                                </a>
                                <br>
                                <a href="../uploads/verification_docs/<?php echo htmlspecialchars($v['document_image']); ?>" download class="doc-link" style="display:inline-block;margin-top:8px;">
                                    <i class="fa fa-download"></i> Download
                                </a>
                            <?php else: ?>
                                <span style="color:#F6465D;">No image</span>
                            <?php endif; ?>
                        </td>
                        <td class="status-<?php echo strtolower($v['status']); ?>"><?php echo ucfirst($v['status']); ?></td>
                        <td><?php echo $v['submitted_at']; ?></td>
                        <td>
                            <?php if ($v['status'] === 'pending'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="verification_id" value="<?php echo $v['id']; ?>">
                                    <button name="action" value="approve" class="action-btn approve-btn">Approve</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="verification_id" value="<?php echo $v['id']; ?>">
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
