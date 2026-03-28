-- 似ている女優テーブル
-- ジャンル分布のコサイン類似度を事前計算して保存する

CREATE TABLE IF NOT EXISTS similar_actresses (
    actress_id INT UNSIGNED NOT NULL,
    similar_actress_id INT UNSIGNED NOT NULL,
    score DECIMAL(5,4) NOT NULL,
    PRIMARY KEY (actress_id, similar_actress_id),
    INDEX idx_actress_score (actress_id, score DESC),
    FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE,
    FOREIGN KEY (similar_actress_id) REFERENCES actresses(id) ON DELETE CASCADE
);
