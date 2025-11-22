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
$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($survey_id === 0) {
    header('Location: survey_list.php');
    exit;
}

// アンケート情報取得 (surveysテーブルが必要)
// 未実装の場合はsetup.phpでテーブル作成が必要

$page_title = 'アンケート';
include 'header.php';
?>

<div class="survey-page">
    <h2>アンケート機能</h2>
    <p>この機能を使用するには、surveysテーブルの作成が必要です。</p>
    <p>setup.phpでデータベースのセットアップを実行してください。</p>
</div>

<?php include 'footer.php'; ?>
