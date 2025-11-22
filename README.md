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

### 🆕 新機能

#### 通知システム
- リアルタイム通知
- 既読/未読管理
- ポイント獲得、ランク変動などの通知

#### ユーザー設定
- 表示名変更
- メールアドレス設定
- 通知設定のカスタマイズ
- アカウント削除機能

#### 管理パネル
- イベント管理（追加・編集・削除）
- 商品管理（在庫・価格設定）
- ユーザーポイント調整
- システム統計表示
- アクティビティログ

#### 紹介プログラム
- 紹介コード生成
- 紹介報酬システム
- 紹介履歴表示

#### アンケートシステム
- アンケート回答でポイント獲得
- アンケート一覧表示
- 回答済み管理

#### REST API
- ユーザー情報取得
- ポイント情報取得
- 履歴取得
- ランキング取得
- ポイント付与（管理者用）
- システム統計取得

## セットアップ

### 1. 初期セットアップ

**setup.php にアクセス**して自動セットアップを実行:

```
https://yourdomain.com/setup.php
```

以下のテーブルが自動的に作成されます:
- users
- point_events
- point_history
- webhook_tokens
- exchange_products
- exchange_history
- misskey_post_logs
- notifications
- user_settings
- referrals

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

// サイトURL（紹介機能用）
define('SITE_URL', 'https://yourdomain.com');
```

### 3. 管理者権限設定

データベースで管理者ユーザーの `is_admin` を 1 に設定:

```sql
UPDATE users SET is_admin = 1 WHERE user_id = 'your_user_id';
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

6. **紹介プログラム**
   - 紹介コードを友達にシェア
   - 友達が登録すると両者にボーナスポイント

### 管理者側

1. **admin.php にアクセス**
   - イベント管理
   - 商品管理
   - ユーザー管理
   - システム統計確認

2. **イベント追加**
   - 新規イベント作成
   - ポイント数、クールダウン、日次制限を設定

3. **商品追加**
   - 交換可能な商品を追加
   - 必要ポイント数と在庫を設定

## ファイル構成

```
/
├── config.php              # 設定
├── db.php                  # DB接続
├── functions.php           # 共通関数
├── header.php             # 共通ヘッダー 🆕
├── footer.php             # 共通フッター 🆕
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
├── history.php            # ポイント履歴 🆕
├── notifications.php      # 通知一覧 🆕
├── settings.php           # ユーザー設定 🆕
├── admin.php              # 管理パネル 🆕
├── setup.php              # 初期セットアップ 🆕
├── api.php                # REST API 🆕
├── referral.php           # 紹介プログラム 🆕
├── survey.php             # アンケート回答 🆕
├── survey_list.php        # アンケート一覧 🆕
├── expire_points.php      # ポイント有効期限処理
├── style.css              # スタイル
├── schema_webhook_tokens.sql
├── sample_events.sql
├── SETUP.md
└── README.md
```

## API エンドポイント

### GET /api.php

#### ユーザー情報取得
```
GET /api.php?endpoint=user&user_id={user_id}
```

#### ポイント情報取得
```
GET /api.php?endpoint=points&user_id={user_id}
```

#### 履歴取得
```
GET /api.php?endpoint=history&user_id={user_id}&limit=20&offset=0
```

#### ランキング取得
```
GET /api.php?endpoint=ranking&limit=10&type=monthly
```

#### イベント一覧
```
GET /api.php?endpoint=events
```

#### 商品一覧
```
GET /api.php?endpoint=products
```

#### システム統計
```
GET /api.php?endpoint=stats
```

### POST /api.php

#### ポイント付与
```json
POST /api.php?endpoint=add_points
Content-Type: application/json

{
  "user_id": "user123",
  "points": 100,
  "reason": "API経由でポイント付与",
  "point_type": "bonus"
}
```

## セキュリティ

- トークンはランダム生成 (64文字)
- トークンに有効期限あり (1年)
- Note IDで重複チェック
- クールダウンと日次制限で不正防止
- SQL インジェクション対策（プリペアドステートメント使用）
- XSS対策（htmlspecialchars使用）

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

### 管理パネルにアクセスできない

1. is_admin が 1 に設定されているか確認
2. セッションが有効か確認
3. ログインし直す

## 今後の予定機能

- [ ] メール通知機能
- [ ] ポイント履歴のエクスポート（CSV）
- [ ] グラフ表示（ポイント獲得推移）
- [ ] バッジシステム
- [ ] マルチ言語対応
- [ ] テーマ切り替え機能

## ライセンス

MIT License

## 作者

ゆんふぃ (@yunfie_misskey)

## リポジトリ

https://github.com/yunfie-twitter/point-system
