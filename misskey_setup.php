<?php
require_once 'config.php';
require_once 'functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$db = get_db();

// Misskeyイベント一覧
$stmt = $db->query("SELECT * FROM point_events WHERE event_type = 'misskey' AND enabled = 1");
$events = $stmt->fetchAll();

// セッションID生成
$session_id = MIAUTH_SESSION_PREFIX . random_str(32);
$_SESSION['miauth_session_id'] = $session_id;

// Miauth URL生成
$miauth_url = "https://misskey.io/miauth/{$session_id}" . '?' . http_build_query([
    'name' => 'ポイントシステム',
    'callback' => 'https://yourdomain.com/misskey_callback.php',
    'permission' => 'read:account'
]);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Misskey連携設定</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
        }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        h2 { margin-bottom: 1rem; color: #333; }
        .step { margin-bottom: 2rem; padding: 1rem; background: #f9f9f9; border-radius: 0.5rem; }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: bold;
        }
        .webhook-url {
            padding: 1rem;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            font-family: monospace;
            word-break: break-all;
            margin: 1rem 0;
        }
        .event-list { margin-top: 1rem; }
        .event-item {
            padding: 1rem;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔗 Misskey連携設定</h1>
    </div>

    <div class="container">
        <div class="card">
            <h2>設定手順</h2>
            
            <div class="step">
                <span class="step-number">1</span>
                <strong>Miauthで認証</strong>
                <p style="margin-top: 0.5rem; color: #666;">
                    以下のボタンをクリックしてMisskeyで認証してください。
                </p>
                <a href="<?= htmlspecialchars($miauth_url) ?>" class="btn" target="_blank">
Misskeyで認証</a>
            </div>

            <div class="step">
                <span class="step-number">2</span>
                <strong>認証完了後、戻る</strong>
                <p style="margin-top: 0.5rem; color: #666;">
                    Misskeyで認証が完了したら、自動的にこちらに戻ります。
                </p>
            </div>

            <div class="step">
                <span class="step-number">3</span>
                <strong>Webhook URLを設定</strong>
                <p style="margin-top: 0.5rem; color: #666;">
                    認証後、次のページでWebhook URLが発行されます。<br>
                    そのURLをMisskeyの設定→Webhookで登録してください。
                </p>
            </div>
        </div>

        <div class="card">
            <h2>対象イベント</h2>
            <p style="color: #666; margin-bottom: 1rem;">
                以下のイベントでポイントを獲得できます
            </p>
            
            <div class="event-list">
                <?php foreach ($events as $event): ?>
                    <div class="event-item">
                        <h3 style="color: #667eea; margin-bottom: 0.5rem;">
                            <?= htmlspecialchars($event['name']) ?>
                        </h3>
                        <p style="color: #666; margin-bottom: 0.5rem;">
                            <?= htmlspecialchars($event['description']) ?>
                        </p>
                        <div style="font-weight: bold; color: #4caf50;">
                            +<?= $event['points'] ?> ポイント
                        </div>
                        <?php if ($event['cooldown_seconds']): ?>
                            <div style="font-size: 0.9rem; color: #999; margin-top: 0.5rem;">
                                クールダウン: <?= $event['cooldown_seconds'] / 3600 ?>時間
                            </div>
                        <?php endif; ?>
                        <?php if ($event['daily_limit']): ?>
                            <div style="font-size: 0.9rem; color: #999;">
                                日次制限: <?= $event['daily_limit'] ?>回/日
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <a href="dashboard.php" class="btn">ダッシュボードに戻る</a>
    </div>
</body>
</html>
