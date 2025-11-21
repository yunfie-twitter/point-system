<?php
require_once 'config.php';
require_once 'functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$points = get_user_points($user_id);
$db = get_db();

// 交換処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $item_id = (int)$_POST['item_id'];
    
    $stmt = $db->prepare("SELECT * FROM reward_items WHERE id = ? AND is_active = 1");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if ($item && $points['total'] >= $item['cost_points']) {
        $db->beginTransaction();
        try {
            // ボーナスポイント優先消費
            $remaining = $item['cost_points'];
            $bonus_used = min($points['bonus'], $remaining);
            $normal_used = $remaining - $bonus_used;
            
            $stmt = $db->prepare(
                "UPDATE user_points SET normal_points = normal_points - ?, bonus_points = bonus_points - ? WHERE user_id = ?"
            );
            $stmt->execute([$normal_used, $bonus_used, $user_id]);
            
            // ログ記録
            add_points($user_id, -$normal_used, -$bonus_used, 'use', '交換: ' . $item['name']);
            
            // 交換ログ
            $stmt = $db->prepare(
                "INSERT INTO point_exchange_logs (user_id, item_id, cost_points, status) VALUES (?, ?, ?, 'completed')"
            );
            $stmt->execute([$user_id, $item_id, $item['cost_points']]);
            
            $db->commit();
            $success = true;
        } catch (Exception $e) {
            $db->rollBack();
            $error = '交換に失敗しました';
        }
    } else {
        $error = 'ポイントが不足しています';
    }
}

// 交換アイテム一覧
$stmt = $db->query("SELECT * FROM reward_items WHERE is_active = 1 ORDER BY cost_points");
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ポイント交換</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h1>🎁 ポイント交換</h1>
        <nav>
            <a href="dashboard.php">ダッシュボード</a>
            <a href="exchange.php">交換</a>
            <a href="ranking.php">ランキング</a>
            <a href="logout.php">ログアウト</a>
        </nav>
    </div>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert success">交換が完了しました！</div>
        <?php elseif (isset($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>保有ポイント: <?= number_format($points['total']) ?></h2>
        </div>

        <div class="grid">
            <?php foreach ($items as $item): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                    <p><?= htmlspecialchars($item['description']) ?></p>
                    <div class="item-cost"><?= number_format($item['cost_points']) ?> ポイント</div>
                    <form method="post">
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <button type="submit" class="btn" <?= ($points['total'] < $item['cost_points']) ? 'disabled' : '' ?>>
                            交換する
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
