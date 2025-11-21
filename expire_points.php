<?php
/**
 * ポイント有効期限処理スクリプト
 * cronで毎日実行することを推奨
 * 
 * 実行例: 0 0 * * * cd /path/to && php expire_points.php
 */

require_once 'config.php';
require_once 'functions.php';

// CLI実行のみ許可
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

$db = get_db();
$processed = 0;
$errors = 0;

echo "[" . date('Y-m-d H:i:s') . "] Starting point expiration process...\n";

try {
    // 有効期限切れポイント取得
    $stmt = $db->query(
        "SELECT pe.*, u.username 
         FROM point_expirations pe 
         JOIN users u ON pe.user_id = u.id 
         WHERE pe.expires_at <= NOW()
         ORDER BY pe.user_id, pe.expires_at"
    );
    $expired = $stmt->fetchAll();
    
    echo "Found " . count($expired) . " expired point records.\n";
    
    foreach ($expired as $exp) {
        $db->beginTransaction();
        
        try {
            // 現在のポイント取得
            $stmt = $db->prepare(
                "SELECT normal_points, bonus_points FROM user_points WHERE user_id = ?"
            );
            $stmt->execute([$exp['user_id']]);
            $current = $stmt->fetch();
            
            if (!$current) {
                echo "  [WARNING] User #{$exp['user_id']} has no points record\n";
                continue;
            }
            
            // ポイント減算 (マイナスにはしない)
            if ($exp['type'] === 'normal') {
                $deduct = min($exp['points'], $current['normal_points']);
                $stmt = $db->prepare(
                    "UPDATE user_points SET normal_points = normal_points - ? WHERE user_id = ?"
                );
                $stmt->execute([$deduct, $exp['user_id']]);
                
                echo "  User: {$exp['username']} (#{$exp['user_id']}) - Expired {$deduct} normal points\n";
            } else {
                $deduct = min($exp['points'], $current['bonus_points']);
                $stmt = $db->prepare(
                    "UPDATE user_points SET bonus_points = bonus_points - ? WHERE user_id = ?"
                );
                $stmt->execute([$deduct, $exp['user_id']]);
                
                echo "  User: {$exp['username']} (#{$exp['user_id']}) - Expired {$deduct} bonus points\n";
            }
            
            // ログ記録
            if ($exp['type'] === 'normal') {
                add_points($exp['user_id'], -$deduct, 0, 'expire', '通常ポイント有効期限切れ');
            } else {
                add_points($exp['user_id'], 0, -$deduct, 'expire', 'ボーナスポイント有効期限切れ');
            }
            
            // 有効期限レコード削除
            $stmt = $db->prepare("DELETE FROM point_expirations WHERE id = ?");
            $stmt->execute([$exp['id']]);
            
            $db->commit();
            $processed++;
            
        } catch (Exception $e) {
            $db->rollBack();
            echo "  [ERROR] Failed to process expiration #{$exp['id']}: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Process completed.\n";
    echo "Processed: {$processed}\n";
    echo "Errors: {$errors}\n";
    
    // 次に期限切れが近いポイントを表示
    $stmt = $db->query(
        "SELECT COUNT(*) as count, MIN(expires_at) as next_expiry 
         FROM point_expirations 
         WHERE expires_at > NOW()"
    );
    $next = $stmt->fetch();
    
    if ($next && $next['count'] > 0) {
        echo "\nNext expiration: {$next['next_expiry']} ({$next['count']} records)\n";
    } else {
        echo "\nNo upcoming expirations.\n";
    }
    
} catch (Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
