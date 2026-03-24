-- genre_fanza_mapping 中間テーブル作成（1ジャンルに複数のFANZA genre_idを紐付け可能にする）
CREATE TABLE IF NOT EXISTS genre_fanza_mapping (
    genre_id INT UNSIGNED NOT NULL,
    fanza_genre_id VARCHAR(50) NOT NULL,
    PRIMARY KEY (genre_id, fanza_genre_id),
    INDEX idx_fanza_genre_id (fanza_genre_id),
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 既存の genres.fanza_genre_id からデータ移行
INSERT IGNORE INTO genre_fanza_mapping (genre_id, fanza_genre_id)
SELECT id, fanza_genre_id FROM genres WHERE fanza_genre_id IS NOT NULL AND fanza_genre_id != '';
