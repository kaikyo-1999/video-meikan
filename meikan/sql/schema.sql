-- video-meikan Database Schema
CREATE DATABASE IF NOT EXISTS video_meikan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE video_meikan;

-- 女優テーブル
CREATE TABLE actresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    thumbnail_url VARCHAR(500) DEFAULT NULL,
    debut_date DATE DEFAULT NULL,
    bust SMALLINT UNSIGNED DEFAULT NULL,
    waist SMALLINT UNSIGNED DEFAULT NULL,
    hip SMALLINT UNSIGNED DEFAULT NULL,
    height SMALLINT UNSIGNED DEFAULT NULL,
    birthday DATE DEFAULT NULL,
    blood_type VARCHAR(10) DEFAULT NULL,
    hobby VARCHAR(500) DEFAULT NULL,
    prefectures VARCHAR(100) DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ジャンルテーブル
CREATE TABLE genres (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    fanza_genre_id VARCHAR(50) DEFAULT NULL,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 作品テーブル
CREATE TABLE works (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500) DEFAULT NULL,
    release_date DATE DEFAULT NULL,
    label VARCHAR(200) DEFAULT NULL,
    affiliate_url VARCHAR(1000) DEFAULT NULL,
    source ENUM('fanza','mgs') NOT NULL DEFAULT 'fanza',
    source_id VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_source_id (source, source_id),
    INDEX idx_release_date (release_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 女優×作品 中間テーブル
CREATE TABLE actress_work (
    actress_id INT UNSIGNED NOT NULL,
    work_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (actress_id, work_id),
    INDEX idx_work_id (work_id),
    FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE,
    FOREIGN KEY (work_id) REFERENCES works(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 作品×ジャンル 中間テーブル
CREATE TABLE work_genre (
    work_id INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (work_id, genre_id),
    INDEX idx_genre_id (genre_id),
    FOREIGN KEY (work_id) REFERENCES works(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
