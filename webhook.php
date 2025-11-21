<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// トークン検証
$token = $_GET['token'] ?? null;

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Token required']);
    exit;
}

$auth = verify_webhook_token($token);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$user_id = $auth['user_id'];
$event_id = $auth['event_id'];

// Webhookペイロード取得
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

debug_log('Webhook received', [
    'user_id' => $user_id,
    'event_id' => $event_id,
    'data' => $data
]);

// Misskeyのnote情報抽出
$note_id = $data['body']['note']['id'] ?? null;
$note_url = $data['body']['note']['url'] ?? null;
$note_text = $data['body']['note']['text'] ?? '';

if (!$note_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Note ID not found']);
    exit;
}

// 重複チェック
$db = get_db();
$stmt = $db->prepare(
    "SELECT id FROM misskey_post_logs WHERE note_id = ?"
);
$stmt->execute([$note_id]);

if ($stmt->fetch()) {
    echo json_encode(['message' => 'Already processed', 'status' => 'duplicate']);
    exit;
}

// ハッシュタグ抽出
preg_match_all('/#([a-zA-Z0-9_\x{3000}-\x{303F}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]+)/u', $note_text, $matches);
$hashtags = $matches[1] ?? [];

// ポイント付与
$result = execute_event($user_id, $event_id, [
    'note_id' => $note_id,
    'note_url' => $note_url,
    'hashtags' => $hashtags
]);

if (!$result['ok']) {
    http_response_code(429);
    echo json_encode(['error' => $result['reason']]);
    exit;
}

// ログ保存
$stmt = $db->prepare(
    "INSERT INTO misskey_post_logs (user_id, event_id, note_id, note_url, hashtags_json)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->execute([
    $user_id,
    $event_id,
    $note_id,
    $note_url,
    json_encode($hashtags)
]);

// ランク更新
update_user_rank($user_id);

echo json_encode([
    'success' => true,
    'points_awarded' => $result['points'],
    'message' => 'ポイントを付与しました'
]);
