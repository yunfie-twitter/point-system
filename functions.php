<?php
require_once 'db.php';

// ログイン確認
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

// ポイント取得
function get_user_points($user_id) {
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT normal_points, bonus_points FROM user_points WHERE user_id = ?"
    );
    $stmt->execute([$user_id]);
    $points = $stmt->fetch();
    
    return [
        'normal' => $points['normal_points'] ?? 0,
        'bonus' => $points['bonus_points'] ?? 0,
        'total' => ($points['normal_points'] ?? 0) + ($points['bonus_points'] ?? 0)
    ];
}

// ポイント追加
function add_points($user_id, $normal_delta, $bonus_delta, $change_type, $reason) {
    $db = get_db();
    $db->beginTransaction();
    
    try {
        // ポイント更新
        $stmt = $db->prepare(
            "UPDATE user_points 
             SET normal_points = normal_points + ?, 
                 bonus_points = bonus_points + ?
             WHERE user_id = ?"
        );
        $stmt->execute([$normal_delta, $bonus_delta, $user_id]);
        
        // ログ記録
        $stmt = $db->prepare(
            "INSERT INTO point_logs (user_id, change_type, normal_delta, bonus_delta, reason)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$user_id, $change_type, $normal_delta, $bonus_delta, $reason]);
        
        // 有効期限設定
        if ($normal_delta > 0) {
            $expire_date = date('Y-m-d H:i:s', strtotime('+' . POINT_EXPIRE_DAYS . ' days'));
            $stmt = $db->prepare(
                "INSERT INTO point_expirations (user_id, type, points, expires_at)
                 VALUES (?, 'normal', ?, ?)"
            );
            $stmt->execute([$user_id, $normal_delta, $expire_date]);
        }
        
        if ($bonus_delta > 0) {
            $expire_date = date('Y-m-d H:i:s', strtotime('+' . BONUS_POINT_EXPIRE_DAYS . ' days'));
            $stmt = $db->prepare(
                "INSERT INTO point_expirations (user_id, type, points, expires_at)
                 VALUES (?, 'bonus', ?, ?)"
            );
            $stmt->execute([$user_id, $bonus_delta, $expire_date]);
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Add points error: ' . $e->getMessage());
        return false;
    }
}

// イベント実行チェック
function can_execute_event($user_id, $event_id) {
    $db = get_db();
    
    // イベント情報取得
    $stmt = $db->prepare(
        "SELECT cooldown_seconds, daily_limit FROM point_events WHERE id = ? AND enabled = 1"
    );
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        return ['ok' => false, 'reason' => 'イベントが見つかりません'];
    }
    
    // クールダウンチェック
    if ($event['cooldown_seconds']) {
        $stmt = $db->prepare(
            "SELECT MAX(executed_at) as last_exec 
             FROM point_event_logs 
             WHERE user_id = ? AND event_id = ?"
        );
        $stmt->execute([$user_id, $event_id]);
        $log = $stmt->fetch();
        
        if ($log && $log['last_exec']) {
            $next_time = strtotime($log['last_exec']) + $event['cooldown_seconds'];
            if (time() < $next_time) {
                $wait = $next_time - time();
                return ['ok' => false, 'reason' => 'クールダウン中です(残り' . ceil($wait / 60) . '分)'];
            }
        }
    }
    
    // 日次制限チェック
    if ($event['daily_limit']) {
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
             FROM point_event_logs 
             WHERE user_id = ? AND event_id = ? AND DATE(executed_at) = CURDATE()"
        );
        $stmt->execute([$user_id, $event_id]);
        $count = $stmt->fetch()['count'];
        
        if ($count >= $event['daily_limit']) {
            return ['ok' => false, 'reason' => '本日の実行回数上限に達しました'];
        }
    }
    
    return ['ok' => true];
}

// イベント実行
function execute_event($user_id, $event_id, $extra_data = null) {
    $check = can_execute_event($user_id, $event_id);
    if (!$check['ok']) {
        return $check;
    }
    
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT points FROM point_events WHERE id = ?"
    );
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        return ['ok' => false, 'reason' => 'イベントが見つかりません'];
    }
    
    // ポイント付与
    $points = $event['points'];
    add_points($user_id, $points, 0, 'add', 'イベント: ID=' . $event_id);
    
    // ログ記録
    $stmt = $db->prepare(
        "INSERT INTO point_event_logs (user_id, event_id, points, ip, user_agent, extra_data)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $user_id,
        $event_id,
        $points,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $extra_data ? json_encode($extra_data) : null
    ]);
    
    return ['ok' => true, 'points' => $points];
}

// Webhook URL生成
function generate_webhook_url($user_id, $event_id) {
    $db = get_db();
    
    // 既存トークン削除
    $db->prepare("DELETE FROM webhook_tokens WHERE user_id = ? AND event_id = ?")
       ->execute([$user_id, $event_id]);
    
    // 新規トークン生成
    $token = bin2hex(random_bytes(32));
    $db->prepare(
        "INSERT INTO webhook_tokens (user_id, event_id, token, expires_at) 
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 YEAR))"
    )->execute([$user_id, $event_id, $token]);
    
    return WEBHOOK_BASE_URL . '?token=' . $token;
}

// Webhook検証
function verify_webhook_token($token) {
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT user_id, event_id FROM webhook_tokens 
         WHERE token = ? AND expires_at > NOW()"
    );
    $stmt->execute([$token]);
    return $stmt->fetch();
}

// ランク更新
function update_user_rank($user_id) {
    $db = get_db();
    
    // 月間スコア取得
    $stmt = $db->prepare(
        "SELECT SUM(points) as monthly_score 
         FROM point_event_logs 
         WHERE user_id = ? AND DATE_FORMAT(executed_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')"
    );
    $stmt->execute([$user_id]);
    $score = $stmt->fetch()['monthly_score'] ?? 0;
    
    // ランク判定
    $rank = 'None';
    if ($score >= RANK_GOLD_THRESHOLD) {
        $rank = 'Gold';
    } elseif ($score >= RANK_SILVER_THRESHOLD) {
        $rank = 'Silver';
    }
    
    // ランク更新
    $stmt = $db->prepare(
        "UPDATE user_ranks SET rank = ?, monthly_score = ? WHERE user_id = ?"
    );
    $stmt->execute([$rank, $score, $user_id]);
    
    return $rank;
}
