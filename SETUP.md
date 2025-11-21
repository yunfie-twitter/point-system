# セットアップガイド

## 1. 前提条件

- PHP 7.4以上
- MySQL 5.7以上 / MariaDB 10.2以上
- Apache / Nginx (ウェブサーバー)
- p2pear OAuthクライアントID/Secret
- Misskeyアカウント

## 2. データベースセットアップ

### 2-1. データベース作成

```bash
mysql -u root -p
```

```sql
CREATE DATABASE cf866966_wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'wallet_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON cf866966_wallet.* TO 'wallet_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2-2. テーブル作成

```bash
# 提供されたSQLダンプをインポート
mysql -u wallet_user -p cf866966_wallet < schema.sql

# webhook_tokensテーブル追加
mysql -u wallet_user -p cf866966_wallet < schema_webhook_tokens.sql

# サンプルデータ投入
mysql -u wallet_user -p cf866966_wallet < sample_events.sql
```

## 3. ファイル配置

### 3-1. ファイルアップロード

すべてのファイルをウェブサーバーのドキュメントルートにアップロード:

```
/var/www/html/point-system/
├── config.php
├── db.php
├── functions.php
├── ...
```

### 3-2. ファイルパーミッション設定

```bash
chmod 755 /var/www/html/point-system
chmod 644 /var/www/html/point-system/*.php
chmod 644 /var/www/html/point-system/*.css
```

## 4. 設定ファイル編集

### 4-1. config.php の編集

```php
<?php
// データベース設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'cf866966_wallet');
define('DB_USER', 'wallet_user');
define('DB_PASS', 'your_secure_password'); // 変更必須

// p2pear OAuth設定
define('OAUTH_CLIENT_ID', 'your_client_id'); // 変更必須
define('OAUTH_CLIENT_SECRET', 'your_client_secret'); // 変更必須
define('OAUTH_REDIRECT_URI', 'https://yourdomain.com/callback.php'); // 変更必須

// Webhook設定
define('WEBHOOK_BASE_URL', 'https://yourdomain.com/webhook.php'); // 変更必須

// デバッグモード (本番環境ではfalseに)
define('DEBUG_MODE', false);
```

### 4-2. misskey_setup.php の編集

66行目付近の `callback` URLを変更:

```php
$miauth_url = "https://misskey.io/miauth/{$session_id}" . '?' . http_build_query([
    'name' => 'ポイントシステム',
    'callback' => 'https://yourdomain.com/misskey_callback.php', // 変更
    'permission' => 'read:account'
]);
```

## 5. p2pear OAuthクライアント設定

1. [p2pear開発者コンソール](https://accounts.p2pear.asia/developers) にアクセス
2. 新規OAuthアプリケーションを作成
3. リダイレクトURIを設定: `https://yourdomain.com/callback.php`
4. Client IDとClient Secretをconfig.phpに設定

## 6. ウェブサーバー設定

### Apacheの場合

`.htaccess` が含まれているのでそのまま使用できます。

### Nginxの場合

`/etc/nginx/sites-available/point-system`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/html/point-system;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # センシティブファイル保護
    location ~ /(config\.php|db\.php|functions\.php|\.sql)$ {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/point-system /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 7. SSL証明書設定 (推奨)

```bash
sudo certbot --nginx -d yourdomain.com
```

## 8. 動作確認

### 8-1. 基本動作

1. `https://yourdomain.com/` にアクセス
2. ログインボタンをクリック
3. p2pearで認証
4. ダッシュボードが表示されることを確認

### 8-2. Misskey連携

1. ダッシュボードから "Misskey連携" をクリック
2. Miauthで認証
3. Webhook URLが生成されることを確認
4. Misskeyの設定でWebhookを追加
5. テスト投稿してポイントが付与されることを確認

## 9. トラブルシューティング

### データベース接続エラー

```bash
# PHPエラーログ確認
tail -f /var/log/apache2/error.log
# または
tail -f /var/log/nginx/error.log

# MySQL接続テスト
mysql -u wallet_user -p cf866966_wallet
```

### Webhookが動かない

```bash
# webhook.phpに直接アクセスしてテスト
curl -X POST https://yourdomain.com/webhook.php?token=test_token \
  -H "Content-Type: application/json" \
  -d '{"body":{"note":{"id":"test123","text":"test"}}}'

# トークン確認
mysql -u wallet_user -p cf866966_wallet -e "SELECT * FROM webhook_tokens;"
```

### ポイントが付与されない

```sql
-- イベント確認
SELECT * FROM point_events WHERE enabled = 1;

-- ログ確認
SELECT * FROM point_event_logs ORDER BY executed_at DESC LIMIT 10;
SELECT * FROM misskey_post_logs ORDER BY created_at DESC LIMIT 10;
```

## 10. セキュリティ強化

### ファイアウォール設定

```bash
# UFWを使用する場合
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable
```

### 定期バックアップ

```bash
# cron設定
crontab -e

# 毎日深姂3時にバックアップ
0 3 * * * mysqldump -u wallet_user -p'password' cf866966_wallet > /backup/wallet_$(date +\%Y\%m\%d).sql
```

## 11. メンテナンス

### ポイント有効期限処理

定期実行するcronスクリプトが必要です。

`expire_points.php` を作成:

```php
<?php
require_once 'config.php';
require_once 'db.php';

$db = get_db();

// 有効期限切れポイント取得
$stmt = $db->query(
    "SELECT * FROM point_expirations WHERE expires_at <= NOW()"
);
$expired = $stmt->fetchAll();

foreach ($expired as $exp) {
    // ポイント減算
    if ($exp['type'] === 'normal') {
        $db->prepare("UPDATE user_points SET normal_points = normal_points - ? WHERE user_id = ?")
           ->execute([$exp['points'], $exp['user_id']]);
    } else {
        $db->prepare("UPDATE user_points SET bonus_points = bonus_points - ? WHERE user_id = ?")
           ->execute([$exp['points'], $exp['user_id']]);
    }
    
    // ログ記録
    add_points($exp['user_id'], 0, 0, 'expire', 'ポイント有効期限切れ');
    
    // 削除
    $db->prepare("DELETE FROM point_expirations WHERE id = ?")->execute([$exp['id']]);
}

echo "Processed " . count($expired) . " expired points.\n";
```

cron設定:

```bash
0 0 * * * cd /var/www/html/point-system && php expire_points.php
```

## 12. 完了！

セットアップが完了しました。

システムを楽しんでください！
