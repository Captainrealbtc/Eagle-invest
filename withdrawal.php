<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// DB connection
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed.');
}

// Get user balance and wallet address
$stmt = $conn->prepare('SELECT balance, wallet_address FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($balance, $wallet_address);
$stmt->fetch();
$stmt->close();
if (!isset($wallet_address)) $wallet_address = '';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check verification status
    $ver_status = null;
    $ver_stmt = $conn->prepare('SELECT status FROM verifications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
    $ver_stmt->bind_param('i', $user_id);
    $ver_stmt->execute();
    $ver_stmt->bind_result($ver_status);
    $ver_stmt->fetch();
    $ver_stmt->close();
    if ($ver_status !== 'approved') {
        $message = 'You need to verify your account before withdrawal.';
    } else {
        $method = $_POST['method'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            $message = 'Enter a valid withdrawal amount.';
        } elseif ($amount > $balance) {
            echo "<script>alert('Insufficient balance, please top up your account.'); window.location='deposit.php';</script>";
            exit();
        } elseif (!in_array($method, ['wallet', 'bank'])) {
            $message = 'Invalid withdrawal method.';
        } else {
            // Deduct balance and create withdrawal
            $conn->begin_transaction();
            $details = isset($_POST['details']) ? $_POST['details'] : '';
            $stmt1 = $conn->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $stmt1->bind_param('di', $amount, $user_id);
            $stmt1->execute();
            $stmt2 = $conn->prepare('INSERT INTO withdrawals (user_id, username, method, amount, details, status) VALUES (?, ?, ?, ?, ?, "pending")');
            $stmt2->bind_param('issds', $user_id, $username, $method, $amount, $details);
            $stmt2->execute();
            if ($stmt1->affected_rows > 0 && $stmt2->affected_rows > 0) {
                // Instant notification to user
                $msg = "Your withdrawal of $" . number_format($amount,2) . " via " . ucfirst($method) . " has been submitted and is pending approval.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif_stmt->bind_param('is', $user_id, $msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                // Admin notification
                $admin_msg = "User $username submitted a withdrawal of $" . number_format($amount,2) . " via " . ucfirst($method) . ".";
                $admin_stmt = $conn->prepare("INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)");
                $admin_stmt->bind_param('iss', $user_id, $username, $admin_msg);
                $admin_stmt->execute();
                $admin_stmt->close();
                $conn->commit();
                echo "<script>alert('Withdrawal submitted to admin, note that the withdrawal will arrive within 48hrs.'); window.location='dashboard.php';</script>";
                exit();
            } else {
                $conn->rollback();
                $message = 'Withdrawal failed. Please try again.';
            }
            $stmt1->close();
            $stmt2->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdraw | Eagle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #181A20; color: #EAECEF; font-family: 'Segoe UI', Arial, sans-serif; }
        .withdraw-container { max-width: 420px; box-shadow:rgb(44, 35, 3) 0px 5px 15px; margin: 60px auto; background: #23262F; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 40px 30px; }
        .withdraw-title { color: #F0B90B; font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 22px; }
        
        .form-group label { display: block; margin-bottom: 8px; color: #B7BDC6; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border-radius: 6px; border: none; background: #181A20; color: #EAECEF; font-size: 1rem; }
        .form-group input:focus, .form-group select:focus { outline: 2px solid #F0B90B; }
        .withdraw-btn { width: 100%; background: #F0B90B; color: #181A20; border: none; border-radius: 6px; padding: 14px 0; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .withdraw-btn:hover { background: #b38a00; }
         a.dashboard-link { color: #F0B90B; text-align: center; text-decoration: underline; font-size: 1rem; }
        .error-msg { color: #F6465D; text-align: center; margin-bottom: 18px; font-weight: bold; }
        .balance-info { color: #0ECB81; text-align: center; margin-bottom: 18px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="withdraw-container">
        <div class="withdraw-title"><i class="fa-solid fa-arrow-up"></i> Withdraw</div>
        <div class="balance-info">Available Balance: $<?php echo number_format($balance, 2); ?></div>
        <?php if ($message): ?>
            <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
            <?php if ($message === 'You need to verify your account before withdrawal.'): ?>
                <script>
                    setTimeout(function() {
                        window.location.href = 'verification.php';
                    }, 3000);
                </script>
            <?php endif; ?>
        <?php endif; ?>
        <form method="post" autocomplete="off" id="withdrawForm">
            <div class="form-group">
                <label for="method">Withdrawal Method</label>
                <select id="method" name="method" required onchange="showDetailsPopup()">
                    <option value="">Select Method</option>
                    <option value="wallet">Wallet</option>
                    <option value="bank">Bank Account</option>
                </select>
            </div>
            <div class="form-group">
                <label for="amount">Amount</label>
                <input type="number" id="amount" name="amount" min="1" step="0.01" required>
            </div>
            <input type="hidden" name="details" id="detailsField">
            <button class="withdraw-btn" type="submit">Submit Withdrawal</button>
            <a href="dashboard.php" class="dashboard-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </form>
        <!-- Modal Popup for Details -->
        <div id="detailsModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
            <div style="background:#23262F;padding:32px 28px;border-radius:12px;max-width:350px;width:90%;margin:auto;position:relative;">
                <div id="modalContent"></div>
                <button onclick="closeDetailsPopup()" style="margin-top:18px;background:#F6465D;color:#fff;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;">Close</button>
                <button onclick="saveDetails()" style="margin-top:18px;background:#0ECB81;color:#fff;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;float:right;">Save</button>
            </div>
        </div>
        <script>
        function showDetailsPopup() {
            var method = document.getElementById('method').value;
            var modal = document.getElementById('detailsModal');
            var content = '';
            if (method === 'wallet') {
                var savedWallet = <?php echo json_encode($wallet_address); ?>;
                content = '<label>Wallet Address</label><input type="text" id="walletAddress" style="width:100%;padding:10px;margin-bottom:10px;border-radius:6px;border:none;background:#181A20;color:#EAECEF;" value="' + (savedWallet ? savedWallet : '') + '">';
            } else if (method === 'bank') {
                content = '<label>Country</label><input type="text" id="country" style="width:100%;padding:10px;margin-bottom:10px;border-radius:6px;border:none;background:#181A20;color:#EAECEF;">' +
                          '<label>Bank Name</label><input type="text" id="bankName" style="width:100%;padding:10px;margin-bottom:10px;border-radius:6px;border:none;background:#181A20;color:#EAECEF;">' +
                          '<label>Account Number</label><input type="text" id="accountNumber" style="width:100%;padding:10px;margin-bottom:10px;border-radius:6px;border:none;background:#181A20;color:#EAECEF;">' +
                          '<label>Account Name</label><input type="text" id="accountName" style="width:100%;padding:10px;margin-bottom:10px;border-radius:6px;border:none;background:#181A20;color:#EAECEF;">' +
                          '<label>Branch Code (optional)</label><input type="text" id="branchCode" style="width:100%;padding:10px;margin-bottom:10px;border-radius:6px;border:none;background:#181A20;color:#EAECEF;">';
            } else {
                modal.style.display = 'none';
                return;
            }
            document.getElementById('modalContent').innerHTML = content;
            modal.style.display = 'flex';
        }
        function closeDetailsPopup() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        function saveDetails() {
            var method = document.getElementById('method').value;
            var details = '';
            if (method === 'wallet') {
                details = 'Wallet Address: ' + document.getElementById('walletAddress').value;
            } else if (method === 'bank') {
                details = 'Country: ' + document.getElementById('country').value + ', Bank Name: ' + document.getElementById('bankName').value + ', Account Number: ' + document.getElementById('accountNumber').value + ', Account Name: ' + document.getElementById('accountName').value;
                var branchCode = document.getElementById('branchCode').value;
                if (branchCode) details += ', Branch Code: ' + branchCode;
            }
            document.getElementById('detailsField').value = details;
            closeDetailsPopup();
        }
        // Prevent form submit if details not filled
        document.getElementById('withdrawForm').onsubmit = function() {
            var method = document.getElementById('method').value;
            var details = document.getElementById('detailsField').value;
            if ((method === 'wallet' && !details.includes('Wallet Address:')) || (method === 'bank' && (!details.includes('Country:') || !details.includes('Bank Name:') || !details.includes('Account Number:') || !details.includes('Account Name:')))) {
                showDetailsPopup();
                return false;
            }
            return true;
        };
        </script>
    </div>
</body>
</html>
