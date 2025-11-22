<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>ポイント報酬システム</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="/favicon.ico">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="header-content">
                <h1 class="site-title"><a href="/">ポイント報酬システム</a></h1>
                <nav class="main-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php">ダッシュボード</a>
                        <a href="exchange.php">ポイント交換</a>
                        <a href="ranking.php">ランキング</a>
                        <a href="history.php">履歴</a>
                        <a href="settings.php">設定</a>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <a href="admin.php" class="admin-link">管理</a>
                        <?php endif; ?>
                        <a href="logout.php" class="logout-btn">ログアウト</a>
                    <?php else: ?>
                        <a href="login.php" class="login-btn">ログイン</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>
    <main class="main-content">
        <div class="container">
