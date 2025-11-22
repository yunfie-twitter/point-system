<?php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

// イベント管理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_event'])) {
        $event_key = trim($_POST['event_key']);
        $event_type = trim($_POST['event_type']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $points = intval($_POST['points']);
        $cooldown = intval($_POST['cooldown_seconds']);
        $daily_limit = intval($_POST['daily_limit']);
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO point_events (event_key, event_type, name, description, points, cooldown_seconds, daily_limit, enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiiii", $event_key, $event_type, $name, $description, $points, $cooldown, $daily_limit, $enabled);
        
        if ($stmt->execute()) {
            $success_message = 'イベントを追加しました。';
        } else {
            $error_message = 'イベントの追加に失敗しました。';
        }
    }
    
    if (isset($_POST['update_event'])) {
        $event_id = intval($_POST['event_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $points = intval($_POST['points']);
        $cooldown = intval($_POST['cooldown_seconds']);
        $daily_limit = intval($_POST['daily_limit']);
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE point_events SET name = ?, description = ?, points = ?, cooldown_seconds = ?, daily_limit = ?, enabled = ? WHERE id = ?");
        $stmt->bind_param("ssiiii", $name, $description, $points, $cooldown, $daily_limit, $enabled, $event_id);
        
        if ($stmt->execute()) {
            $success_message = 'イベントを更新しました。';
        } else {
            $error_message = 'イベントの更新に失敗しました。';
        }
    }
    
    if (isset($_POST['delete_event'])) {
        $event_id = intval($_POST['event_id']);
        $stmt = $conn->prepare("DELETE FROM point_events WHERE id = ?");
        $stmt->bind_param("i", $event_id);
        
        if ($stmt->execute()) {
            $success_message = 'イベントを削除しました。';
        } else {
            $error_message = 'イベントの削除に失敗しました。';
        }
    }
    
    // 商品管理
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['product_name']);
        $description = trim($_POST['product_description']);
        $points_required = intval($_POST['points_required']);
        $stock = intval($_POST['stock']);
        $enabled = isset($_POST['product_enabled']) ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO exchange_products (name, description, points_required, stock, enabled) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiii", $name, $description, $points_required, $stock, $enabled);
        
        if ($stmt->execute()) {
            $success_message = '商品を追加しました。';
        } else {
            $error_message = '商品の追加に失敗しました。';
        }
    }
    
    // ユーザー管理
    if (isset($_POST['adjust_points'])) {
        $target_user_id = trim($_POST['target_user_id']);
        $points = intval($_POST['adjust_points_value']);
        $reason = trim($_POST['adjust_reason']);
        
        if (addPoints($conn, $target_user_id, $points, $reason, 'normal')) {
            $success_message = 'ポイントを調整しました。';
        } else {
            $error_message = 'ポイントの調整に失敗しました。';
        }
    }
}

// 統計情報
$stats = [];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['total_points_distributed'] = $conn->query("SELECT SUM(points) as total FROM point_history WHERE points > 0")->fetch_assoc()['total'] ?? 0;
$stats['total_exchanges'] = $conn->query("SELECT COUNT(*) as count FROM exchange_history")->fetch_assoc()['count'];
$stats['active_webhooks'] = $conn->query("SELECT COUNT(*) as count FROM webhook_tokens WHERE expires_at > NOW()")->fetch_assoc()['count'];

// イベント一覧
$events = $conn->query("SELECT * FROM point_events ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// 商品一覧
$products = $conn->query("SELECT * FROM exchange_products ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// 最近のアクティビティ
$recent_activities = $conn->query("SELECT ph.*, u.display_name FROM point_history ph LEFT JOIN users u ON ph.user_id = u.user_id ORDER BY ph.created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

$page_title = '管理パネル';
include 'header.php';
?>

<div class="admin-page">
    <h2>管理パネル</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- 統計カード -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
            <div class="stat-label">総ユーザー数</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⭐</div>
            <div class="stat-value"><?php echo number_format($stats['total_points_distributed']); ?></div>
            <div class="stat-label">配布ポイント総数</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎁</div>
            <div class="stat-value"><?php echo number_format($stats['total_exchanges']); ?></div>
            <div class="stat-label">交換回数</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔗</div>
            <div class="stat-value"><?php echo number_format($stats['active_webhooks']); ?></div>
            <div class="stat-label">有効なWebhook</div>
        </div>
    </div>

    <!-- タブ -->
    <div class="admin-tabs">
        <button class="tab-btn active" onclick="switchTab('events')">イベント管理</button>
        <button class="tab-btn" onclick="switchTab('products')">商品管理</button>
        <button class="tab-btn" onclick="switchTab('users')">ユーザー管理</button>
        <button class="tab-btn" onclick="switchTab('activities')">アクティビティ</button>
    </div>

    <!-- イベント管理 -->
    <div id="events-tab" class="tab-content active">
        <h3>イベント一覧</h3>
        <button class="btn-primary" onclick="document.getElementById('add-event-form').style.display='block'">新規イベント追加</button>
        
        <div id="add-event-form" style="display:none;" class="form-modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('add-event-form').style.display='none'">&times;</span>
                <h4>新規イベント追加</h4>
                <form method="post">
                    <input type="text" name="event_key" placeholder="イベントキー (misskey_post_like)" required>
                    <select name="event_type" required>
                        <option value="misskey">Misskey</option>
                        <option value="manual">手動</option>
                    </select>
                    <input type="text" name="name" placeholder="イベント名" required>
                    <textarea name="description" placeholder="説明"></textarea>
                    <input type="number" name="points" placeholder="ポイント" required>
                    <input type="number" name="cooldown_seconds" placeholder="クールダウン(秒)" value="0">
                    <input type="number" name="daily_limit" placeholder="日次制限" value="0">
                    <label><input type="checkbox" name="enabled" checked> 有効化</label>
                    <button type="submit" name="add_event" class="btn-primary">追加</button>
                </form>
            </div>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>イベント名</th>
                    <th>キー</th>
                    <th>ポイント</th>
                    <th>クールダウン</th>
                    <th>制限</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?php echo $event['id']; ?></td>
                        <td><?php echo htmlspecialchars($event['name']); ?></td>
                        <td><code><?php echo htmlspecialchars($event['event_key']); ?></code></td>
                        <td><?php echo $event['points']; ?>pt</td>
                        <td><?php echo $event['cooldown_seconds']; ?>秒</td>
                        <td><?php echo $event['daily_limit']; ?>回/日</td>
                        <td>
                            <span class="badge <?php echo $event['enabled'] ? 'success' : 'secondary'; ?>">
                                <?php echo $event['enabled'] ? '有効' : '無効'; ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                <button type="submit" name="delete_event" class="btn-small btn-danger" onclick="return confirm('削除しますか？');">削除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 商品管理 -->
    <div id="products-tab" class="tab-content">
        <h3>商品一覧</h3>
        <button class="btn-primary" onclick="document.getElementById('add-product-form').style.display='block'">新規商品追加</button>
        
        <div id="add-product-form" style="display:none;" class="form-modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('add-product-form').style.display='none'">&times;</span>
                <h4>新規商品追加</h4>
                <form method="post">
                    <input type="text" name="product_name" placeholder="商品名" required>
                    <textarea name="product_description" placeholder="説明"></textarea>
                    <input type="number" name="points_required" placeholder="必要ポイント" required>
                    <input type="number" name="stock" placeholder="在庫数" value="-1">
                    <label><input type="checkbox" name="product_enabled" checked> 有効化</label>
                    <button type="submit" name="add_product" class="btn-primary">追加</button>
                </form>
            </div>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>商品名</th>
                    <th>必要ポイント</th>
                    <th>在庫</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo number_format($product['points_required']); ?>pt</td>
                        <td><?php echo $product['stock'] < 0 ? '無制限' : $product['stock']; ?></td>
                        <td>
                            <span class="badge <?php echo $product['enabled'] ? 'success' : 'secondary'; ?>">
                                <?php echo $product['enabled'] ? '有効' : '無効'; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ユーザー管理 -->
    <div id="users-tab" class="tab-content">
        <h3>ユーザー管理</h3>
        <div class="admin-card">
            <h4>ポイント調整</h4>
            <form method="post" class="inline-form">
                <input type="text" name="target_user_id" placeholder="ユーザーID" required>
                <input type="number" name="adjust_points_value" placeholder="ポイント(マイナス可)" required>
                <input type="text" name="adjust_reason" placeholder="理由" required>
                <button type="submit" name="adjust_points" class="btn-primary">調整</button>
            </form>
        </div>
    </div>

    <!-- アクティビティ -->
    <div id="activities-tab" class="tab-content">
        <h3>最近のアクティビティ</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>日時</th>
                    <th>ユーザー</th>
                    <th>理由</th>
                    <th>ポイント</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_activities as $activity): ?>
                    <tr>
                        <td><?php echo date('Y/m/d H:i', strtotime($activity['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($activity['display_name'] ?? $activity['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($activity['reason']); ?></td>
                        <td class="<?php echo $activity['points'] > 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $activity['points'] > 0 ? '+' : ''; ?><?php echo number_format($activity['points']); ?>pt
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function switchTab(tabName) {
    // タブボタンのアクティブ状態を切り替え
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // タブコンテンツの表示切り替え
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(tabName + '-tab').classList.add('active');
}
</script>

<style>
.admin-page {
    padding: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-icon {
    font-size: 32px;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    color: #666;
}

.admin-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #ddd;
}

.tab-btn {
    padding: 10px 20px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #666;
    transition: all 0.3s;
}

.tab-btn:hover {
    color: #333;
}

.tab-btn.active {
    color: #007bff;
    border-bottom-color: #007bff;
}

.tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tab-content.active {
    display: block;
}

.admin-table {
    width: 100%;
    margin-top: 20px;
    border-collapse: collapse;
}

.admin-table th,
.admin-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.admin-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.admin-table code {
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.form-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    position: relative;
}

.modal-content .close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 28px;
    cursor: pointer;
}

.modal-content form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.modal-content input,
.modal-content select,
.modal-content textarea {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.admin-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.inline-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.inline-form input {
    flex: 1;
    min-width: 150px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn-small {
    padding: 5px 10px;
    font-size: 12px;
}

.text-success {
    color: #28a745;
    font-weight: bold;
}

.text-danger {
    color: #dc3545;
    font-weight: bold;
}

.badge.secondary {
    background: #6c757d;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
}
</style>

<?php include 'footer.php'; ?>
