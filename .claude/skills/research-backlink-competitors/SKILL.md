---
name: research-backlink-competitors
description: "競合サイトの参照ドメインをAhrefs MCPで横断調査し、被リンク獲得候補を抽出する。'被リンク調査', '参照ドメイン調査', 'バックリンク候補', 'refdomain比較', 'リンク獲得候補抽出', 'competitor backlink research'で発動。"
metadata:
  version: 1.0.0
---

# 競合参照ドメイン横断調査スキル

オーガニック競合の参照ドメインをAhrefs MCPで取得し、複数競合に共通して掲載されるドメインや、1社のみだが獲得可能性のあるドメインをリストアップして `docs/` に格納する。

---

## 前提

- Ahrefs MCPサーバー（`d05d162a-...`）が有効
- av-hakase.com はAhrefsプロジェクトにGSC連携されていない可能性あり → `site-explorer-*` 系で代替する
- 結果は `docs/` 配下にMarkdownで保存し、`docs/backlink-outreach.md` への追加候補として活用する

---

## Step 1: 競合サイトの特定（5〜8社）

すでに `docs/competitor-refdomain-research-plan.md` で5社が選定済みの場合はそれを使う。新規の場合は以下を実行:

```
mcp__d05d162a-...-site-explorer-organic-competitors
  target=av-hakase.com
  mode=subdomains
  country=jp
  date=<実行日 YYYY-MM-DD>
  limit=30
  order_by=keywords_common:desc
  select=competitor_domain,keywords_common,keywords_competitor,keywords_target,traffic,domain_rating
```

抽出時は **直接競合（女優DB／ジャンル特化）** を優先し、以下は除外する:
- メーカー公式（dmm.co.jp, sod.co.jp, mgstage.com）
- Tubeサイト（njavtv.com, tktube.com）
- ノイズ（hitomi.la — コミック検索, fmk.fm — 音楽, fanbox.cc）

---

## Step 2: 各競合の参照ドメイン取得

選定した5〜8社に対して **並列で** 以下を実行（1メッセージで複数tool callを送る）:

```
mcp__d05d162a-...-site-explorer-referring-domains
  target=<competitor_domain>
  mode=subdomains
  history=live
  limit=100
  order_by=domain_rating:desc
  select=domain,domain_rating,traffic_domain,dofollow_links,links_to_target
  where={"and":[
    {"field":"is_root_domain","is":["eq",true]},
    {"field":"is_spam","is":["eq",false]},
    {"field":"dofollow_links","is":["gt",0]}
  ]}
```

**重要**: `where`句で `is_spam=false` と `dofollow_links>0` を必ず指定。これでスパム/nofollowを事前に除外する。

コスト目安: 約16 units/row × 100 × 8社 = 約12,800 units（実際は競合ごとに参照ドメイン数が異なるため変動）

---

## Step 3: 横断比較（2サイト以上で出現するドメイン抽出）

各競合の結果からドメインリストを作成し、出現サイト数をカウント。

優先度判定基準:

| 出現数 | DR | 判定 |
|--------|----|----|
| 4社以上 | 任意 | ★★★ 最優先 |
| 2-3社 | DR50+ | ★★★ |
| 2-3社 | DR30-49 | ★★ |
| 2-3社 | DR10-29 | ★ |
| 2-3社 | DR0-9 | ★（連絡コスト次第） |

**× 除外推奨パターン:**
- `.info` 連番ドメイン（rttrws*, q3bbpj*, lmcuppg* 等）→ PBNネットワーク
- ドメイン名が乱文（tatougsggd.com, healingisland3103.com 等）
- 中華圏（`.cc`, 数字+xyz 等）でAV系コンテンツの怪しいもの

---

## Step 4: 1サイトのみだが「出せそう」な候補抽出

すべての競合の参照ドメインから、出現は1サイトのみだが以下の条件を満たすものをTier分類する。

| Tier | 条件 | 取得方法 |
|-----|------|---------|
| **S** | DR60+ かつ申請ベースで自社で獲得可能 | 自社オペで獲得（ameblo.jp 投稿、megalodon.jp アーカイブ申請、ランキングサイト登録 等） |
| **A** | DR40-59 のメディア系 | プレスリリース・寄稿打診・問い合わせフォーム |
| **B** | DR20-39 の個人ブログ・小規模メディア | 連絡可能性を個別調査 |
| **C** | DR0-19 の個人ブログ | 連絡コスト最小、ついで枠 |

**抽出対象に含める基準:**
- AV/風俗/グラビア関連のテーマ親和性あり
- 連絡フォーム・問い合わせ手段がありそう
- ドメイン名・コンテンツ内容が真っ当

**含めない:**
- 中華・韓国系（言語の壁）
- 競合自身（pan-pan.co, minnano-av.com 等）
- PBN疑い

---

## Step 5: ドキュメント化

`docs/competitor-refdomain-findings.md` に以下の構造で保存（既存の場合は日付セクションで追記）:

```markdown
# 競合参照ドメイン調査 結果（YYYY-MM-DD Ahrefs実行）

## 調査対象（オーガニック競合N社）
<テーブル: ドメイン, DR, タイプ, 共通KW>

## 1. 2サイト以上で参照されるドメイン（N件）
### ★★★ 最優先候補
### ★★ 中優先
### ★ 小優先
### × 除外推奨（PBN/低品質）

## 2. 1サイトのみだが「出せそう」な候補
### Tier S：自社獲得可能（DR60+）
### Tier A：個別連絡で獲得余地（DR40-59）
### Tier B：個人ブログ・小規模メディア（DR20-39）
### Tier C：個人ブログ（DR0-19）
### × 除外

## 3. 次アクション
1. Tier S/A から優先的に20件程度を docs/backlink-outreach.md に追加
2. 最頻出ドメインのリンクパターン調査
3. PBN系の自社被リンク確認（disavow判断）

## 付録：データ取得コマンド
```

---

## Step 6: コミット & PR

```bash
git checkout -b claude/refdomain-research-YYYYMMDD  # または既存の作業ブランチで
git add docs/competitor-refdomain-findings.md
git commit -m "docs/competitor-refdomain-findings.md: 競合N社の参照ドメイン横断比較結果"
git push -u origin <branch>
# mcp__github__create_pull_request でPR作成
```

PR本文には以下を含める:
- 調査対象の競合数とドメイン
- 抽出された★★★ドメインの代表例
- Tier S/A の代表例
- 次アクション

---

## トラブルシューティング

### `where` フィルタで InputValidationError

- `not_substring` は無効。`{"not":{"field":"x","is":["substring","val"]}}` のように `not` で囲む
- URL型フィールドへの `substring` は値の型エラー → 部分URLは渡せない。`prefix` で完全URL指定する

### `select` で column not found

- 各エンドポイントの `select` 識別子は doc tool の `outputSchema` で確認（`where` の column識別子とは別物）
- 例: organic-competitors は `keywords_common`, `keywords_competitor`, `traffic`, `domain_rating` を使う（`common_keywords` は誤り）

### コスト超過

- `select` から `traffic_value`, `org_cost`, `paid_cost`, `refdomains`, `dofollow_refdomains` 等の高ユニット列を外す
- `limit` を50に絞る
- 競合数を5社に絞る（最重要）

---

## このスキルが使われた事例

- 2026-05-01: `docs/competitor-refdomain-findings.md` を作成。8社調査で2サイト以上出現39件、1サイト候補38件を抽出。PR #16
