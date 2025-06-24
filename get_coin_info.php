<?php
header('Content-Type: application/json');

$host = 'localhost';
$db   = 'user_auth_db';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed.']);
    exit();
}

$coin = $_POST['coin'] ?? '';
if ($coin === '') {
    echo json_encode(['error' => 'No coin specified.']);
    $conn->close();
    exit();
}

$stmt = $conn->prepare('SELECT coin_symbol, address, barcode_image FROM deposit_addresses WHERE coin_symbol = ? LIMIT 1');
$stmt->bind_param('s', $coin);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'coin_symbol' => $row['coin_symbol'],
        'address' => $row['address'],
        'barcode_image' => $row['barcode_image']
    ]);
} else {
    echo json_encode(['error' => 'Coin not found.']);
}
$stmt->close();
$conn->close();
