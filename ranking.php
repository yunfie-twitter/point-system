<?php
require_once 'config.php';
require_once 'functions.php';
require_login();

$db = get_db();

// 月間ランキング
$stmt = $db->query(
    "SELECT u.username, ur.monthly_score, ur.rank 
     FROM user_ranks ur 
     JOIN users u ON ur.user_id = u.id 
     ORDER BY ur.monthly_score DESC 
     LIMIT 50"
);
$rankings = $stmt->fetchAll();

// 総ポイントランキング
$stmt = $db->query(
    "SELECT u.username, up.normal_points + up.bonus_points as total_points 
     FROM user_points up 
     JOIN users u ON up.user_id = u.id 
     ORDER BY total_points DESC 
     LIMIT 50"
);
$total_rankings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ランキング</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h1>🏆 ランキング</h1>
        <nav>
            <a href="dashboard.php">ダッシュボード</a>
            <a href="exchange.php">交換</a>
            <a href="ranking.php">ランキング</a>
            <a href="logout.php">ログアウト</a>
        </nav>
    </div>

    <div class="container">
        <div class="grid">
            <div class="card">
                <h2>📅 月間ランキング</h2>
                <table>
                    <thead>
                        <tr>
                            <th>順位</th>
                            <th>ユーザー</th>
                            <th>スコア</th>
                            <th>ランク</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rankings as $i => $rank): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($rank['username']) ?></td>
                                <td><?= number_format($rank['monthly_score']) ?></td>
                                <td><span class="rank-badge rank-<?= $rank['rank'] ?>"><?= $rank['rank'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>💰 総ポイントランキング</h2>
                <table>
                    <thead>
                        <tr>
                            <th>順位</th>
                            <th>ユーザー</th>
                            <th>ポイント</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($total_rankings as $i => $rank): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($rank['username']) ?></td>
                                <td><?= number_format($rank['total_points']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
