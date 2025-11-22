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
$user = getUserById($conn, $user_id);
$success_message = '';
$error_message = '';

// 設定保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_display_name'])) {
        $display_name = trim($_POST['display_name']);
        if (!empty($display_name) && strlen($display_name) <= 50) {
            $stmt = $conn->prepare("UPDATE users SET display_name = ? WHERE user_id = ?");
            $stmt->bind_param("ss", $display_name, $user_id);
            if ($stmt->execute()) {
                $success_message = '表示名を更新しました。';
                $user['display_name'] = $display_name;
            } else {
                $error_message = '更新に失敗しました。';
            }
        } else {
            $error_message = '表示名は1～50文字で入力してください。';
        }
    }
    
    if (isset($_POST['update_email'])) {
        $email = trim($_POST['email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->bind_param("ss", $email, $user_id);
            if ($stmt->execute()) {
                $success_message = 'メールアドレスを更新しました。';
                $user['email'] = $email;
            } else {
                $error_message = '更新に失敗しましぞ。';
            }
        } else {
            $error_message = '有効なメールアドレスを入力してください。';
        }
    }
    
    if (isset($_POST['update_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $point_notifications = isset($_POST['point_notifications']) ? 1 : 0;
        $rank_notifications = isset($_POST['rank_notifications']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE user_settings SET email_notifications = ?, point_notifications = ?, rank_notifications = ? WHERE user_id = ?");
        $stmt->bind_param("iiis", $email_notifications, $point_notifications, $rank_notifications, $user_id);
        
        if ($stmt->execute()) {
            $success_message = '通知設定を更新しました。';
        } else {
            // user_settingsテーブルがない場合は作成
            $stmt = $conn->prepare("INSERT INTO user_settings (user_id, email_notifications, point_notifications, rank_notifications) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siii", $user_id, $email_notifications, $point_notifications, $rank_notifications);
            if ($stmt->execute()) {
                $success_message = '通知設定を保存しました。';
            } else {
                $error_message = '保存に失敗しました。';
            }
        }
    }
    
    if (isset($_POST['delete_account'])) {
        $confirm = trim($_POST['confirm_delete']);
        if ($confirm === 'DELETE') {
            // アカウント削除処理
            $conn->begin_transaction();
            try {
                // 関連データ削除
                $stmt = $conn->prepare("DELETE FROM point_history WHERE user_id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                
                $stmt = $conn->prepare("DELETE FROM webhook_tokens WHERE user_id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                
                $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                
                $stmt = $conn->prepare("DELETE FROM user_settings WHERE user_id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                
                $conn->commit();
                session_destroy();
                header('Location: index.php?deleted=1');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'アカウント削除に失敗しました。';
            }
        } else {
            $error_message = '削除には "DELETE" と入力してください。';
        }
    }
}

// 通知設定取得
$stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$settings_result = $stmt->get_result()->fetch_assoc();

if (!$settings_result) {
    // デフォルト設定
    $settings_result = [
        'email_notifications' => 1,
        'point_notifications' => 1,
        'rank_notifications' => 1
    ];
}

$page_title = '設定';
include 'header.php';
?>

<div class="settings-page">
    <h2>アカウント設定</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="settings-section">
        <h3>基本情報</h3>
        <div class="settings-card">
            <form method="post" class="settings-form">
                <div class="form-group">
                    <label for="user_id">ユーザーID</label>
                    <input type="text" id="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>" disabled>
                    <small>ユーザーIDは変更できません。</small>
                </div>
                
                <div class="form-group">
                    <label for="display_name">表示名</label>
                    <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>" maxlength="50">
                </div>
                
                <button type="submit" name="update_display_name" class="btn-primary">表示名を更新</button>
            </form>
        </div>
    </div>

    <div class="settings-section">
        <h3>メールアドレス</h3>
        <div class="settings-card">
            <form method="post" class="settings-form">
                <div class="form-group">
                    <label for="email">メールアドレス</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                    <small>重要な通知の受信に使用されます。</small>
                </div>
                
                <button type="submit" name="update_email" class="btn-primary">メールアドレスを更新</button>
            </form>
        </div>
    </div>

    <div class="settings-section">
        <h3>通知設定</h3>
        <div class="settings-card">
            <form method="post" class="settings-form">
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="email_notifications" <?php echo $settings_result['email_notifications'] ? 'checked' : ''; ?>>
                        メール通知を受け取る
                    </label>
                </div>
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="point_notifications" <?php echo $settings_result['point_notifications'] ? 'checked' : ''; ?>>
                        ポイント獣得時に通知を受け取る
                    </label>
                </div>
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="rank_notifications" <?php echo $settings_result['rank_notifications'] ? 'checked' : ''; ?>>
                        ランク変動時に通知を受け取る
                    </label>
                </div>
                
                <button type="submit" name="update_notifications" class="btn-primary">通知設定を保存</button>
            </form>
        </div>
    </div>

    <div class="settings-section danger-zone">
        <h3>危険な操作</h3>
        <div class="settings-card">
            <div class="warning-box">
                <strong>⚠️ 警告</strong>
                <p>アカウントを削除すると、すべてのデータが完全に削除され、復元できません。</p>
            </div>
            
            <form method="post" class="settings-form" onsubmit="return confirm('本当にアカウントを削除しますか？この操作は元に戻せません。');">
                <div class="form-group">
                    <label for="confirm_delete">削除するには "DELETE" と入力してください</label>
                    <input type="text" id="confirm_delete" name="confirm_delete" placeholder="DELETE">
                </div>
                
                <button type="submit" name="delete_account" class="btn-danger">アカウントを削除</button>
            </form>
        </div>
    </div>
</div>

<style>
.settings-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.settings-page h2 {
    margin-bottom: 30px;
}

.settings-section {
    margin-bottom: 40px;
}

.settings-section h3 {
    margin-bottom: 15px;
    color: #333;
}

.settings-card {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.settings-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #333;
}

.form-group input[type="text"],
.form-group input[type="email"] {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input:disabled {
    background: #f5f5f5;
    cursor: not-allowed;
}

.form-group small {
    color: #666;
    font-size: 12px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    padding: 10px 0;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: normal;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.alert {
    padding: 15px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.danger-zone .settings-card {
    border: 2px solid #dc3545;
}

.warning-box {
    background: #fff3cd;
    border: 1px solid #ffc107;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.warning-box strong {
    display: block;
    margin-bottom: 8px;
    color: #856404;
}

.warning-box p {
    margin: 0;
    color: #856404;
}

.btn-danger {
    background: #dc3545;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.btn-danger:hover {
    background: #c82333;
}
</style>

<?php include 'footer.php'; ?>
