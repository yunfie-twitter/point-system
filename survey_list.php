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

$page_title = 'アンケート一覧';
include 'header.php';
?>

<div class="survey-list-page">
    <h2>アンケート一覧</h2>
    <div class="no-surveys">
        <p>現在利用可能なアンケートはありません。</p>
        <p>アンケート機能を使用するには、管理者がアンケートを作成する必要があります。</p>
    </div>
</div>

<style>
.survey-list-page {
    padding: 20px 0;
}

.no-surveys {
    text-align: center;
    padding: 80px 20px;
    background: #fff;
    border-radius: 8px;
    color: #999;
}
</style>

<?php include 'footer.php'; ?>
