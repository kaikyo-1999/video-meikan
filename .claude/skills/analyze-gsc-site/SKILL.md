---
name: analyze-gsc-site
description: "GSCデータでサイト全体を多角的に分析する。週次クエリ変化、ページ別クリック変動、女優/ジャンル/記事のページタイプバランスをレポート。'サイト分析', 'GSC分析', 'GSCサイト分析', 'gsc analysis', '週次クエリ変化', 'ページタイプバランス', 'ページ別変動分析'で発動。"
metadata:
  version: 1.0.0
---

# GSC サイト全体分析スキル

Google Search Console APIから期間内データを取得し、3つの観点でサイトを分析する:

1. **週次クエリ変化** — Top10/期間Top20の推移、急上昇クエリ、急落クエリ
2. **ページ別クリック変動** — 当期 vs 前期で急増/急減/新規流入/消滅したページ
3. **ページタイプ別バランス** — 女優/ジャンル/記事/トップ/その他の週次比率

---

## 実行コマンド

```bash
# 直近4週間 (default、/article除外)
python3 .claude/skills/analyze-gsc-site/analyze.py

# 期間指定
python3 .claude/skills/analyze-gsc-site/analyze.py --from 2026-04-01 --to 2026-04-30

# /articleページも含める
python3 .claude/skills/analyze-gsc-site/analyze.py --include-article

# レポートの一部だけ
python3 .claude/skills/analyze-gsc-site/analyze.py --skip-page-changes
python3 .claude/skills/analyze-gsc-site/analyze.py --skip-balance
```

### オプション

| オプション | デフォルト | 説明 |
|-----------|----------|------|
| `--from YYYY-MM-DD` | 終了日の27日前 | 開始日 |
| `--to YYYY-MM-DD` | 今日-3日（GSC確定日） | 終了日 |
| `--include-article` | 除外 | /articleページを含める |
| `--skip-page-changes` | false | [2]を省略 |
| `--skip-balance` | false | [3]を省略 |

---

## 実行フロー

### Step 1: 期間ヒアリング（必要に応じて）

ユーザーが「先月のGSC分析して」のように指示したら、対象期間を確認する:
- 「4月」→ `--from 2026-04-01 --to 2026-04-30`
- 「直近1ヶ月」「先月」→ デフォルト or 月指定
- 範囲不明なら「直近4週間でいい？」と確認

### Step 2: スクリプト実行

```bash
python3 .claude/skills/analyze-gsc-site/analyze.py --from <start> --to <end>
```

データ量によっては1〜3分かかる。

### Step 3: 出力を読み解いてユーザーに要約

スクリプトは生データを長く吐く。**そのまま貼らずに、以下の観点で要約する**:

#### 週次クエリ変化（[1]の出力）
- 「初週だけ突出していたクエリ」（一過性スパイク = ニュース・話題化系）
- 「最終週で増えたクエリ」「new と表示された新出現クエリ」（積み上がりシグナル）
- 急上昇/急落のリストから、特定の女優・ジャンルにシフトしている兆候

#### ページ別クリック変動（[2]の出力）
- 急増ページ Top5を抜粋して URL パターン（`/{actress}/{genre}/` か `/{actress}/` か）に注目
- 急減ページは「http→https正規化」「重複URL」など健全なものは除外して評価
- 新規流入ページ・消滅ページのドメインパターンを観察

#### ページタイプ別バランス（[3]の出力）
- 週ごとの「女優% / ジャンル% / 記事%」の変化
- ユニーク流入URL数の急増（インデックス拡大シグナル）
- インプレッション・CTRから「どのタイプが今後伸ばしやすいか」の示唆

### Step 4: アクション提案

数字を並べて終わりではなく、**次に何をすべきか** を1〜3個に絞って提示:
- 「ジャンルページが伸びている → actress×genreの組み合わせを優先生成」
- 「女優単体ページの露出が縮小 → 内部リンクで補強 or 諦めてジャンル投資」
- 「特定女優のクエリが急上昇 → その女優の関連ジャンルページを追加」

---

## 分類ロジック（重要）

URLは以下の正規表現でカテゴリ分けされる（GSC側＝完全URL前提）:

| カテゴリ | 判定 |
|---------|------|
| `article` | URLに `/article` を含む |
| `top` | `https?://(www\.)?av-hakase\.com/$` |
| `genre` | `^https://av-hakase\.com/[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*/$` |
| `actress` | `^https://av-hakase\.com/[a-z0-9][a-z0-9-]*/$` |
| `other` | 上記以外 |

判定順は `article > top > genre > actress > other`。「女優URL = ジャンルURLの prefix」なので、必ずジャンル判定を先に行う。

---

## 注意事項

- **GSC APIには3日程度のデータ遅延**がある。`--to` を `today - 3` がデフォルト
- `/article` を **含めない** がデフォルト。記事の効果を見たいときは `--include-article`
- 大量データ取得のため、**Ahrefs MCP は使わない**（`gsc/fetch.py` の Google公式API経由）— これは CLAUDE.md の方針と一致
- ページ変動レポートは前期と同じ日数で自動比較する（4週間指定なら直前4週間と比較）

---

## 関連ファイル

| パス | 役割 |
|------|------|
| `gsc/fetch.py` | GSC API認証 + データ取得ヘルパ |
| `gsc/daily_report.py` | 日次推移グラフ（記事/女優/ジャンル積み上げ） |
| `ga4/daily_report.py` | GA4側の同等レポート（セッション・滞在・FANZAクリック） |
