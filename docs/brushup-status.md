# 新人AV女優記事ブラッシュアップ 進捗ドキュメント

最終更新: 2026-03-29（2回目更新）

## 作業概要

`meikan/content/articles/shinjin-av-*.md` の全記事を1月記事（shinjin-av-2026-01.md）品質に統一するブラッシュアップ作業。

### 品質基準（目標レベル）
- 見出しレベル: `##`（h2）
- 各女優エントリ: プロフィール画像 + 表 + 本文2〜3段落 + `@actress[slug]` + `**デビュー作品**：` リンク + `:::samples` + `:::say`
- `:::samples` : DMM画像 2枚（`{cid}jp-2.jpg` + `{cid}jp-9.jpg` など）
- `:::say` : av博士キャラ（男性・断定調）、レーベル/スペック/背景を具体的に分析する2〜3文
- 記事冒頭: `:::cast[タイトル]{デビュー日,身長,所属}` ブロック（各女優のサムネイル付き一覧）
- ジャンル記事: `**デビュー作品**：`（`**おすすめ作品**：` は使わない）

---

## 完了タスク ✅

### 月別記事 — h3→h2 見出し変換
| ファイル | 状態 |
|---|---|
| shinjin-av-2025-09.md | ✅ |
| shinjin-av-2025-10.md | ✅ |
| shinjin-av-2025-11.md | ✅ |
| shinjin-av-2025-12.md | ✅ |
| shinjin-av-2026-01.md | ✅ |
| shinjin-av-2026-02.md | ✅ |
| shinjin-av-2026-03.md | ✅ |

### 月別記事 — :::cast ブロック追加
| ファイル | 女優数 | 状態 |
|---|---|---|
| shinjin-av-2025-09.md | 11名 | ✅ |
| shinjin-av-2025-10.md | 19名 | ✅ |
| shinjin-av-2025-11.md | 13名 | ✅ |
| shinjin-av-2025-12.md | 9名 | ✅ |
| shinjin-av-2026-01.md | 27名 | ✅ |
| shinjin-av-2026-02.md | 14名 | ✅ |
| shinjin-av-2026-03.md | 9名 | ✅ |

### 月別記事 — 薄いエントリの補完
| ファイル | 対象女優 | 状態 |
|---|---|---|
| shinjin-av-2026-01.md | 12名スタブ（七瀬栞・凪咲あおい・国仲ありな・月城るな・水川菜月・鳴涙まりる・早瀬すみれ・湊音かれん・月待青花・小泉楓・溝端恋・重盛れいか） | ✅ |
| shinjin-av-2025-10.md | 6名（永野妃華・愛咲ましろ・最上もあ・未春ゆう・杉崎美紗・菅原ゆうり） | ✅ |
| shinjin-av-2025-11.md | 5名（青葉香奈・黒木麻央・桜みお・水樹つくし・永瀬みゆう） | ✅ |

### ジャンル別記事 — 全対応完了
| ファイル | h3→h2 | おすすめ→デビュー | :::cast |
|---|---|---|---|
| shinjin-av-bakunyu.md | ✅ | ✅ | ✅ 10名 |
| shinjin-av-18sai.md | ✅ | ✅ | ✅ 5名 |
| shinjin-av-minimum.md | ✅ | ✅ | ✅ 7名 |
| shinjin-av-c-cup.md | ✅ | ✅ | ✅ 5名 |
| shinjin-av-joshidaisei.md | ✅ | ✅ | ✅ 8名 |
| shinjin-av-geinou.md | ✅ | ✅ | ✅ 7名 |
| shinjin-av-moto-idol.md | ✅ | ✅ | ✅ 5名 |
| shinjin-av-bijiri.md | ✅ | ✅ | ✅ 6名 |
| shinjin-av-influencer.md | ✅ | ✅ | ✅ 6名 |
| shinjin-av-l-cup.md | ✅ | ✅ | ✅ 4名 |

---

### 2026-02 薄いエントリ補完
| ファイル | 対象女優 | 状態 |
|---|---|---|
| shinjin-av-2026-02.md | あんどうはな（MOODYZ分析+:::say強化） | ✅ |
| shinjin-av-2026-02.md | 石川くるみ（22歳現役保育士の詳細追加+:::say強化） | ✅ |

### 2025-11 CIDデータ重複問題 — 修正完了
| 女優 | 誤CID | 正CID | レーベル修正 | 状態 |
|---|---|---|---|---|
| 春野くるみ | `1mgold00055`（百崎きっかのもの） | `cawd00912`（kawaii\*） | なし（kawaii正しい） | ✅ |
| 永瀬みゆう | `pppe00385`（音井もものもの） | `cawd00905`（kawaii\*） | OPPAI→kawaii\*、本文全面リライト、身長171cm追加 | ✅ |

### :::say コメント品質向上
| ファイル | 修正件数 | 内容 | 状態 |
|---|---|---|---|
| shinjin-av-2025-09.md | 1件 | 澪奈あすみ：断定調化+具体化 | ✅ |
| shinjin-av-2025-10.md | 13件 | 全ですます調→断定調、レーベル分析・スペック追加 | ✅ |
| shinjin-av-2026-01.md | 1件 | 紫月みやび：断定調化+MOODYZ分析追加 | ✅ |
| shinjin-av-2026-02.md | 1件 | あんどうはな：エントリ補完と同時対応 | ✅ |

---

## 残タスク ❌ / ⚠️

なし — 全タスク完了

---

## ファイル変更サマリー（git diff 対象）

```
meikan/content/articles/shinjin-av-2025-09.md  — h2変換 + :::cast追加 + :::say改善1件
meikan/content/articles/shinjin-av-2025-10.md  — h2変換 + :::cast追加 + 6名補完 + :::say全面改善13件
meikan/content/articles/shinjin-av-2025-11.md  — h2変換 + :::cast追加 + 5名補完 + CID重複修正（春野くるみ・永瀬みゆう）
meikan/content/articles/shinjin-av-2025-12.md  — h2変換 + :::cast追加
meikan/content/articles/shinjin-av-2026-01.md  — 12名スタブ補完 + :::say改善1件
meikan/content/articles/shinjin-av-2026-02.md  — h2変換 + :::cast追加 + あんどうはな・石川くるみ補完
meikan/content/articles/shinjin-av-2026-03.md  — h2変換 + :::cast追加
meikan/content/articles/shinjin-av-bakunyu.md  — h2変換 + おすすめ→デビュー + :::cast追加
meikan/content/articles/shinjin-av-18sai.md    — h2変換 + おすすめ→デビュー + :::cast追加
meikan/content/articles/shinjin-av-minimum.md  — h2変換 + おすすめ→デビュー + :::cast追加
meikan/content/articles/shinjin-av-c-cup.md    — h2変換 + おすすめ→デビュー + :::cast追加
meikan/content/articles/shinjin-av-joshidaisei.md — h2変換 + おすすめ→デビュー + :::cast追加
meikan/content/articles/shinjin-av-geinou.md   — h2変換 + おすすめ→デビュー + :::cast追加（重複行削除）
meikan/content/articles/shinjin-av-moto-idol.md — h2変換 + おすすめ→デビュー + :::cast追加
meikan/content/articles/shinjin-av-bijiri.md   — h2変換 + おすすめ→デビュー + :::cast追加
meikan/content/articles/shinjin-av-influencer.md — h2変換 + おすすめ→デビュー + :::cast追加
meikan/content/articles/shinjin-av-l-cup.md    — h2変換 + おすすめ→デビュー + :::cast追加
```

---

## 技術メモ

### :::cast フォーマット
```
:::cast[タイトル]{列1,列2,列3}
女優名 | - | https://pics.dmm.co.jp/digital/video/{cid}/{cid}pl.jpg | 値1 | 値2 | 値3
:::
```

### DMM画像URLパターン
- サムネイル（:::cast用）: `{cid}pl.jpg`
- プロフィール（## 直下）: `{cid}jp-6.jpg`（バリエーション: jp-5, jp-7）
- サンプル1（:::samples）: `{cid}jp-2.jpg`
- サンプル2（:::samples）: `{cid}jp-9.jpg`（バリエーション: jp-8, jp-10）

### macOS sed 注意
```bash
sed -i '' 's/旧文字列/新文字列/g' ファイル
# ← macOS BSD sed は -i の後に空文字列引数が必須
```

### av博士 :::say 文体ルール
- キャラクター: 男性、断定調（「〜だ」「〜している」「〜は間違いない」）
- 内容: レーベルの特性 + スペック分析 + 具体的な作品評 + ランキング/売上への言及
- 長さ: 2〜3文（60〜120文字程度）
- NG: 汎用的な「期待したい」「注目だ」のみで終わる文
