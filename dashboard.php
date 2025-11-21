<?php
require_once 'config.php';
require_once 'functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$points = get_user_points($user_id);
$db = get_db();

$stmt = $db->prepare("SELECT rank, monthly_score FROM user_ranks WHERE user_id = ?");
$stmt->execute([$user_id]);
$rank_info = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM point_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$recent_logs = $stmt->fetchAll();

$stmt = $db->query("SELECT * FROM point_events WHERE event_type = 'misskey' AND enabled = 1");
$misskey_events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h1>🎁 ポイントシステム</h1>
        <nav>
            <a href="dashboard.php">ダッシュボード</a>
            <a href="misskey_setup.php">Misskey連携</a>
            <a href="exchange.php">交換</a>
            <a href="ranking.php">ランキング</a>
            <a href="logout.php">ログアウト</a>
        </nav>
    </div>

    <div class="container">
        <div class="points-card">
            <div class="points-display"><?= number_format($points['total']) ?></div>
            <p>保有ポイント</p>
            <p style="color: #666; font-size: 0.9rem;">
                通常: <?= number_format($points['normal']) ?> | ボーナス: <?= number_format($points['bonus']) ?>
            </p>
            <div class="rank-badge rank-<?= htmlspecialchars($rank_info['rank']) ?>">
                <?= htmlspecialchars($rank_info['rank']) ?> ランク
            </div>
            <p style="margin-top: 0.5rem; color: #666; font-size: 0.9rem;">
                今月のスコア: <?= number_format($rank_info['monthly_score']) ?>
            </p>
        </div>

        <div class="grid">
            <div class="card">
                <h3>Misskey連携</h3>
                <p>投稿でポイント獲得！</p>
                <a href="misskey_setup.php" class="btn">連携設定</a>
                <?php if ($misskey_events): ?>
                    <div style="margin-top: 1rem;">
                        <?php foreach ($misskey_events as $event): ?>
                            <div class="event-item">
                                <strong><?= htmlspecialchars($event['name']) ?></strong><br>
                                <small><?= htmlspecialchars($event['description']) ?></small><br>
                                <span class="points">+<?= $event['points'] ?>pt</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>最近の履歴</h3>
                <?php if ($recent_logs): ?>
                    <?php foreach ($recent_logs as $log): ?>
                        <div class="log-item">
                            <div>
                                <div><?= htmlspecialchars($log['reason']) ?></div>
                                <small><?= date('Y/m/d H:i', strtotime($log['created_at'])) ?></small>
                            </div>
                            <div class="<?= ($log['normal_delta'] + $log['bonus_delta'] >= 0) ? 'points-positive' : 'points-negative' ?>">
                                <?= ($log['normal_delta'] + $log['bonus_delta'] >= 0 ? '+' : '') ?>
                                <?= number_format($log['normal_delta'] + $log['bonus_delta']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #999;">履歴なし</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
