-- worksテーブルにレビュー情報カラムを追加
ALTER TABLE works
    ADD COLUMN review_count INT UNSIGNED DEFAULT NULL AFTER affiliate_url,
    ADD COLUMN review_average DECIMAL(3,2) DEFAULT NULL AFTER review_count;
