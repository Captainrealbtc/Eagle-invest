<?php
session_start();
// Admin authentication check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../login.php');
    exit();
}
// Database connection (to be moved to config in next steps)
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
// Handle user actions
$actionMsg = '';
$viewUserId = null;
$editUserId = null;
$viewUser = null;
$editUser = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $userId = intval($_POST['user_id']);
    $action = $_POST['action'];
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        $actionMsg = 'Database connection failed.';
    } else {
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $actionMsg = 'User deleted.';
            $stmt->close();
        } elseif ($action === 'resetpw') {
            $newPw = bin2hex(random_bytes(4));
            $hashed = password_hash($newPw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hashed, $userId);
            $stmt->execute();
            $actionMsg = 'Password reset. New password: ' . $newPw;
            $stmt->close();
        } elseif ($action === 'view') {
            $viewUserId = $userId;
        } elseif ($action === 'edit') {
            $editUserId = $userId;
        } elseif ($action === 'update' && isset($_POST['username'], $_POST['email'], $_POST['verified'], $_POST['balance'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $verified = intval($_POST['verified']);
            $balance = floatval($_POST['balance']);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, verified = ?, balance = ? WHERE id = ?");
            $stmt->bind_param('ssidi', $username, $email, $verified, $balance, $userId);
            $stmt->execute();
            $actionMsg = 'User updated.';
            $stmt->close();
            $editUserId = $userId; // Keep edit form open after update
        }
        $conn->close();
    }
}
// Fetch all users and their verification status
$users = [];
$conn = new mysqli($host, $user, $pass, $db);
$sql = "SELECT *, IFNULL(balance, 0.00) as balance FROM users ORDER BY created_at DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        // Query latest verification status for this user
        $ver_status = null;
        $ver_stmt = $conn->prepare('SELECT status FROM verifications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
        $ver_stmt->bind_param('i', $row['id']);
        $ver_stmt->execute();
        $ver_stmt->bind_result($ver_status);
        $ver_stmt->fetch();
        $ver_stmt->close();
        $row['verification_status'] = $ver_status;
        $users[] = $row;
        // Set $viewUser or $editUser if needed
        if ($viewUserId !== null && $row['id'] == $viewUserId) {
            $viewUser = $row;
        }
        if ($editUserId !== null && $row['id'] == $editUserId) {
            $editUser = $row;
        }
    }
}
$conn->close();
?>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        /* Inline CSS preserved for now, will be moved to external file in next steps */
        .admin-title { font-size: 1.5rem; color: #F0B90B; font-weight: bold; margin: 40px 0 20px 0; text-align: center; }
         body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #181A20; color: #EAECEF; }
        .section-placeholder { background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 40px 30px; max-width: 1000px; margin: 40px auto; text-align: center; color: #B7BDC6; font-size: 1.2rem; }
    </style>
</head>
<body>
<div class="admin-title">Users</div>
<?php if ($actionMsg): ?>
    <div style="color:#F0B90B; text-align:center; font-weight:bold; margin-bottom:18px;"> <?php echo htmlspecialchars($actionMsg); ?> </div>
<?php endif; ?>
<div class="section-placeholder">
    <table style="width:100%;border-collapse:collapse;">
        <tr style="background:#1E2026;">
            <th style="padding:10px 8px;">ID</th>
            <th style="padding:10px 8px;">Username</th>
            <th style="padding:10px 8px;">Email</th>
            <th style="padding:10px 8px;">Active</th>
            <th style="padding:10px 8px;">Verified</th>
            <th style="padding:10px 8px;">Balance</th>
            <th style="padding:10px 8px;">Registered</th>
            <th style="padding:10px 8px;">Actions</th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td style="padding:8px 6px; color:#F0B90B; font-weight:bold;">#<?php echo $user['id']; ?></td>
            <td style="padding:8px 6px;"> <?php echo htmlspecialchars($user['username']); ?> </td>
            <td style="padding:8px 6px;"> <?php echo htmlspecialchars($user['email']); ?> </td>
            <td style="padding:8px 6px; text-align:center;">
                <?php if (isset($user['active']) && $user['active']): ?>
                    <span style="display:inline-block;width:14px;height:14px;background:#0ECB81;border-radius:50%;border:2px solid #23262F;"></span>
                <?php else: ?>
                    <span style="display:inline-block;width:14px;height:14px;background:#181A20;border-radius:50%;border:2px solid #23262F;"></span>
                <?php endif; ?>
            </td>
            <td style="padding:8px 6px;">
                <?php if (isset($user['verification_status']) && $user['verification_status'] === 'approved'): ?>
                    <span style="color:#0ECB81;font-weight:bold;">Yes</span>
                <?php else: ?>
                    <span style="color:#F6465D;font-weight:bold;">No</span>
                <?php endif; ?>
            </td>
            <td style="padding:8px 6px; color:#F0B90B; font-weight:bold;">
                <?php echo number_format($user['balance'], 2); ?>
            </td>
            <td style="padding:8px 6px; color:#B7BDC6;"> <?php echo $user['created_at']; ?> </td>
            <td style="padding:8px 6px;">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <button name="action" value="view" style="background:#F0B90B;color:#181A20;border:none;border-radius:5px;padding:5px 12px;cursor:pointer;">View</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <button name="action" value="edit" style="background:#23262F;color:#fff;border:none;border-radius:5px;padding:5px 12px;cursor:pointer;">Edit</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <button name="action" value="delete" style="background:#F6465D;color:#fff;border:none;border-radius:5px;padding:5px 12px;cursor:pointer;">Delete</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <button name="action" value="resetpw" style="background:#0ECB81;color:#fff;border:none;border-radius:5px;padding:5px 12px;cursor:pointer;">Reset PW</button>
                </form>
            </td>
        </tr>
        <?php if ($viewUser && $viewUser['id'] == $user['id']): ?>
        <tr><td colspan="7" style="background:#23262F; color:#F0B90B; text-align:left; padding:18px;">
            <b>User Details:</b><br>
            Username: <?php echo htmlspecialchars($viewUser['username']); ?><br>
            Email: <?php echo htmlspecialchars($viewUser['email']); ?><br>
            Password: <?php echo htmlspecialchars($viewUser['password']); ?><br>
            Balance: <?php echo number_format($viewUser['balance'], 2); ?><br>
            Verified: <?php echo $viewUser['verified'] ? 'Yes' : 'No'; ?><br>
            Blocked: <?php echo $viewUser['blocked'] ? 'Yes' : 'No'; ?><br>
            Registered: <?php echo $viewUser['created_at']; ?><br>
           
        </td></tr>
        <?php endif; ?>
        <?php if ($editUser && $editUser['id'] == $user['id']): ?>
        <tr><td colspan="7" style="background:#23262F; color:#F0B90B; text-align:left; padding:18px;">
            <form method="post">
                <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                <input type="hidden" name="action" value="update">
                <label>Username: <input type="text" name="username" value="<?php echo htmlspecialchars($editUser['username']); ?>" required></label><br><br>
                <label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>" required></label><br><br>
                <label>Verified: 
                    <select name="verified" required>
                        <option value="1" <?php if ($editUser['verified'] == 1) echo 'selected'; ?>>Yes</option>
                        <option value="0" <?php if ($editUser['verified'] == 0) echo 'selected'; ?>>No</option>
                    </select>
                </label><br><br>
                <label>Balance: <input type="number" step="0.01" name="balance" value="<?php echo number_format($editUser['balance'], 2, '.', ''); ?>" required></label><br><br>
                <button type="submit" style="background:#F0B90B;color:#181A20;border:none;border-radius:5px;padding:5px 18px;cursor:pointer;">Update</button>
            </form>
            <div style="width: 250px; height:335px; background-color: #FAFAFA; overflow:hidden; box-sizing: border-box; border: 1px solid #56667F; border-radius: 4px; text-align: right; line-height:14px; block-size:335px; font-size: 12px; font-feature-settings: normal; text-size-adjust: 100%; box-shadow: inset 0 -20px 0 0 #56667F;margin: 0;width: 250px;padding:1px;padding: 0px; margin: 0px;"><div style="height:315px; padding:0px; margin:0px; width: 100%;"><iframe src="https://widget.coinlib.io/widget?type=converter&theme=light" width="250" height="310px" scrolling="auto" marginwidth="0" marginheight="0" frameborder="0" border="0" style="border:0;margin:0;padding:0;"></iframe></div><div style="color: #FFFFFF; line-height: 14px; font-weight: 400; font-size: 11px; box-sizing: border-box; padding: 2px 6px; width: 100%; font-family: Verdana, Tahoma, Arial, sans-serif;"><a href="https://coinlib.io" target="_blank" style="font-weight: 500; color: #FFFFFF; text-decoration:none; font-size:11px"></div></div>
        </td></tr>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr><td colspan="7" style="text-align:center;color:#B7BDC6;">No users found.</td></tr>
        <?php endif; ?>
    </table>
</div>

</body>
</html>
