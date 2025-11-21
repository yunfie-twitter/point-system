<?php
require_once 'config.php';
require_once 'functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$session_id = $_SESSION['miauth_session_id'] ?? null;

if (!$session_id) {
    die('セッションIDがありません');
}

// Miauthトークン確認
$ch = curl_init("https://misskey.io/api/miauth/{$session_id}/check");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die('Miauth確認エラー: ' . $response);
}

$data = json_decode($response, true);

if (!isset($data['ok']) || !$data['ok']) {
    die('Miauth認証が完了していません');
}

$misskey_token = $data['token'] ?? null;
if (!$misskey_token) {
    die('トークンが取得できませんでした');
}

// DBに保存
$db = get_db();
$stmt = $db->prepare(
    "UPDATE users SET access_token = ? WHERE id = ?"
);
$stmt->execute([$misskey_token, $user_id]);

// Webhook URL生成（イベントごと）
$stmt = $db->query(
    "SELECT id FROM point_events WHERE event_type = 'misskey' AND enabled = 1"
);
$events = $stmt->fetchAll();

$webhook_urls = [];
foreach ($events as $event) {
    $webhook_urls[$event['id']] = generate_webhook_url($user_id, $event['id']);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook URL発行</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .success { background: #4caf50; color: white; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; }
        .webhook-url {
            padding: 1rem;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            font-family: monospace;
            word-break: break-all;
            margin: 1rem 0;
        }
        .copy-btn {
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        .instructions { background: #fff3cd; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="header"><h1>✅ Misskey連携完了</h1></div>

    <div class="container">
        <div class="success">
            <strong>認証が完了しました！</strong>
        </div>

        <div class="card">
            <h2>次のステップ: Webhookを設定</h2>
            <p style="margin-bottom: 1rem; color: #666;">
                以下のWebhook URLをMisskeyの設定で登録してください。
            </p>

            <?php foreach ($webhook_urls as $event_id => $url): ?>
                <?php
                $stmt = $db->prepare("SELECT name FROM point_events WHERE id = ?");
                $stmt->execute([$event_id]);
                $event = $stmt->fetch();
                ?>
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: #667eea; margin-bottom: 0.5rem;">
                        <?= htmlspecialchars($event['name']) ?>
                    </h3>
                    <div class="webhook-url"><?= htmlspecialchars($url) ?></div>
                    <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($url) ?>')">
                        📋 URLをコピー
                    </button>
                </div>
            <?php endforeach; ?>

            <div class="instructions">
                <strong>📝 設定手順:</strong>
                <ol style="margin: 0.5rem 0 0 1.5rem;">
                    <li>Misskeyの設定ページを開く</li>
                    <li>Webhookセクションに移動</li>
                    <li>上記のURLを追加</li>
                    <li>イベントを選択（note作成など）</li>
                    <li>保存</li>
                </ol>
            </div>
        </div>

        <a href="dashboard.php" style="display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 0.5rem;">
            ダッシュボードに戻る
        </a>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('URLをコピーしました！');
            });
        }
    </script>
</body>
</html>
