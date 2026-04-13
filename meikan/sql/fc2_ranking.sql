-- FC2ランキング: DBマイグレーション

CREATE TABLE IF NOT EXISTS fc2_works (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    cid           VARCHAR(20)   NOT NULL UNIQUE,
    title         VARCHAR(500)  NOT NULL,
    thumbnail_url VARCHAR(1000) DEFAULT NULL,
    price         INT           DEFAULT 0,
    duration      INT           DEFAULT NULL COMMENT '再生時間（分）',
    affiliate_url VARCHAR(1000) DEFAULT NULL,
    is_approved   TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'ユーザー投稿は0で保留',
    submitted_ip  VARCHAR(45)   DEFAULT NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fc2_votes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    fc2_work_id INT          NOT NULL,
    voter_ip    VARCHAR(45)  NOT NULL,
    voted_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote (fc2_work_id, voter_ip),
    CONSTRAINT fk_fc2_vote_work FOREIGN KEY (fc2_work_id) REFERENCES fc2_works(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
