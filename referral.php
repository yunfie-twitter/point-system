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
$success_message = '';
$error_message = '';

// 紹介コード生成
function generateReferralCode($user_id) {
    return strtoupper(substr(md5($user_id . time()), 0, 8));
}

// ユーザーの紹介コード取得または生成
$stmt = $conn->prepare("SELECT referral_code FROM referrals WHERE referrer_user_id = ? LIMIT 1");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    $referral_code = generateReferralCode($user_id);
    $stmt = $conn->prepare("INSERT INTO referrals (referrer_user_id, referred_user_id, referral_code) VALUES (?, '', ?)");
    $stmt->bind_param("ss", $user_id, $referral_code);
    $stmt->execute();
} else {
    $referral_code = $result['referral_code'];
}

// 紹介履歴取得
$stmt = $conn->prepare("
    SELECT r.*, u.display_name 
    FROM referrals r 
    LEFT JOIN users u ON r.referred_user_id = u.user_id 
    WHERE r.referrer_user_id = ? AND r.referred_user_id != ''
    ORDER BY r.created_at DESC
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$referrals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_referrals = count($referrals);
$total_points_earned = array_sum(array_column($referrals, 'points_earned'));

// 紹介コード使用処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_code'])) {
    $code = strtoupper(trim($_POST['referral_code']));
    
    if (!empty($code)) {
        if ($code === $referral_code) {
            $error_message = '自分の紹介コードは使用できません。';
        } else {
            $stmt = $conn->prepare("SELECT referrer_user_id FROM referrals WHERE referral_code = ? AND referred_user_id = ''");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $referrer = $stmt->get_result()->fetch_assoc();
            
            if ($referrer) {
                $stmt = $conn->prepare("SELECT id FROM referrals WHERE referred_user_id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $already_used = $stmt->get_result()->fetch_assoc();
                
                if ($already_used) {
                    $error_message = '既に紹介コードを使用済みです。';
                } else {
                    $conn->begin_transaction();
                    try {
                        $referral_points = 100;
                        addPoints($conn, $referrer['referrer_user_id'], $referral_points, '紹介報酬', 'bonus');
                        
                        $signup_bonus = 50;
                        addPoints($conn, $user_id, $signup_bonus, '紹介コード入力ボーナス', 'bonus');
                        
                        $stmt = $conn->prepare("INSERT INTO referrals (referrer_user_id, referred_user_id, referral_code, points_earned) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("sssi", $referrer['referrer_user_id'], $user_id, $code, $referral_points);
                        $stmt->execute();
                        
                        $conn->commit();
                        $success_message = "紹介コードを使用しました！{$signup_bonus}ポイントを獲得しました。";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_message = '紹介コードの使用に失敗しました。';
                    }
                }
            } else {
                $error_message = '無効な紹介コードです。';
            }
        }
    } else {
        $error_message = '紹介コードを入力してください。';
    }
}

$page_title = '紹介プログラム';
include 'header.php';
?>

<div class="referral-page">
    <h2>紹介プログラム</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="referral-code-section">
        <h3>あなたの紹介コード</h3>
        <div class="code-display">
            <div class="code"><?php echo htmlspecialchars($referral_code); ?></div>
            <button onclick="copyCode()" class="btn-secondary">コピー</button>
        </div>
    </div>

    <div class="stats-section">
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_referrals; ?></div>
            <div class="stat-label">紹介人数</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($total_points_earned); ?></div>
            <div class="stat-label">獲得ポイント</div>
        </div>
    </div>

    <div class="use-code-section">
        <h3>紹介コードを使用</h3>
        <form method="post" class="code-form">
            <input type="text" name="referral_code" placeholder="紹介コード" maxlength="8" required>
            <button type="submit" name="use_code" class="btn-primary">使用</button>
        </form>
    </div>
</div>

<script>
function copyCode() {
    navigator.clipboard.writeText('<?php echo $referral_code; ?>');
    alert('コピーしました！');
}
</script>

<?php include 'footer.php'; ?>
