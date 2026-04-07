-- セール情報用の価格カラムを追加
ALTER TABLE works
    ADD COLUMN price INT UNSIGNED DEFAULT NULL COMMENT '現在価格（円）' AFTER sample_movie_url,
    ADD COLUMN list_price INT UNSIGNED DEFAULT NULL COMMENT '定価（円）' AFTER price,
    ADD COLUMN price_updated_at DATETIME DEFAULT NULL COMMENT '価格更新日時' AFTER list_price;
