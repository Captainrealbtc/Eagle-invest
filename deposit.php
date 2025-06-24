<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: registration.php');
    exit();
}

// Database connection
$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch deposit addresses
$addresses = [];
$sql = "SELECT * FROM deposit_addresses ORDER BY coin_symbol";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $addresses[$row['coin_symbol']] = $row;
}

// Fetch active P2P bank details
$p2p_bank = null;
$p2p_sql = "SELECT * FROM p2p_bank_details WHERE is_active = 1 LIMIT 1";
$p2p_res = $conn->query($p2p_sql);
if ($p2p_res && $p2p_res->num_rows > 0) {
    $p2p_bank = $p2p_res->fetch_assoc();
}

// Determine selected coin
$selected_coin = $_POST['coin'] ?? array_key_first($addresses);
$selected = $addresses[$selected_coin] ?? reset($addresses);
$address = $selected['address'] ?? '';
$barcode_image = $selected['barcode_image'] ?? '';
$coin_name = $selected['coin_name'] ?? $selected_coin;

// Handle deposit submission
$deposit_success = false;
$deposit_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount']) && isset($_POST['coin'])) {
    $amount = trim($_POST['amount']);
    if (!is_numeric($amount) || $amount <= 0) {
        $deposit_error = 'Please enter a valid amount.';
    } else {
        $stmt = $conn->prepare("INSERT INTO deposits (user_id, username, coin_symbol, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $user_id, $username, $selected_coin, $amount);
        if ($stmt->execute()) {
            $deposit_success = true;
            // Instant notification to user
            $msg = "Your deposit of " . number_format($amount,8) . " $selected_coin has been submitted and is pending approval.";
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notif_stmt->bind_param('is', $user_id, $msg);
            $notif_stmt->execute();
            $notif_stmt->close();
            // Admin notification
            $admin_msg = "User $username submitted a deposit of " . number_format($amount,8) . " $selected_coin.";
            $admin_stmt = $conn->prepare("INSERT INTO admin_notification (user_id, username, message) VALUES (?, ?, ?)");
            $admin_stmt->bind_param('iss', $user_id, $username, $admin_msg);
            $admin_stmt->execute();
            $admin_stmt->close();
        } else {
            $deposit_error = 'Failed to submit deposit.';
        }
        $stmt->close();
    }
}

// Fetch deposit history for this user
$history = [];
$hres = $conn->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC");
$hres->bind_param('i', $user_id);
$hres->execute();
$result = $hres->get_result();
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}
$hres->close();

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deposit | Binance Style</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #181A20; color: #EAECEF; }
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 220px; background: #23262F; padding-top: 30px; display: flex; flex-direction: column; z-index: 100; }
        .sidebar .logo { color: #F0B90B; font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 40px; letter-spacing: 2px; }
        .sidebar nav { flex: 1; }
        .sidebar nav a { display: flex; align-items: center; color: #EAECEF; text-decoration: none; padding: 16px 30px; font-size: 1.08rem; transition: background 0.2s, color 0.2s; }
        .sidebar nav a.active, .sidebar nav a:hover { background: #1E2026; color: #F0B90B; }
        .sidebar nav a i { margin-right: 16px; font-size: 1.2rem; }
        .sidebar .logout { margin: 30px 30px 0 30px; background: #F6465D; color: #fff; border: none; border-radius: 6px; padding: 12px 0; font-size: 1rem; cursor: pointer; text-align: center; text-decoration: none; display: block; transition: background 0.2s; }
        .sidebar .logout:hover { background: #a8322a; }
        .main-content { margin-left: 220px; min-height: 100vh; background: #181A20; }
        .top-bar { background: #23262F; padding: 0 40px; height: 70px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #2B3139; position: sticky; top: 0; z-index: 10; }
        .greeting { font-size: 1.2rem; font-weight: 500; color: #fff; }
        .deposit-container { max-width: 520px; margin: 40px auto 0 auto; background: #23262F; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); padding: 36px 36px 28px 36px; }
        .deposit-title { color: #F0B90B; font-size: 1.5rem; font-weight: bold; margin-bottom: 18px; text-align: center; }
        .deposit-form { display: flex; flex-direction: column; gap: 18px; }
        .deposit-form label { color: #B7BDC6; font-weight: 500; margin-bottom: 6px; }
        .deposit-form select, .deposit-form input[type=number] { background: #181A20; color: #fff; border: 1px solid #2B3139; border-radius: 8px; padding: 10px; font-size: 1.08rem; }
        .deposit-address-box { background: #181A20; border-radius: 8px; padding: 18px; margin-top: 10px; display: flex; align-items: center; justify-content: space-between; }
        .deposit-address { font-size: 1.08rem; color: #F0B90B; letter-spacing: 1px; word-break: break-all; }
        .copy-btn { background: #F0B90B; color: #181A20; border: none; border-radius: 6px; padding: 6px 14px; font-weight: bold; cursor: pointer; margin-left: 18px; transition: background 0.2s; }
        .copy-btn:hover { background: #FFD700; }
        .qr-section { display: flex; flex-direction: column; align-items: center; margin-top: 24px; }
        .qr-section img { border-radius: 10px; background: #fff; padding: 8px; max-width: 180px; }
        .qr-label { color: #B7BDC6; margin-top: 10px; font-size: 0.98rem; }
        .deposit-note { color: #F6465D; font-size: 0.98rem; margin-top: 18px; text-align: center; }
        .deposit-success { color: #0ECB81; text-align: center; margin-bottom: 10px; }
        .deposit-error { color: #F6465D; text-align: center; margin-bottom: 10px; }
        .deposit-history { margin-top: 40px; background: #181A20; border-radius: 10px; padding: 18px 18px 10px 18px; }
        .history-title { color: #F0B90B; font-weight: bold; margin-bottom: 10px; }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 8px 10px; text-align: left; }
        .history-table th { color: #B7BDC6; font-weight: 500; border-bottom: 1px solid #23262F; }
        .history-table td { color: #EAECEF; border-bottom: 1px solid #23262F; }
        .history-table tr:last-child td { border-bottom: none; }
        @media (max-width: 900px) { .sidebar { width: 60px; } .sidebar .logo { font-size: 1.2rem; } .sidebar nav a span { display: none; } .main-content { margin-left: 60px; } .top-bar { padding: 0 10px; } }
        @media (max-width: 600px) { .sidebar { display: none; } .main-content { margin-left: 0; } .top-bar { padding: 0 5px; } .deposit-container { padding: 16px 4px; } }
    </style>
    <script>
        function copyAddress() {
            var copyText = document.getElementById('depositAddress');
            navigator.clipboard.writeText(copyText.textContent);
            var btn = document.getElementById('copyBtn');
            btn.textContent = 'Copied!';
            setTimeout(function(){ btn.textContent = 'Copy'; }, 1200);
        }
    </script>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><i class="fa-solid fa-coins"></i> Binance</div>
        <nav>
            <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a>
            <a href="deposit.php" class="active"><i class="fa-solid fa-arrow-down"></i> <span>Deposit</span></a>
            <a href="withdrawal.php"><i class="fa-solid fa-arrow-up"></i> <span>Withdraw</span></a>
            <a href="recent_transactions.php"><i class="fa-solid fa-chart-line"></i> <span>Trades</span></a>
            <a href="wallet.php"><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a>
        </nav>
        <a class="logout" href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="top-bar">
            <div class="greeting">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
        </div>
        <div class="deposit-container">
            <div class="deposit-title">Deposit</div>
            <div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 24px;">
                <button type="button" class="copy-btn" id="tab-crypto" onclick="showTab('crypto')">Crypto</button>
                <button type="button" class="copy-btn" id="tab-p2p" onclick="showTab('p2p')">P2P</button>
                <button type="button" class="copy-btn" id="tab-giftcard" onclick="showTab('giftcard')">Gift Card</button>
            </div>
            <div id="crypto-section" style="display:block;">
                <?php if ($deposit_success): ?>
                    <meta http-equiv="refresh" content="4;url=dashboard.php">
                    <div class="deposit-success">Deposit request submitted! Awaiting admin approval.<br>Redirecting...</div>
                <?php elseif ($deposit_error): ?>
                    <div class="deposit-error"><?php echo htmlspecialchars($deposit_error); ?></div>
                <?php endif; ?>
                <form class="deposit-form" method="post" id="depositForm">
                    <label for="coin">Select Coin</label>
                    <select name="coin" id="coin" onchange="updateCoinInfo()">
                        <?php foreach ($addresses as $coin => $row): ?>
                            <option value="<?php echo $coin; ?>" <?php if ($selected_coin === $coin) echo 'selected'; ?>><?php echo htmlspecialchars($row['coin_name']); ?> (<?php echo $coin; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <label for="amount">Amount(COIN)</label>
                    <input type="number" name="amount" id="amount" min="0.00000001" step="0.00000001" placeholder="Enter amount" required>
                    <button type="submit" class="copy-btn" style="width:100%;margin:0;">Submit Deposit</button>
                </form>
                <div class="deposit-address-box">
                    <span class="deposit-address" id="depositAddress"><?php echo htmlspecialchars($address); ?></span>
                    <button class="copy-btn" id="copyBtn" onclick="copyAddress()" type="button">Copy</button>
                </div>
                <div class="qr-section">
                    <img id="qrImage" src="<?php echo $barcode_image ? htmlspecialchars($barcode_image) : 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($address); ?>" alt="QR Code">
                    <div class="qr-label" id="qrLabel">Scan to deposit <?php echo htmlspecialchars($selected_coin); ?></div>
                </div>
                <div class="deposit-note" id="depositNote">Send only <b><?php echo htmlspecialchars($selected_coin); ?></b> to this address. Sending any other coin may result in permanent loss.</div>
                <script>
                function updateCoinInfo() {
                    var coin = document.getElementById('coin').value;
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'get_coin_info.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                document.getElementById('depositAddress').textContent = data.address;
                                document.getElementById('qrImage').src = data.barcode_image ? data.barcode_image : 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(data.address);
                                document.getElementById('qrLabel').textContent = 'Scan to deposit ' + data.coin_symbol;
                                document.getElementById('depositNote').innerHTML = 'Send only <b>' + data.coin_symbol + '</b> to this address. Sending any other coin may result in permanent loss.';
                            } catch (e) {
                                alert('Failed to update coin info.');
                            }
                        }
                    };
                    xhr.send('coin=' + encodeURIComponent(coin));
                }
                </script>
            </div>
            <div id="p2p-section" style="display:none;">
                <div class="deposit-title" style="margin-bottom:10px;">P2P Bank Transfer</div>
                <div style="background:#181A20;padding:18px;border-radius:8px;margin-bottom:16px;">
                    <?php if ($p2p_bank): ?>
                        <div><b>Bank Name:</b> <?php echo htmlspecialchars($p2p_bank['bank_name']); ?></div>
                        <div><b>Account Number:</b> <?php echo htmlspecialchars($p2p_bank['account_number']); ?></div>
                        <div><b>Account Name:</b> <?php echo htmlspecialchars($p2p_bank['account_name']); ?></div>
                        <?php if (!empty($p2p_bank['branch_code'])): ?>
                        <div><b>Branch Code:</b> <?php echo htmlspecialchars($p2p_bank['branch_code']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($p2p_bank['bank_address'])): ?>
                        <div><b>Bank Address:</b> <?php echo htmlspecialchars($p2p_bank['bank_address']); ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="color:#F6465D;">No P2P bank details available. Please contact support.</div>
                    <?php endif; ?>
                </div>
                <form class="deposit-form" method="post" enctype="multipart/form-data" action="p2p_deposit_handler.php">
                    <label for="p2p_amount">Amount(USD)</label>
                    <input type="number" name="p2p_amount" id="p2p_amount" min="1" step="0.01" required>
                    <label for="payment_proof">Upload Payment Proof (image)</label>
                    <input type="file" name="payment_proof" id="payment_proof" accept="image/*" required>
                    <button type="submit" class="copy-btn" style="width:100%;margin:0;">Submit P2P Deposit</button>
                </form>
                <div style="color:#F0B90B;margin-bottom:10px;">After transfer, submit your payment proof for faster processing.</div>
            </div>
            <div id="giftcard-section" style="display:none;">
                <div class="deposit-title" style="margin-bottom:10px;">Gift Card Deposit</div>
                <form class="deposit-form" method="post" enctype="multipart/form-data" action="giftcard_deposit_handler.php">
                    <label for="card_name">Card Name</label>
                    <input type="text" name="card_name" id="card_name" required>
                    <label for="card_amount">Amount</label>
                    <input type="number" name="card_amount" id="card_amount" min="1" step="1" required>
                    <label for="card_code">Card Code</label>
                    <input type="text" name="card_code" id="card_code" required>
                    <label for="card_category">Category</label>
                    <input type="text" name="card_category" id="card_category" required>
                    <label for="card_image">Upload Card Image</label>
                    <input type="file" name="card_image" id="card_image" accept="image/*" required>
                    <button type="submit" class="copy-btn" style="width:100%;margin:0;">Send Gift Card</button>
                </form>
            </div>
        </div>
        <script>
            function showTab(tab) {
                document.getElementById('crypto-section').style.display = (tab === 'crypto') ? 'block' : 'none';
                document.getElementById('p2p-section').style.display = (tab === 'p2p') ? 'block' : 'none';
                document.getElementById('giftcard-section').style.display = (tab === 'giftcard') ? 'block' : 'none';
                document.getElementById('tab-crypto').style.background = (tab === 'crypto') ? '#FFD700' : '#F0B90B';
                document.getElementById('tab-p2p').style.background = (tab === 'p2p') ? '#FFD700' : '#F0B90B';
                document.getElementById('tab-giftcard').style.background = (tab === 'giftcard') ? '#FFD700' : '#F0B90B';
            }
        </script>
        <?php if (count($history) > 0): ?>
        <div class="deposit-history">
            <div class="history-title">Deposit History</div>
            <table class="history-table">
                <tr><th>Date</th><th>Coin</th><th>Amount</th><th>Status</th></tr>
                <?php foreach ($history as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($row['coin_symbol']); ?></td>
                    <td><?php echo htmlspecialchars($row['amount']); ?></td>
                    <td style="color:
                        <?php echo $row['status'] === 'approved' ? '#0ECB81' : ($row['status'] === 'pending' ? '#F0B90B' : '#F6465D'); ?>;">
                        <?php echo ucfirst($row['status']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
