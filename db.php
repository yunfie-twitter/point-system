<?php
require_once 'config.php';

function get_db() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('データベース接続エラー');
        }
    }
    
    return $pdo;
}

// ユーザー取得または作成
function get_or_create_user($oauth_provider, $oauth_id, $user_data) {
    $db = get_db();
    
    // 既存ユーザー確認
    $stmt = $db->prepare(
        "SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ?"
    );
    $stmt->execute([$oauth_provider, $oauth_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // ログイン日時更新
        $stmt = $db->prepare(
            "UPDATE users SET last_login_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$user['id']]);
        return $user;
    }
    
    // 新規ユーザー作成
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO users (oauth_provider, oauth_id, email, username, avatar_url, last_login_at) 
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $oauth_provider,
            $oauth_id,
            $user_data['email'] ?? null,
            $user_data['username'] ?? null,
            $user_data['avatar'] ?? null
        ]);
        
        $user_id = $db->lastInsertId();
        
        // ポイントテーブル初期化
        $stmt = $db->prepare(
            "INSERT INTO user_points (user_id, normal_points, bonus_points) VALUES (?, 0, 0)"
        );
        $stmt->execute([$user_id]);
        
        // ランクテーブル初期化
        $stmt = $db->prepare(
            "INSERT INTO user_ranks (user_id, rank, monthly_score) VALUES (?, 'None', 0)"
        );
        $stmt->execute([$user_id]);
        
        $db->commit();
        
        // 新規登録ボーナス
        add_points($user_id, 500, 0, 'add', '新規登録ボーナス');
        
        // 作成したユーザー取得
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
