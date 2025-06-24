<?php
session_start();
// Simple admin check (replace with your actual admin auth logic)
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle P2P bank detail actions
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add_p2p') {
        $stmt = $conn->prepare("INSERT INTO p2p_bank_details (bank_name, account_number, account_name, branch_code, bank_address, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssi', $_POST['bank_name'], $_POST['account_number'], $_POST['account_name'], $_POST['branch_code'], $_POST['bank_address'], $_POST['is_active']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['action'] === 'edit_p2p' && isset($_POST['id'])) {
        $stmt = $conn->prepare("UPDATE p2p_bank_details SET bank_name=?, account_number=?, account_name=?, branch_code=?, bank_address=?, is_active=? WHERE id=?");
        $stmt->bind_param('sssssii', $_POST['bank_name'], $_POST['account_number'], $_POST['account_name'], $_POST['branch_code'], $_POST['bank_address'], $_POST['is_active'], $_POST['id']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['action'] === 'delete_p2p' && isset($_POST['id'])) {
        $stmt = $conn->prepare("DELETE FROM p2p_bank_details WHERE id=?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['action'] === 'activate_p2p' && isset($_POST['id'])) {
        // Deactivate all, then activate selected
        $conn->query("UPDATE p2p_bank_details SET is_active=0");
        $stmt = $conn->prepare("UPDATE p2p_bank_details SET is_active=1 WHERE id=?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle gift card deposit actions
if (isset($_POST['giftcard_action']) && isset($_POST['giftcard_id'])) {
    if ($_POST['giftcard_action'] === 'approve') {
        $stmt = $conn->prepare("UPDATE giftcard_deposits SET status='approved' WHERE id=?");
        $stmt->bind_param('i', $_POST['giftcard_id']);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['giftcard_action'] === 'reject') {
        $stmt = $conn->prepare("UPDATE giftcard_deposits SET status='rejected' WHERE id=?");
        $stmt->bind_param('i', $_POST['giftcard_id']);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch all P2P bank details
$p2p_banks = [];
$res = $conn->query("SELECT * FROM p2p_bank_details ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    $p2p_banks[] = $row;
}

// Fetch all gift card deposits
$giftcards = [];
$res = $conn->query("SELECT g.*, u.username FROM giftcard_deposits g JOIN users u ON g.user_id = u.id ORDER BY g.created_at DESC");
while ($row = $res->fetch_assoc()) {
    $giftcards[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - P2P & Gift Card Control</title>
    <style>
        body { font-family: Arial, sans-serif; background: #181A20; color: #EAECEF; }
        h2 { color: #F0B90B; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { padding: 8px 10px; border: 1px solid #23262F; }
        th { background: #23262F; color: #F0B90B; }
        tr:nth-child(even) { background: #23262F; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-approve { background: #0ECB81; color: #fff; }
        .btn-reject { background: #F6465D; color: #fff; }
        .btn-edit { background: #F0B90B; color: #181A20; }
        .btn-delete { background: #F6465D; color: #fff; }
        .btn-activate { background: #0ECB81; color: #fff; }
        .active-row { background: #0ECB81 !important; color: #181A20; }
        .img-thumb { max-width: 120px; max-height: 80px; border-radius: 6px; }
        .form-section { background: #23262F; padding: 18px; border-radius: 10px; margin-bottom: 30px; }
        label { display: block; margin-top: 10px; color: #F0B90B; }
        input, textarea { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #2B3139; background: #181A20; color: #fff; }
    </style>
</head>
<body>
    <h2>P2P Bank Details</h2>
    <div class="form-section">
        <form method="post">
            <input type="hidden" name="action" value="add_p2p">
            <label>Bank Name</label><input type="text" name="bank_name" required>
            <label>Account Number</label><input type="text" name="account_number" required>
            <label>Account Name</label><input type="text" name="account_name" required>
            <label>Branch Code</label><input type="text" name="branch_code">
            <label>Bank Address</label><input type="text" name="bank_address">
            <label>Active</label><select name="is_active"><option value="1">Yes</option><option value="0">No</option></select>
            <button class="btn btn-approve" type="submit">Add P2P Bank</button>
        </form>
    </div>
    <table>
        <tr><th>ID</th><th>Bank Name</th><th>Account Number</th><th>Account Name</th><th>Branch Code</th><th>Bank Address</th><th>Active</th><th>Actions</th></tr>
        <?php foreach ($p2p_banks as $bank): ?>
        <tr<?php if ($bank['is_active']) echo ' class="active-row"'; ?>>
            <td><?php echo $bank['id']; ?></td>
            <td><?php echo htmlspecialchars($bank['bank_name']); ?></td>
            <td><?php echo htmlspecialchars($bank['account_number']); ?></td>
            <td><?php echo htmlspecialchars($bank['account_name']); ?></td>
            <td><?php echo htmlspecialchars($bank['branch_code']); ?></td>
            <td><?php echo htmlspecialchars($bank['bank_address']); ?></td>
            <td><?php echo $bank['is_active'] ? 'Yes' : 'No'; ?></td>
            <td>
                <form method="post" style="display:inline-block;">
                    <input type="hidden" name="action" value="activate_p2p">
                    <input type="hidden" name="id" value="<?php echo $bank['id']; ?>">
                    <button class="btn btn-activate" type="submit">Activate</button>
                </form>
                <form method="post" style="display:inline-block;">
                    <input type="hidden" name="action" value="delete_p2p">
                    <input type="hidden" name="id" value="<?php echo $bank['id']; ?>">
                    <button class="btn btn-delete" type="submit" onclick="return confirm('Delete this bank detail?');">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Gift Card Deposits</h2>
    <table>
        <tr><th>ID</th><th>User</th><th>Card Name</th><th>Amount</th><th>Code</th><th>Category</th><th>Image</th><th>Status</th><th>Created</th><th>Actions</th></tr>
        <?php foreach ($giftcards as $g): ?>
        <tr>
            <td><?php echo $g['id']; ?></td>
            <td><?php echo htmlspecialchars($g['username']); ?></td>
            <td><?php echo htmlspecialchars($g['card_name']); ?></td>
            <td><?php echo htmlspecialchars($g['card_amount']); ?></td>
            <td><?php echo htmlspecialchars($g['card_code']); ?></td>
            <td><?php echo htmlspecialchars($g['card_category']); ?></td>
            <td><?php if ($g['card_image']): ?><a href="/<?php echo $g['card_image']; ?>" target="_blank"><img src="/<?php echo $g['card_image']; ?>" class="img-thumb"></a><?php endif; ?></td>
            <td><?php echo ucfirst($g['status']); ?></td>
            <td><?php echo $g['created_at']; ?></td>
            <td>
                <?php if ($g['status'] === 'pending'): ?>
                <form method="post" style="display:inline-block;">
                    <input type="hidden" name="giftcard_action" value="approve">
                    <input type="hidden" name="giftcard_id" value="<?php echo $g['id']; ?>">
                    <button class="btn btn-approve" type="submit">Approve</button>
                </form>
                <form method="post" style="display:inline-block;">
                    <input type="hidden" name="giftcard_action" value="reject">
                    <input type="hidden" name="giftcard_id" value="<?php echo $g['id']; ?>">
                    <button class="btn btn-reject" type="submit">Reject</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
