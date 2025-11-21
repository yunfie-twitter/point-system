<?php
require_once 'config.php';
require_once 'functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ポイントシステム</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
        }
        h1 { color: #333; margin-bottom: 1rem; font-size: 2rem; }
        p { color: #666; margin-bottom: 2rem; line-height: 1.6; }
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .features { margin-top: 2rem; text-align: left; }
        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            color: #666;
        }
        .feature::before {
            content: '✓';
            display: inline-block;
            width: 24px;
            height: 24px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎁 ポイントシステム</h1>
        <p>ポイントを貯めて特典と交換！</p>
        <div class="features">
            <div class="feature">Misskey投稿でポイント</div>
            <div class="feature">アンケートで特典</div>
            <div class="feature">ランキングと報酬</div>
            <div class="feature">ポイント交換</div>
        </div>
        <a href="login.php" class="btn">ログイン / 登録</a>
    </div>
</body>
</html>
