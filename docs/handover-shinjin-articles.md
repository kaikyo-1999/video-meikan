# 引継書: 新人AV女優デビュー記事プロジェクト

**作成日**: 2026-03-29
**引継元**: Claude Code セッション
**プロジェクト概要**: 「新人av女優」「avデビュー」関連キーワード（月間検索Vol最大19,000）を狙った月別・条件別デビュー記事群の作成

---

## 完了済み作業

### Phase 1: DB基盤整備 ✅

- `meikan/sql/migration_add_debut_date.sql` を作成・実行済み
  - `actresses` テーブルに `debut_date DATE` カラム追加
  - `idx_debut_date` インデックス作成済み
- `meikan/batch/calculate_debut_dates.php` を新規作成
  - `MIN(works.release_date)` でデビュー日を算出・DB更新
  - `meikan/batch/run_all.php` の実行順序に追加済み（`assign_title_genres.php` の後）
- `meikan/src/models/Actress.php` に3メソッドを追加
  - `findByDebutMonth(string $yearMonth)`: 指定月デビュー女優一覧
  - `findRecentDebuts(int $months = 6)`: 直近N ヶ月デビュー女優
  - `findRecentDebutsByGenre(string $genreSlug, int $months = 6)`: ジャンル条件付き

### Phase 2: 新人女優インポート ✅

- `meikan/batch/data/newcomers_2025_2026.json`: 117名の女優名リスト（2025-09〜2026-03）
- `meikan/batch/data/newcomers_kanji_fix.json`: 漢字名のslugs手動割り当てリスト（46名）
- バッチ実行完了（register → fetch_actress_profiles → fetch_fanza → assign_title_genres → calculate_debut_dates → calculate_similar_actresses → clear_cache）
- **DB状態**: 女優総数 217名、debut_date設定済み 211名

### Phase 3: スキル作成 ✅

- `.claude/skills/create-debut-article/SKILL.md` を新規作成
  - モードA（月別）・モードB（条件別）の2モード
  - DB照会コマンド、記事テンプレート、画像URL仕様、文体ルールを記載

### Phase 4-A: 月別記事 ✅ 全7本完成

| ファイル | 対象月 | 女優数 | 行数 |
|---------|--------|--------|------|
| `shinjin-av-2025-09.md` | 2025年9月 | 11名 | 364行 |
| `shinjin-av-2025-10.md` | 2025年10月 | 19名 | 576行 |
| `shinjin-av-2025-11.md` | 2025年11月 | 13名 | 420行 |
| `shinjin-av-2025-12.md` | 2025年12月 | 9名 | 308行 |
| `shinjin-av-2026-01.md` | 2026年1月 | 27名 | 534行 |
| `shinjin-av-2026-02.md` | 2026年2月 | 14名 | 431行 |
| `shinjin-av-2026-03.md` | 2026年3月 | 9名 | 286行 |

すべて `meikan/content/articles/` に配置済み。

### Phase 4-B: 条件別記事 ✅ 全10本完成

| slug | 条件 | 検索Vol | 状態 | 女優数 |
|------|------|--------|------|-------|
| `shinjin-av-bakunyu` | 爆乳（Fカップ以上） | 4,400 | ✅ | 10名（渡部ほの Jカップ〜香川あんず Fカップ） |
| `shinjin-av-minimum` | 小柄・ミニマム（155cm以下） | 1,600 | ✅ | 7名（全員155cm以下確認済み） |
| `shinjin-av-18sai` | 18〜19歳デビュー | 1,200 | ✅ | 5名（S1 SDAB中心、条件を「18〜19歳」に拡大） |
| `shinjin-av-geinou` | 芸能人出身 | 1,400 | ✅ | — |
| `shinjin-av-moto-idol` | 元アイドル | 1,300 | ✅ | — |
| `shinjin-av-influencer` | インフルエンサー | 800 | ✅ | — |
| `shinjin-av-c-cup` | Cカップ | 800 | ✅ | 5名（清野咲・谷村凪咲・早瀬すみれ・青坂あおい・當真さら） |
| `shinjin-av-bijiri` | 美尻 | 600 | ✅ | 6名（浦野愛羽・七瀬栞・芦田希空・福田ゆあ・若葉結希・最上もあ） |
| `shinjin-av-joshidaisei` | 女子大生 | 100 | ✅ | 8名 |
| `shinjin-av-l-cup` | Lカップ | 500 | ✅ | 4名（2022〜2026年に範囲拡大、DB外女優を手動調査） |

**Lカップ記事の注意点**: DB（2025-09〜2026-03）にLカップ新人はゼロのため2022年まで遡って掲載。@actress[]埋め込みなし。

---

## 未完了作業

### Git コミット未実施 ⚠️

以下がすべて未コミット：

```
M  meikan/batch/run_all.php
M  meikan/src/models/Actress.php
?? .claude/skills/create-debut-article/
?? meikan/batch/calculate_debut_dates.php
?? meikan/content/articles/（全17本）
?? meikan/sql/migration_add_debut_date.sql
```

条件別記事の内容確認後、まとめてコミット → ユーザーがローカル確認 → デプロイ。

### デプロイ未実施 ⚠️

**オーナー確認が完了するまでデプロイ禁止**。ローカル確認の手順：

```bash
php dev-server.php
# ブラウザで /article/shinjin-av-2025-09/ 等を確認
```

---

## 記事作成の続き方

すべての記事は完成済み。追加で条件別記事を作成するには、Claude Code で以下を実行：

```
/create-debut-article
```

または直接指示：

```
新人記事（条件別）を作って。条件: {条件名}
```

---

## ファイル構成

```
meikan/
├── content/articles/
│   ├── shinjin-av-2025-09.md  ✅
│   ├── shinjin-av-2025-10.md  ✅
│   ├── shinjin-av-2025-11.md  ✅
│   ├── shinjin-av-2025-12.md  ✅
│   ├── shinjin-av-2026-01.md  ✅
│   ├── shinjin-av-2026-02.md  ✅
│   ├── shinjin-av-2026-03.md  ✅
│   ├── shinjin-av-bakunyu.md  ✅
│   ├── shinjin-av-minimum.md  ✅
│   ├── shinjin-av-18sai.md    ✅
│   ├── shinjin-av-geinou.md   ✅
│   ├── shinjin-av-moto-idol.md ✅
│   ├── shinjin-av-influencer.md ✅
│   ├── shinjin-av-c-cup.md    ✅
│   ├── shinjin-av-bijiri.md   ✅
│   ├── shinjin-av-joshidaisei.md ✅
│   └── shinjin-av-l-cup.md    ✅
├── sql/
│   └── migration_add_debut_date.sql  ✅（実行済み）
├── batch/
│   ├── calculate_debut_dates.php     ✅
│   ├── run_all.php                   ✅（更新済み）
│   └── data/
│       ├── newcomers_2025_2026.json  ✅
│       └── newcomers_kanji_fix.json  ✅
└── src/models/
    └── Actress.php                   ✅（更新済み）

.claude/skills/
└── create-debut-article/
    └── SKILL.md                      ✅
```

---

## 注意事項

- **バッチ実行順序**: `run_all.php` に定義された順序を厳守（`register → fetch_profiles → fetch_fanza → assign_genres → calculate_debut_dates → calculate_similar → clear_cache`）
- **デプロイ**: オーナーのローカル確認が完了するまで `git push` および本番デプロイは禁止
- **画像URL**: FANZAサンプル画像は `https://pics.dmm.co.jp/digital/video/{CID}/{CID}jp-{N}.jpg`。h3直下と `:::samples` で異なるN番を使うこと
- **@actress埋め込み**: DBに登録済みの女優は必ず `@actress[slug]` で埋め込む
- **Lカップ記事**: DB外女優のため @actress[] 埋め込みなし。次回バッチ実行後に必要に応じて追記
