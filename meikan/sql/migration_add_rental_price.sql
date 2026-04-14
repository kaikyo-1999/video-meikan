-- レンタル価格カラムを追加
ALTER TABLE works
    ADD COLUMN rental_price INT UNSIGNED DEFAULT NULL COMMENT 'レンタル最安値・現在価格（円）' AFTER campaign_title,
    ADD COLUMN rental_list_price INT UNSIGNED DEFAULT NULL COMMENT 'レンタル最安値・定価（円）' AFTER rental_price;
