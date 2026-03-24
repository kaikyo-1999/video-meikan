# 新ジャンル追加 実装プラン

## 背景

既存30ジャンルは属性・シチュエーション系に偏っており、行為（プレイ）系がほぼ欠落している。
Ahrefsキーワード分析の結果、最大16ジャンルの追加候補を特定。

## 追加ジャンル一覧とデータソース

### FANZA genre_idで紐付け可能（14ジャンル）

fetch_fanza.phpが作品取得時にAPIレスポンスの `iteminfo.genre` を解析しており、
これらのgenre_idが含まれていれば自動的に `work_genre` に紐付く。

| ジャンル名 | slug | fanza_genre_id | ランク |
|-----------|------|---------------|-------|
| フェラ | fera | 5002 | S |
| イラマチオ | irrumatio | 5068 | S（フェラと統合するか分離するか要検討） |
| 潮吹き | shiofuki | 5016 | S |
| 顔射 | gansha | 5023 | A |
| ぶっかけ | bukkake | 5003 | A（顔射と統合するか分離するか要検討） |
| 手コキ | tekoki | 5004 | A |
| 足コキ | ashikoki | 5048 | A（手コキと統合するか分離するか要検討） |
| 主観 | shukanpov | 5063 | A |
| レズ |lez | 4040 | B |
| 拘束・緊縛 | kousoku | 25, 5021 | B（2つのgenre_idに対応） |
| 騎乗位 | kijyoui | 4106 | B |
| メイド | maid | 1008 | B |
| ごっくん | gokkun | 5009 | C |
| パイズリ | paizuri | 5019 | C |
| ソープ | soap | 6937 | C |

### タイトルベースで紐付けが必要（2ジャンル）

FANZA genre_idが存在しない or 不十分なため、タイトル文字列から判定。

| ジャンル名 | slug | 判定方法 | ランク |
|-----------|------|---------|-------|
| VR | vr | タイトルに `【VR】` を含む | A |
| マジックミラー号 | magicmirror | タイトルに `マジックミラー` を含む | C |

※ `制服・JK` は既存の `女子校生`（genre_id: 1018）とFANZA上のgenre_id: 48が重複するため、
  統合方針を別途検討。今回のスコープからは除外。

## 統合 or 分離の方針

検索需要の観点から以下を推奨：

| 候補 | 推奨 | 理由 |
|------|------|------|
| フェラ + イラマチオ | **統合**（フェラ・イラマチオ） | 検索需要でセットで検索される。genre_id 2つ紐付け |
| 顔射 + ぶっかけ | **統合**（顔射・ぶっかけ） | 同上。genre_id 2つ紐付け |
| 手コキ + 足コキ | **統合**（手コキ・足コキ） | 同上。genre_id 2つ紐付け |
| 拘束 + 緊縛 | **統合**（拘束・緊縛） | 同上。genre_id 2つ紐付け |

統合する場合、1つのジャンルレコードに対して複数のfanza_genre_idを持つ必要がある。

## 実装方針

### Step 1: genres テーブル拡張

現在 `fanza_genre_id` は1つしか持てない（VARCHAR単一値）。
統合ジャンル対応のため、以下のいずれかで対応：

**案A: genre_fanza_id 中間テーブルを作る（推奨）**
```sql
CREATE TABLE genre_fanza_mapping (
    genre_id INT UNSIGNED NOT NULL,
    fanza_genre_id VARCHAR(50) NOT NULL,
    PRIMARY KEY (genre_id, fanza_genre_id),
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);
```
- 既存の `genres.fanza_genre_id` からデータ移行
- fetch_fanza.phpのジャンルマッチングロジックを変更

**案B: fanza_genre_id をカンマ区切りで保存**
```sql
-- 既存カラムを流用
UPDATE genres SET fanza_genre_id = '5002,5068' WHERE slug = 'fera';
```
- スキーマ変更不要だがSQL検索が `FIND_IN_SET` になり汚い

→ **案Aを推奨**（正規化されていて拡張しやすい）

### Step 2: タイトルベース判定用のバッチ作成

VR、マジックミラー号など、FANZA genre_idでカバーできないジャンルのために
タイトル文字列から `work_genre` に紐付けるバッチを作成。

```
batch/assign_title_genres.php
```

| ジャンル | マッチ条件 |
|---------|----------|
| VR | `title LIKE '%【VR】%'` |
| マジックミラー号 | `title LIKE '%マジックミラー%'` |

### Step 3: 新ジャンルをDBに登録

`batch/register_genres.php`（または既存のimport系バッチを拡張）で
新ジャンルレコードをINSERT + genre_fanza_mappingに紐付け。

### Step 4: 既存作品への紐付け

新ジャンル追加後、既存作品との紐付けが必要：

1. **FANZA genre_id ベースの紐付け**: fetch_fanza.phpを再実行すれば、
   APIレスポンスに含まれるgenre_idで自動紐付けされる
2. **タイトルベースの紐付け**: assign_title_genres.php を実行

### Step 5: 薄いコンテンツ制御

女優×ジャンルページで作品数が少ない場合の制御：

- **作品2本以下**: ページを生成しない（ActressControllerのジャンル一覧から除外）
- **既存noindex基準は維持**: ジャンル2つ以上の女優のみindex

実装箇所: `Actress::getGenres()` のSQLに `HAVING work_count >= 3` を追加

### Step 6: run_all.php の実行順序更新

```
1. register_actresses.php
2. fetch_actress_profiles.php
3. register_genres.php        ← 新規
4. fetch_fanza.php
5. assign_title_genres.php    ← 新規
6. clear_cache.php
```

## 対象ファイル

| ファイル | 変更種別 |
|---------|--------|
| `meikan/sql/migration_genre_fanza_mapping.sql` | **新規** — 中間テーブル作成 + データ移行 |
| `meikan/sql/insert_new_genres.sql` | **新規** — 新ジャンルINSERT |
| `meikan/batch/register_genres.php` | **新規** — ジャンル登録バッチ |
| `meikan/batch/assign_title_genres.php` | **新規** — タイトルベース紐付けバッチ |
| `meikan/batch/fetch_fanza.php` | 編集 — genre_fanza_mapping対応 |
| `meikan/batch/run_all.php` | 編集 — 実行順序にバッチ追加 |
| `meikan/src/models/Actress.php` | 編集 — getGenresにwork_count >= 3フィルター |
| `meikan/src/models/Genre.php` | 編集 — findBySlugのgenre_id解決を中間テーブル対応 |

## 実装順序

### Phase 1: S+Aランク（7ジャンル）
1. genre_fanza_mapping テーブル作成 + 既存データ移行
2. 新ジャンル7つを登録（フェラ・イラマチオ / 乳首 / 潮吹き / 顔射・ぶっかけ / 手コキ・足コキ / 主観 / VR）
3. fetch_fanza.php のジャンルマッチングを中間テーブル対応に変更
4. assign_title_genres.php を作成（VR用）
5. 本番で fetch_fanza.php + assign_title_genres.php 実行
6. 薄いコンテンツ制御（work_count >= 3）
7. キャッシュクリア

### Phase 2: Bランク（5ジャンル）
レズ / 拘束・緊縛 / 騎乗位 / 制服・JK / メイド

### Phase 3: Cランク（4ジャンル）
ごっくん / パイズリ / ソープ / マジックミラー号

## 未決事項

- [ ] 「乳首・乳首責め」のFANZA genre_id — GenreSearchの結果に見当たらない。タイトルベース判定が必要かも
- [ ] 「制服・JK」と既存「女子校生」の統合方針
- [ ] 統合ジャンルの最終確認（フェラ+イラマ等、本当に統合でよいか）
- [ ] 各ジャンルのSEO用metaDescription テンプレート
