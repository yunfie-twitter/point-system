<?php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 通知を既読にする
$mark_as_read = isset($_GET['read']) ? intval($_GET['read']) : 0;
if ($mark_as_read > 0) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("is", $mark_as_read, $user_id);
    $stmt->execute();
    header('Location: notifications.php');
    exit;
}

// すべて既読にする
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    header('Location: notifications.php');
    exit;
}

// 通知削除
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("is", $delete_id, $user_id);
    $stmt->execute();
    header('Location: notifications.php');
    exit;
}

// ページネーション
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 通知取得
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("sii", $user_id, $per_page, $offset);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 総件数
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$total_count = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_count / $per_page);

// 未読件数
$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread'];

$page_title = '通知';
include 'header.php';
?>

<div class="notifications-page">
    <div class="page-header">
        <h2>通知</h2>
        <div class="header-actions">
            <?php if ($unread_count > 0): ?>
                <span class="unread-count"><?php echo $unread_count; ?>件の未読</span>
                <form method="post" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="btn-secondary">すべて既読にする</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="no-notifications">
            <div class="icon">🔔</div>
            <p>通知はありません</p>
        </div>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="notification-icon">
                        <?php
                        $icon = '🔔';
                        if (strpos($notification['type'], 'point') !== false) $icon = '⭐';
                        if (strpos($notification['type'], 'rank') !== false) $icon = '🏆';
                        if (strpos($notification['type'], 'exchange') !== false) $icon = '🎁';
                        if (strpos($notification['type'], 'bonus') !== false) $icon = '🎉';
                        echo $icon;
                        ?>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="notification-message"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></div>
                        <div class="notification-time"><?php echo timeAgo($notification['created_at']); ?></div>
                    </div>
                    <div class="notification-actions">
                        <?php if (!$notification['is_read']): ?>
                            <a href="?read=<?php echo $notification['id']; ?>" class="btn-mark-read" title="既読にする">✓</a>
                        <?php endif; ?>
                        <a href="?delete=<?php echo $notification['id']; ?>" class="btn-delete" onclick="return confirm('この通知を削除しますか？');" title="削除">×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="btn-page">&laquo; 前へ</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="btn-page <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="btn-page">次へ &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.notifications-page {
    padding: 20px 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}

.unread-count {
    background: #dc3545;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.no-notifications {
    text-align: center;
    padding: 80px 20px;
    background: #fff;
    border-radius: 8px;
    color: #999;
}

.no-notifications .icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification-item {
    display: flex;
    gap: 15px;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    border-left: 4px solid #ddd;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.notification-item.unread {
    background: #f8f9fa;
    border-left-color: #007bff;
}

.notification-item:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.notification-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 16px;
}

.notification-message {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 10px;
}

.notification-time {
    font-size: 12px;
    color: #999;
}

.notification-actions {
    display: flex;
    gap: 5px;
    align-items: flex-start;
}

.btn-mark-read,
.btn-delete {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    text-decoration: none;
    font-size: 18px;
    transition: all 0.2s ease;
}

.btn-mark-read {
    background: #28a745;
    color: white;
}

.btn-mark-read:hover {
    background: #218838;
}

.btn-delete {
    background: #dc3545;
    color: white;
}

.btn-delete:hover {
    background: #c82333;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 30px;
}

.btn-page {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
}

.btn-page:hover {
    background: #f8f9fa;
}

.btn-page.active {
    background: #007bff;
    color: #fff;
    border-color: #007bff;
}
</style>

<?php 
// 時間表示ヘルパー関数
function timeAgo($datetime) {
    $now = time();
    $timestamp = strtotime($datetime);
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'ただ今';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '時間前';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . '日前';
    } else {
        return date('Y/m/d H:i', $timestamp);
    }
}

include 'footer.php'; 
?>
