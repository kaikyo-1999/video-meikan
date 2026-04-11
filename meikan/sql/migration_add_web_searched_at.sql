-- Web検索補填バッチ用：試行済みフラグカラムを追加
ALTER TABLE actresses
    ADD COLUMN web_searched_at DATETIME DEFAULT NULL AFTER prefectures;
