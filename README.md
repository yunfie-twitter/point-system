# ポイント報酬システム with Misskey Webhook

## 概要

Misskeyの投稿をwebhookで受け取り、ポイントを付与するシステムです。

## 主な機能

### 認証システム
- p2pear OAuthでログイン
- ユーザー管理とセッション

### Misskey連携
1. **Miauth認証**: Misskeyアカウントと連携
2. **Webhook URL発行**: イベントごとに固有のURLを生成
3. **投稿検知**: webhookでMisskeyの投稿を受信
4. **ポイント付与**: 条件を満たした投稿に自動付与

### ポイントシステム
- 通常ポイントとボーナスポイント
- ポイント有効期限管理
- クールダウンと日次制限
- ポイント履歴記録

### ランクシステム
- None / Silver / Gold ランク
- 月間スコア計算
- ランキング表示

### 交換機能
- ポイントで商品交換
- 在庫管理
- 交換履歴

## セットアップ

### 1. データベースセットアップ

```bash
# 提供されたSQLダンプをインポート
mysql -u username -p cf866966_wallet < schema.sql

# webhook_tokensテーブルを追加
mysql -u username -p cf866966_wallet < schema_webhook_tokens.sql
```

### 2. 設定ファイル編集

`config.php` を編集:

```php
// データベース
define('DB_HOST', 'localhost');
define('DB_NAME', 'cf866966_wallet');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_pass');

// p2pear OAuth
define('OAUTH_CLIENT_ID', 'your_client_id');
define('OAUTH_CLIENT_SECRET', 'your_client_secret');
define('OAUTH_REDIRECT_URI', 'https://yourdomain.com/callback.php');

// Webhook URL
define('WEBHOOK_BASE_URL', 'https://yourdomain.com/webhook.php');
```

### 3. イベント追加

```sql
-- Misskey投稿イベントの例
INSERT INTO point_events 
(event_key, event_type, name, description, points, cooldown_seconds, daily_limit, enabled) 
VALUES 
('misskey_post_hashtag', 'misskey', 'ハッシュタグ付き投稿', '指定ハッシュタグ付きで投稿する', 10, 3600, 5, 1);
```

## 使い方

### ユーザー側

1. **ログイン**
   - `index.php` からログイン
   - p2pear OAuthで認証

2. **Misskey連携**
   - ダッシュボードから "Misskey連携" をクリック
   - MiauthでMisskey認証
   - 生成されたWebhook URLをコピー

3. **MisskeyでWebhook設定**
   - Misskeyの設定→Webhook
   - コピーしたURLを追加
   - イベントを選択 (note作成など)

4. **投稿してポイント獲得**
   - Misskeyで投稿
   - 自動的にポイントが付与される

5. **ポイント交換**
   - 交換ページから商品を選択
   - ポイントで交換

## ファイル構成

```
/
├── config.php              # 設定
├── db.php                  # DB接続
├── functions.php           # 共通関数
├── index.php              # トップページ
├── login.php              # OAuth開始
├── callback.php           # OAuthコールバック
├── logout.php             # ログアウト
├── dashboard.php          # ダッシュボード
├── misskey_setup.php      # Miauth認証開始
├── misskey_callback.php   # Miauthコールバック
├── webhook.php            # Webhook受信
├── exchange.php           # ポイント交換
├── ranking.php            # ランキング
├── style.css              # スタイル
├── schema_webhook_tokens.sql
└── README.md
```

## セキュリティ

- トークンはランダム生成 (64文字)
- トークンに有効期限あり (1年)
- Note IDで重複チェック
- クールダウンと日次制限で不正防止

## Webhookペイロード例

```json
{
  "hookId": "...",
  "userId": "...",
  "eventId": "...",
  "createdAt": 1234567890,
  "type": "note",
  "body": {
    "note": {
      "id": "note_id_here",
      "text": "投稿内容 #ハッシュタグ",
      "url": "https://misskey.io/notes/...",
      "userId": "..."
    }
  }
}
```

## トラブルシューティング

### Webhookが反応しない

1. webhook.phpにアクセスできるか確認
2. トークンが有効か確認 (webhook_tokensテーブル)
3. エラーログを確認

### ポイントが付与されない

1. イベントがenabled=1か確認
2. クールダウン中ではないか確認
3. 日次制限に達していないか確認
4. misskey_post_logsで重複確認

## ライセンス

MIT License

## 作者

ゆんふぃ (@yunfie_misskey)
