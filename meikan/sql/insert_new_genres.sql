-- Phase 1: S+Aランク 新ジャンル7つを登録
INSERT IGNORE INTO genres (name, slug) VALUES
('フェラ・イラマチオ', 'fera'),
('乳首・乳首責め', 'chikubi'),
('潮吹き', 'shiofuki'),
('顔射・ぶっかけ', 'gansha'),
('手コキ・足コキ', 'tekoki'),
('主観（POV）', 'shukanpov'),
('VR', 'vr');

-- genre_fanza_mapping にFANZA genre_idを紐付け
-- フェラ・イラマチオ: 5002(フェラ), 5068(イラマチオ)
INSERT IGNORE INTO genre_fanza_mapping (genre_id, fanza_genre_id)
SELECT id, '5002' FROM genres WHERE slug = 'fera'
UNION ALL
SELECT id, '5068' FROM genres WHERE slug = 'fera';

-- 潮吹き: 5016
INSERT IGNORE INTO genre_fanza_mapping (genre_id, fanza_genre_id)
SELECT id, '5016' FROM genres WHERE slug = 'shiofuki';

-- 顔射・ぶっかけ: 5023(顔射), 5003(ぶっかけ)
INSERT IGNORE INTO genre_fanza_mapping (genre_id, fanza_genre_id)
SELECT id, '5023' FROM genres WHERE slug = 'gansha'
UNION ALL
SELECT id, '5003' FROM genres WHERE slug = 'gansha';

-- 手コキ・足コキ: 5004(手コキ), 5048(足コキ)
INSERT IGNORE INTO genre_fanza_mapping (genre_id, fanza_genre_id)
SELECT id, '5004' FROM genres WHERE slug = 'tekoki'
UNION ALL
SELECT id, '5048' FROM genres WHERE slug = 'tekoki';

-- 主観（POV）: 5063
INSERT IGNORE INTO genre_fanza_mapping (genre_id, fanza_genre_id)
SELECT id, '5063' FROM genres WHERE slug = 'shukanpov';

-- VR, 乳首: FANZA genre_idなし（タイトルベースで紐付け）
