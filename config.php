<?php
// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// データベース設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'cf866966_wallet');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_pass');

// p2pear OAuth設定
define('OAUTH_CLIENT_ID', 'your_client_id');
define('OAUTH_CLIENT_SECRET', 'your_client_secret');
define('OAUTH_REDIRECT_URI', 'https://yourdomain.com/callback.php');
define('OAUTH_AUTHORIZE_URL', 'https://accounts.p2pear.asia/authorize');
define('OAUTH_TOKEN_URL', 'https://accounts.p2pear.asia/token');
define('OAUTH_API_BASE', 'https://accounts.p2pear.asia/api');

// Miauth設定
define('MIAUTH_BASE_URL', 'https://misskey.io/miauth');
define('MIAUTH_SESSION_PREFIX', 'miauth_');
define('MIAUTH_PERMISSIONS', 'read:account,write:notes');

// Webhook設定
define('WEBHOOK_SECRET', bin2hex(random_bytes(32))); // 初回実行時に生成して固定値に変更
define('WEBHOOK_BASE_URL', 'https://yourdomain.com/webhook.php');

// ポイント設定
define('POINT_EXPIRE_DAYS', 365); // 通常ポイント有効期限
define('BONUS_POINT_EXPIRE_DAYS', 90); // ボーナスポイント有効期限
define('REFERRAL_POINTS', 100); // 紹介報酬ポイント

// ランク閾値
define('RANK_SILVER_THRESHOLD', 1000);
define('RANK_GOLD_THRESHOLD', 5000);

// デバッグモード
define('DEBUG_MODE', true);

// エラーログ
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// ヘルパー関数
function random_str($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

function debug_log($message, $data = null) {
    if (DEBUG_MODE) {
        error_log($message . ($data ? ': ' . print_r($data, true) : ''));
    }
}
