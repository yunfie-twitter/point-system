<?php
require_once 'config.php';
require_once 'db.php';

if (isset($_GET['error'])) die('認証エラー: ' . htmlspecialchars($_GET['error']));

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (!$code || !$state || $state !== ($_SESSION['oauth_state'] ?? '')) {
    die('不正なリクエスト');
}

unset($_SESSION['oauth_state']);

$tokenParams = [
    'grant_type' => 'authorization_code',
    'client_id' => OAUTH_CLIENT_ID,
    'client_secret' => OAUTH_CLIENT_SECRET,
    'redirect_uri' => OAUTH_REDIRECT_URI,
    'code' => $code
];

$ch = curl_init(OAUTH_TOKEN_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) die('トークン取得エラー');

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) die('アクセストークンがありません');

$accessToken = $tokenData['access_token'];

$ch = curl_init(OAUTH_API_BASE . '/user');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Accept: application/json']);

$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) die('ユーザー情報取得エラー');

$userData = json_decode($userResponse, true);
if (!$userData || !isset($userData['id'])) die('ユーザー情報の取得に失敗');

$user = get_or_create_user('p2pear', $userData['id'], $userData);

$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['avatar'] = $user['avatar_url'];
$_SESSION['access_token'] = $accessToken;

header('Location: dashboard.php');
exit;
