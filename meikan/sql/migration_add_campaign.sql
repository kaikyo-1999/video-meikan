-- セールキャンペーン情報カラムを追加
ALTER TABLE works
    ADD COLUMN sale_end_at DATETIME DEFAULT NULL COMMENT 'セール終了日時' AFTER list_price,
    ADD COLUMN campaign_title VARCHAR(200) DEFAULT NULL COMMENT 'キャンペーン名' AFTER sale_end_at;
