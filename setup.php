<?php
require_once 'config.php';
require_once 'db.php';

$success = [];
$errors = [];

// セットアップ実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    // テーブル作成
    $tables = [
        'users' => "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) UNIQUE NOT NULL,
                display_name VARCHAR(255),
                email VARCHAR(255),
                points INT DEFAULT 0,
                bonus_points INT DEFAULT 0,
                rank ENUM('none', 'silver', 'gold') DEFAULT 'none',
                monthly_score INT DEFAULT 0,
                is_admin BOOLEAN DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_rank (rank)
            )
        ",
        'point_events' => "
            CREATE TABLE IF NOT EXISTS point_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_key VARCHAR(100) UNIQUE NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                points INT NOT NULL,
                cooldown_seconds INT DEFAULT 0,
                daily_limit INT DEFAULT 0,
                enabled BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_key (event_key),
                INDEX idx_enabled (enabled)
            )
        ",
        'point_history' => "
            CREATE TABLE IF NOT EXISTS point_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                points INT NOT NULL,
                point_type ENUM('normal', 'bonus') DEFAULT 'normal',
                reason TEXT,
                event_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            )
        ",
        'webhook_tokens' => "
            CREATE TABLE IF NOT EXISTS webhook_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                misskey_instance VARCHAR(255),
                misskey_user_id VARCHAR(255),
                expires_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_user_id (user_id)
            )
        ",
        'exchange_products' => "
            CREATE TABLE IF NOT EXISTS exchange_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                points_required INT NOT NULL,
                stock INT DEFAULT -1,
                enabled BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_enabled (enabled)
            )
        ",
        'exchange_history' => "
            CREATE TABLE IF NOT EXISTS exchange_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                product_id INT NOT NULL,
                points_spent INT NOT NULL,
                status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status)
            )
        ",
        'misskey_post_logs' => "
            CREATE TABLE IF NOT EXISTS misskey_post_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                note_id VARCHAR(255) UNIQUE NOT NULL,
                event_key VARCHAR(100),
                points_earned INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_note_id (note_id),
                INDEX idx_user_id (user_id)
            )
        ",
        'notifications' => "
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT,
                is_read BOOLEAN DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_is_read (is_read)
            )
        ",
        'user_settings' => "
            CREATE TABLE IF NOT EXISTS user_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) UNIQUE NOT NULL,
                email_notifications BOOLEAN DEFAULT 1,
                point_notifications BOOLEAN DEFAULT 1,
                rank_notifications BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id)
            )
        ",
        'referrals' => "
            CREATE TABLE IF NOT EXISTS referrals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                referrer_user_id VARCHAR(255) NOT NULL,
                referred_user_id VARCHAR(255) NOT NULL,
                referral_code VARCHAR(50) UNIQUE NOT NULL,
                points_earned INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_referrer (referrer_user_id),
                INDEX idx_code (referral_code)
            )
        "
    ];
    
    foreach ($tables as $table_name => $sql) {
        if ($conn->query($sql)) {
            $success[] = "{$table_name} テーブルを作成または確認しました。";
        } else {
            $errors[] = "{$table_name} テーブルの作成に失敗: " . $conn->error;
        }
    }
    
    // サンプルイベント追加
    $sample_events = [
        ['misskey_post_basic', 'misskey', '通常投稿', 'Misskeyに投稿する', 5, 0, 0],
        ['misskey_post_hashtag', 'misskey', 'ハッシュタグ付き投稿', '指定ハッシュタグ付きで投稿', 10, 3600, 5]
    ];
    
    foreach ($sample_events as $event) {
        $stmt = $conn->prepare("INSERT IGNORE INTO point_events (event_key, event_type, name, description, points, cooldown_seconds, daily_limit) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiii", $event[0], $event[1], $event[2], $event[3], $event[4], $event[5], $event[6]);
        if ($stmt->execute()) {
            $success[] = "サンプルイベント '{$event[2]}' を追加しました。";
        }
    }
}

// 現在のテーブル状態確認
$existing_tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $existing_tables[] = $row[0];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初期セットアップ - ポイント報酬システム</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .setup-card {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .message-list {
            list-style: none;
            padding: 0;
        }
        .message-list li {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
        .table-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin: 20px 0;
        }
        .table-badge {
            padding: 10px;
            text-align: center;
            border-radius: 4px;
            font-size: 14px;
        }
        .table-badge.exists {
            background: #d4edda;
            color: #155724;
        }
        .table-badge.missing {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <h1>ポイント報酬システム - 初期セットアップ</h1>
            <p>このページでシステムの初期セットアップを実行します。</p>
            
            <?php if (!empty($success)): ?>
                <ul class="message-list">
                    <?php foreach ($success as $msg): ?>
                        <li class="success">✓ <?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <ul class="message-list">
                    <?php foreach ($errors as $msg): ?>
                        <li class="error">✗ <?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <h3>現在のテーブル状態</h3>
            <div class="table-status">
                <?php
                $required_tables = ['users', 'point_events', 'point_history', 'webhook_tokens', 'exchange_products', 'exchange_history', 'misskey_post_logs', 'notifications', 'user_settings', 'referrals'];
                foreach ($required_tables as $table) {
                    $exists = in_array($table, $existing_tables);
                    echo "<div class='table-badge " . ($exists ? "exists" : "missing") . "'>";
                    echo $exists ? "✓" : "✗";
                    echo " {$table}</div>";
                }
                ?>
            </div>
            
            <form method="post">
                <button type="submit" name="run_setup" class="btn-primary" style="width: 100%; padding: 15px; font-size: 16px; margin-top: 20px;">
                    セットアップ実行
                </button>
            </form>
            
            <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #ddd;">
                <h3>次のステップ</h3>
                <ol>
                    <li>セットアップが完了したら <a href="index.php">index.php</a> にアクセス</li>
                    <li>p2pear OAuthでログイン</li>
                    <li>管理者権限を設定する場合はDBで is_admin を 1 に設定</li>
                    <li>Misskey連携を設定</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>
