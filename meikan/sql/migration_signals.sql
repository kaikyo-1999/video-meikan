-- Phase 2: 行動データ集計テーブル (2026-05-04)
-- GA4 / FANZA から日次集計したシグナル値を保存する。
-- TOP の「ホット作品」「PV急上昇女優」セクションのソート用。

-- 作品シグナル
CREATE TABLE IF NOT EXISTS work_signals (
    work_id INT UNSIGNED NOT NULL PRIMARY KEY,
    click_7d INT UNSIGNED NOT NULL DEFAULT 0      COMMENT '直近7日 fanza_click 数',
    click_30d INT UNSIGNED NOT NULL DEFAULT 0     COMMENT '直近30日 fanza_click 数',
    click_prev_7d INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '前週(8〜14日前) fanza_click 数',
    velocity_score DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT '(click_7d - click_prev_7d) / max(click_prev_7d, 1)',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_velocity (velocity_score DESC),
    INDEX idx_click_7d (click_7d DESC),
    FOREIGN KEY (work_id) REFERENCES works(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 女優シグナル
CREATE TABLE IF NOT EXISTS actress_signals (
    actress_id INT UNSIGNED NOT NULL PRIMARY KEY,
    sessions_7d INT UNSIGNED NOT NULL DEFAULT 0       COMMENT '直近7日 sessions 数 (女優ページ)',
    sessions_prev_7d INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '前週(8〜14日前) sessions 数',
    pv_velocity_score DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT '(sessions_7d - sessions_prev_7d) / max(sessions_prev_7d, 1)',
    fav_7d INT UNSIGNED NOT NULL DEFAULT 0      COMMENT '直近7日 favorite_added 数 (Phase 3 で利用)',
    fav_30d INT UNSIGNED NOT NULL DEFAULT 0     COMMENT '直近30日 favorite_added 数',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pv_velocity (pv_velocity_score DESC),
    INDEX idx_sessions_7d (sessions_7d DESC),
    FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
