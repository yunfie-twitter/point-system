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
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// フィルター処理
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// 基本SQLクエリ
$where_clauses = ["user_id = ?"];
$params = [$user_id];
$param_types = "s";

if ($filter_type !== 'all') {
    if ($filter_type === 'earned') {
        $where_clauses[] = "points > 0";
    } elseif ($filter_type === 'spent') {
        $where_clauses[] = "points < 0";
    } elseif ($filter_type === 'expired') {
        $where_clauses[] = "reason LIKE '%有効期限切れ%'";
    }
}

if (!empty($filter_date)) {
    $where_clauses[] = "DATE(created_at) = ?";
    $params[] = $filter_date;
    $param_types .= "s";
}

$where_sql = implode(' AND ', $where_clauses);

// 履歴取得
$sql = "SELECT * FROM point_history WHERE $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 総件数取得
$count_sql = "SELECT COUNT(*) as total FROM point_history WHERE $where_sql";
$stmt = $conn->prepare($count_sql);
array_pop($params); // offset
array_pop($params); // limit
$param_types = substr($param_types, 0, -2);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_count = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_count / $per_page);

// ユーザー情報取得
$user = getUserById($conn, $user_id);

$page_title = 'ポイント履歴';
include 'header.php';
?>

<div class="history-page">
    <div class="page-header">
        <h2>ポイント履歴</h2>
        <div class="user-points-summary">
            <div class="points-box">
                <span class="label">通常ポイント:</span>
                <span class="value"><?php echo number_format($user['points']); ?>pt</span>
            </div>
            <div class="points-box bonus">
                <span class="label">ボーナスポイント:</span>
                <span class="value"><?php echo number_format($user['bonus_points']); ?>pt</span>
            </div>
        </div>
    </div>

    <div class="filter-section">
        <form method="get" action="" class="filter-form">
            <div class="filter-group">
                <label for="type">種類:</label>
                <select name="type" id="type">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>すべて</option>
                    <option value="earned" <?php echo $filter_type === 'earned' ? 'selected' : ''; ?>>獲得</option>
                    <option value="spent" <?php echo $filter_type === 'spent' ? 'selected' : ''; ?>>使用</option>
                    <option value="expired" <?php echo $filter_type === 'expired' ? 'selected' : ''; ?>>失効</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="date">日付:</label>
                <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($filter_date); ?>">
            </div>
            <button type="submit" class="btn-primary">フィルター</button>
            <a href="history.php" class="btn-secondary">クリア</a>
        </form>
    </div>

    <?php if (empty($history)): ?>
        <div class="no-data">
            <p>履歴がありません。</p>
        </div>
    <?php else: ?>
        <div class="history-list">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>日時</th>
                        <th>理由</th>
                        <th>ポイント</th>
                        <th>種類</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                        <tr class="<?php echo $item['points'] > 0 ? 'earned' : 'spent'; ?>">
                            <td><?php echo date('Y/m/d H:i', strtotime($item['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($item['reason']); ?></td>
                            <td class="points <?php echo $item['points'] > 0 ? 'plus' : 'minus'; ?>">
                                <?php echo $item['points'] > 0 ? '+' : ''; ?><?php echo number_format($item['points']); ?>pt
                            </td>
                            <td>
                                <span class="badge <?php echo $item['point_type']; ?>">
                                    <?php echo $item['point_type'] === 'bonus' ? 'ボーナス' : '通常'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $filter_type; ?>&date=<?php echo $filter_date; ?>" class="btn-page">&laquo; 前へ</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&type=<?php echo $filter_type; ?>&date=<?php echo $filter_date; ?>" 
                       class="btn-page <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $filter_type; ?>&date=<?php echo $filter_date; ?>" class="btn-page">次へ &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.history-page {
    padding: 20px 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.user-points-summary {
    display: flex;
    gap: 15px;
}

.points-box {
    background: #f5f5f5;
    padding: 10px 20px;
    border-radius: 8px;
}

.points-box.bonus {
    background: #fff3cd;
}

.points-box .label {
    font-size: 12px;
    color: #666;
    margin-right: 5px;
}

.points-box .value {
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

.filter-section {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filter-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.filter-group select,
.filter-group input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.history-table {
    width: 100%;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.history-table thead {
    background: #f8f9fa;
}

.history-table th,
.history-table td {
    padding: 15px;
    text-align: left;
}

.history-table th {
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #dee2e6;
}

.history-table tbody tr {
    border-bottom: 1px solid #dee2e6;
}

.history-table tbody tr:hover {
    background: #f8f9fa;
}

.history-table .points {
    font-weight: bold;
    font-size: 16px;
}

.history-table .points.plus {
    color: #28a745;
}

.history-table .points.minus {
    color: #dc3545;
}

.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.badge.normal {
    background: #e3f2fd;
    color: #1976d2;
}

.badge.bonus {
    background: #fff3cd;
    color: #856404;
}

.no-data {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 8px;
    color: #999;
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

<?php include 'footer.php'; ?>
