<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// DB connection
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed.');
}

// Fetch deposits
$deposits = [];
$stmt = $conn->prepare('SELECT amount, status, created_at FROM deposits WHERE user_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $row['type'] = 'Deposit';
    $deposits[] = $row;
}
$stmt->close();

// Fetch withdrawals
$withdrawals = [];
$stmt = $conn->prepare('SELECT amount, status, method, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $row['type'] = 'Withdrawal (' . ucfirst($row['method']) . ')';
    $withdrawals[] = $row;
}
$stmt->close();
$conn->close();

// Merge and sort all transactions by date
$transactions = array_merge($deposits, $withdrawals);
usort($transactions, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recent Transactions | Eagle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; }
        .tx-container { max-width: 700px; margin: 60px auto; background: #23262F; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 40px 30px; }
        .tx-title { color: #F0B90B; font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; }
        th { background: #1E2026; color: #F0B90B; }
        tr:nth-child(even) { background: #20232B; }
        .status-approved { color: #0ECB81; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .status-rejected { color: #F6465D; font-weight: bold; }
    </style>
</head>
<body>
    <div class="tx-container">
        <div class="tx-title"><i class="fa-solid fa-list"></i> Recent Transactions</div>
        <table>
            <tr>
                <th>Type</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="4" style="text-align:center; color:#B7BDC6;">No transactions found.</td></tr>
            <?php else: ?>
                <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tx['type']); ?></td>
                        <td>$<?php echo number_format($tx['amount'], 2); ?></td>
                        <td class="status-<?php echo strtolower($tx['status']); ?>">
                            <?php echo ucfirst($tx['status']); ?>
                        </td>
                        <td><?php echo $tx['created_at']; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>
