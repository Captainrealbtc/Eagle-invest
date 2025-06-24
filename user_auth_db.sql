
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    referrer_id INT DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    wallet_address VARCHAR(255) DEFAULT NULL,
    active TINYINT(1) DEFAULT 0,
    verified TINYINT(1) DEFAULT 0,
    blocked TINYINT(1) DEFAULT 0,
    balance DECIMAL(18,2) DEFAULT 0.00,
    bonus_awarded TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );


CREATE TABLE IF NOT EXISTS verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type ENUM('tax id', 'drivers license', 'government issued card', 'permit card') NOT NULL,
    document_image VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS deposit_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coin_symbol VARCHAR(10) NOT NULL,
    coin_name VARCHAR(32) NOT NULL,
    address VARCHAR(128) NOT NULL,
    barcode_image VARCHAR(255) DEFAULT NULL
);

INSERT INTO deposit_addresses (coin_symbol, coin_name, address, barcode_image) VALUES
('BTC', 'Bitcoin', '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 'barcodes/btc.png'),
('ETH', 'Ethereum', '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'barcodes/eth.png'),
('USDT', 'Tether', 'TQ7zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 'barcodes/usdt.png'),
('BNB', 'Binance Coin', 'bnb1grpf0955h0ykzq3ar5nmum7y6gdfl6lxfn46h2', 'barcodes/bnb.png'),
('SOL', 'Solana', '4e3Q7zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 'barcodes/sol.png'),
('ADA', 'Cardano', 'DdzFFzCqrht2rjQ2Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1', 'barcodes/ada.png'),
('DOGE', 'Dogecoin', 'D7Y55Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1', 'barcodes/doge.png'),
('XRP', 'Ripple', 'rDsbeomae4FXwgQTJp9Rs64Qg9vDiTCdBv', 'barcodes/xrp.png'),
('TRX', 'Tron', 'TQ7zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 'barcodes/trx.png'),
('LTC', 'Litecoin', 'LcHKZQw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1Qw1', 'barcodes/ltc.png');

CREATE TABLE IF NOT EXISTS deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(64) NOT NULL,
    coin_symbol VARCHAR(10) NOT NULL,
    amount DECIMAL(18,8) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_onscreen TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(64) NOT NULL,
    method ENUM('wallet','bank') NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    details TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);



CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    reply TEXT,
    replied_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('pending','resolved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS p2p_bank_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(32) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    branch_code VARCHAR(32) DEFAULT NULL,
    bank_address VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS p2p_deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(64) NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(32) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    branch_code VARCHAR(32),
    bank_address VARCHAR(255),
    payment_proof VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS giftcard_deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_name VARCHAR(100) NOT NULL,
    card_amount DECIMAL(18,2) NOT NULL,
    card_code VARCHAR(100) NOT NULL,
    card_category VARCHAR(100) NOT NULL,
    card_image VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS admin_notification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(64) NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(0) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);


INSERT INTO admins (name, email, password) VALUES (
    'Super Admin',
    'Admin@eagle.com',
    'Admin123'
) ON DUPLICATE KEY UPDATE email=email;

UPDATE admin_notification SET is_read = 0 WHERE 1;