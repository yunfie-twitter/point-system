<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

// APIキー認証 (未実装 - 必要に応じて追加)
// $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
// if ($api_key !== API_KEY) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

try {
    switch ($endpoint) {
        case 'user':
            // ユーザー情報取得
            if ($method === 'GET') {
                $user_id = $_GET['user_id'] ?? '';
                if (empty($user_id)) {
                    throw new Exception('user_id is required');
                }
                
                $user = getUserById($conn, $user_id);
                if (!$user) {
                    throw new Exception('User not found');
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $user
                ]);
            }
            break;
            
        case 'points':
            // ポイント情報取得
            if ($method === 'GET') {
                $user_id = $_GET['user_id'] ?? '';
                if (empty($user_id)) {
                    throw new Exception('user_id is required');
                }
                
                $user = getUserById($conn, $user_id);
                if (!$user) {
                    throw new Exception('User not found');
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'points' => $user['points'],
                        'bonus_points' => $user['bonus_points'],
                        'rank' => $user['rank'],
                        'monthly_score' => $user['monthly_score']
                    ]
                ]);
            }
            break;
            
        case 'history':
            // ポイント履歴取得
            if ($method === 'GET') {
                $user_id = $_GET['user_id'] ?? '';
                $limit = intval($_GET['limit'] ?? 20);
                $offset = intval($_GET['offset'] ?? 0);
                
                if (empty($user_id)) {
                    throw new Exception('user_id is required');
                }
                
                $stmt = $conn->prepare("SELECT * FROM point_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $stmt->bind_param("sii", $user_id, $limit, $offset);
                $stmt->execute();
                $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $history
                ]);
            }
            break;
            
        case 'ranking':
            // ランキング取得
            if ($method === 'GET') {
                $limit = intval($_GET['limit'] ?? 10);
                $type = $_GET['type'] ?? 'monthly';
                
                if ($type === 'monthly') {
                    $sql = "SELECT user_id, display_name, monthly_score, rank FROM users WHERE monthly_score > 0 ORDER BY monthly_score DESC LIMIT ?";
                } else {
                    $sql = "SELECT user_id, display_name, points, bonus_points, rank FROM users ORDER BY (points + bonus_points) DESC LIMIT ?";
                }
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                $ranking = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $ranking
                ]);
            }
            break;
            
        case 'events':
            // イベント一覧取得
            if ($method === 'GET') {
                $stmt = $conn->query("SELECT * FROM point_events WHERE enabled = 1 ORDER BY created_at DESC");
                $events = $stmt->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $events
                ]);
            }
            break;
            
        case 'products':
            // 交換商品一覧取得
            if ($method === 'GET') {
                $stmt = $conn->query("SELECT * FROM exchange_products WHERE enabled = 1 AND (stock > 0 OR stock = -1) ORDER BY points_required ASC");
                $products = $stmt->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $products
                ]);
            }
            break;
            
        case 'add_points':
            // ポイント付与 (POSTのみ)
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $user_id = $input['user_id'] ?? '';
                $points = intval($input['points'] ?? 0);
                $reason = $input['reason'] ?? 'API経由';
                $point_type = $input['point_type'] ?? 'normal';
                
                if (empty($user_id) || $points === 0) {
                    throw new Exception('user_id and points are required');
                }
                
                if (addPoints($conn, $user_id, $points, $reason, $point_type)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Points added successfully'
                    ]);
                } else {
                    throw new Exception('Failed to add points');
                }
            } else {
                throw new Exception('POST method required');
            }
            break;
            
        case 'stats':
            // システム統計
            if ($method === 'GET') {
                $stats = [];
                $stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
                $stats['total_points_distributed'] = $conn->query("SELECT SUM(points) as total FROM point_history WHERE points > 0")->fetch_assoc()['total'] ?? 0;
                $stats['total_exchanges'] = $conn->query("SELECT COUNT(*) as count FROM exchange_history")->fetch_assoc()['count'];
                $stats['active_users_today'] = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM point_history WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
                
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
            }
            break;
            
        default:
            throw new Exception('Invalid endpoint');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
