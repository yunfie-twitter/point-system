-- サンプルイベントデータ
-- 初期セットアップ用

-- Misskey投稿イベント
INSERT INTO `point_events` 
(`event_key`, `event_type`, `name`, `description`, `points`, `cooldown_seconds`, `daily_limit`, `enabled`) 
VALUES
('misskey_post_basic', 'misskey', '通常投稿', 'Misskeyで投稿するとポイント獲得', 5, 3600, 10, 1),
('misskey_post_hashtag', 'misskey', 'ハッシュタグ付き投稿', '指定ハッシュタグ付きで投稿', 10, 1800, 5, 1),
('misskey_post_long', 'misskey', '長文投稿', '500文字以上の投稿', 15, 7200, 3, 1),
('misskey_post_media', 'misskey', 'メディア付き投稿', '画像または動画付き投稿', 8, 3600, 5, 1);

-- アンケートイベント
INSERT INTO `point_events` 
(`event_key`, `event_type`, `name`, `description`, `points`, `cooldown_seconds`, `daily_limit`, `enabled`) 
VALUES
('survey_feedback', 'survey', 'フィードバックアンケート', 'サービス改善アンケートに回答', 50, NULL, 1, 1),
('survey_monthly', 'survey', '月次アンケート', '毎月の利用状況アンケート', 30, NULL, 1, 1);

-- 閲覧イベント
INSERT INTO `point_events` 
(`event_key`, `event_type`, `name`, `description`, `points`, `cooldown_seconds`, `daily_limit`, `enabled`) 
VALUES
('view_terms', 'view', '利用規約閲覧', '利用規約を最後まで閲覧', 10, NULL, 1, 1),
('view_tutorial', 'view', 'チュートリアル完了', '初回チュートリアルを完了', 20, NULL, 1, 1);

-- サンプル商品
INSERT INTO `reward_items` 
(`name`, `description`, `cost_points`, `item_type`, `stock`, `is_active`) 
VALUES
('ギフトコード 500円', 'Amazonギフトコード 500円分', 500, 'code', 100, 1),
('ギフトコード 1000円', 'Amazonギフトコード 1000円分', 1000, 'code', 50, 1),
('プレミアムバッジ', 'プロフィールに表示される特別バッジ', 2000, 'other', -1, 1),
('限定ステッカー', 'オリジナルデザインステッカー', 300, 'other', 200, 1);

-- サンプルキャンペーン
INSERT INTO `point_campaigns` 
(`title`, `multiplier`, `start_at`, `end_at`) 
VALUES
('オープン記念キャンペーン', 2.00, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)),
('月末感謝祭', 1.50, DATE_FORMAT(LAST_DAY(NOW()), '%Y-%m-%d 00:00:00'), DATE_FORMAT(LAST_DAY(NOW()), '%Y-%m-%d 23:59:59'));
