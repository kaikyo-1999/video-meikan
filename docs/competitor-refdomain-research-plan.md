# 競合参照ドメイン調査 実行プラン（4/29 Ahrefsリセット後実行用）

## 目的
av-hakase.com の被リンク獲得のため、SEO流入のある競合5社の参照ドメインを Ahrefs で取得し、その中から「獲得できそうな被リンク先」を抽出する。

- 立案日: 2026-04-25
- 実行予定日: 2026-04-29（Ahrefs API ユニットリセット直後）

---

## 戦略

```
Step 1: 競合5社をAhrefs batch-analysisで流入検証（2,000以上を確認）
  ↓ 流入2,000未満なら補欠と差し替え
Step 2: 各社のreferring-domainsを取得（質フィルタ＋上限設定で予算最適化）
  ↓
Step 3: 5社の参照ドメインを統合し、av-hakase.comの既存被リンクと差分取り
  ↓
Step 4: 「獲得できそう」フィルタ（個人ブログ/相互リンク募集サイト/はてなブログ等の登録系）で30〜50件に絞る
  ↓
Step 5: docs/backlink-outreach.md に追加して連絡管理
```

---

## 最終5社（subagent検証済み・2026-04-25時点）

| # | ドメイン | 役割 | 採用理由 |
|---|---------|------|---------|
| 1 | av-wiki.net | **本命** | av-hakase.comと最も方向性が近い大規模AV女優DB。毎日更新・2020年〜の運営。WP系。被リンク資産が厚いと推定 |
| 2 | av-rank.com | **本命** | 設計類似度が最も高い（身長/カップ/体型/顔/メーカー軸別検索）。先行する将来像。LiteSpeed運用 |
| 3 | av-times.com | 記事系競合 | 月別デビュー情報・メーカー解説で av-hakase.com の記事システムと正面競合。デビュー記事から自然リンクが集まりやすい |
| 4 | javmodel.com | **多様性確保** | 2001年開設の老舗・グローバル流入。海外フォーラム/Redditからの被リンクで国内系と被らない参照ドメインが取れる |
| 5 | minkch.com | ニッチ枠 | 2008年〜・15年以上の老舗。AVまとめ/アンテナ系ネットワークに組込済み。**DB系で取れない種類の被リンク先**を発掘するため |

### 補欠（メインが流入2,000未満だった場合の差し替え用）
- av-report.com（旧av-watcher.com、作品レビュー特化）
- av-actress.com（シンプル女優DB）

### 死亡確認済みの旧候補（再検討不要）
warekore.net / erohamu.net / avche.com / avhime.com / av-mode.com / jav-actress.com / av-actress-info.net / av-mu.com / ero-friend.com（9件すべてWHOIS未登録）

---

## 実行コマンド（4/29に上から順に実行）

### Step 1: 流入検証（コスト目安: 5〜10ユニット）

```
mcp__claude_ai_Ahrefs__batch-analysis
  select: ["url", "domain_rating", "org_traffic", "org_keywords", "refdomains"]
  targets: [
    {url: "av-wiki.net", mode: "subdomains", protocol: "both"},
    {url: "av-rank.com", mode: "subdomains", protocol: "both"},
    {url: "av-times.com", mode: "subdomains", protocol: "both"},
    {url: "javmodel.com", mode: "subdomains", protocol: "both"},
    {url: "minkch.com", mode: "subdomains", protocol: "both"},
    {url: "av-report.com", mode: "subdomains", protocol: "both"},
    {url: "av-actress.com", mode: "subdomains", protocol: "both"}
  ]
  country: "jp"
  order_by: ["org_traffic:desc"]
```

判定:
- 流入2,000以上のサイトをStep 2の対象とする
- 最低3社・最大5社（流入2,000満たすサイトのみ）

### Step 2: 各社の参照ドメイン取得（コスト目安: 1社あたり300〜500ユニット）

各競合について、以下を実行。

```
mcp__claude_ai_Ahrefs__site-explorer-referring-domains
  target: "<競合ドメイン>"
  mode: "subdomains"
  protocol: "both"
  select: "domain,domain_rating,traffic_domain,first_seen,is_dofollow,is_root_domain,positions_source_domain,links_to_target"
  where: {
    "and": [
      {"field": "domain_rating", "is": ["gte", 10]},
      {"field": "is_root_domain", "is": ["eq", true]},
      {"field": "is_spam", "is": ["eq", false]},
      {"field": "traffic_domain", "is": ["gte", 100]}
    ]
  }
  limit: 300
  order_by: "domain_rating:desc"
```

注意:
- `traffic_domain` は10ユニット/行、`refdomains` は5ユニット/行と高コスト。`select` で必要最小限に絞る
- DR10以上＋月流入100以上のフィルタで「価値ある被リンク先」だけに絞る
- 5社×300件 = 1,500件 × 11ユニット = **約16,500ユニット**（リセット後の月次予算に収まる）

### Step 2 簡易版（予算節約モード）

`traffic_domain`を抜いた軽量版。コスト 1ユニット/行 × 1,500件 = 1,500ユニット。

```
select: "domain,domain_rating,first_seen,is_dofollow,is_root_domain,positions_source_domain"
where: {
  "and": [
    {"field": "domain_rating", "is": ["gte", 15]},
    {"field": "is_root_domain", "is": ["eq", true]},
    {"field": "is_spam", "is": ["eq", false]}
  ]
}
```

---

## Step 3〜5: 分析・フィルタ・出力（Ahrefs後の手作業）

### Step 3: 統合と差分取り

```python
# 疑似コード
all_competitor_refdomains = union(comp1_refs, comp2_refs, comp3_refs, comp4_refs, comp5_refs)
# av-hakase.comの既存refdomainsをAhrefsで取得（コスト追加）
av_hakase_refs = get_refdomains("av-hakase.com")
# 差分: 競合は獲得済み、自社は未獲得
target_refs = all_competitor_refdomains - av_hakase_refs
```

### Step 4: 「獲得できそう」フィルタ

含める：
- 個人ブログ（はてなブログ・livedoor blog・FC2 blog・WordPress個人サイト）
- 相互リンク募集サイト（LP上に「相互リンク」の表記があるもの）
- リンク集サイト・ディレクトリ
- コメント可能サイト
- **登録すれば被リンクが付く系**: はてなブックマーク、note、Qiita（→ アダルト系は不可）、各種まとめサイト

除外：
- FANZA / DMM / MGS等の大手公式
- Wikipedia / Yahoo知恵袋 / 教えてgoo
- 報道機関のニュースサイト
- SNS（Twitter, Facebook, Instagram）
- アクセス不能サイト

判別の手がかり（手作業 or WebFetch併用）：
- ドメイン名から個人/法人を推定
- DR・参照ドメイン数の規模感
- WebFetchで「相互リンク」「お問い合わせ」ページの存在確認

### Step 5: docs/backlink-outreach.md に追加

既存20件の表に「競合分析由来」という出典列を加えて30〜50件追加。優先度は流入＋親和性で再ランキング。

---

## 注意事項

- **流入2,000以上の基準を満たさない競合が複数出る可能性あり**（av-rank.comとjavmodel.comは未測。日本語サイトのみ評価ならjavmodelは除外検討）
- **海外サイト（javmodel.com）は country=jp でなく country=us でも測ってみる**価値あり。Ahrefsの`org_traffic`は国別と全世界で大きく違うことが多い
- **は はてなブログは「登録すれば被リンク獲得可」だがアダルト規約NG**。アダルト系自社サイトをはてなブログに登録は不可。ただし「はてなブックマーク」は可能性あり
- **note.com もアダルトNG規約**。前回リサーチの通り、アダルト系では使えない登録系プラットフォームが多い点を念頭に
- API予算が再度枯渇するリスクに備え、Step 2は**簡易版（軽量フィルタ）から実行**して結果を見ながら本格版に切り替える運用を推奨

---

## このドキュメントの状態

- [x] 競合5社の事前検証完了（subagent経由 WebFetch + WHOIS）
- [x] 死亡ドメイン9件を排除済み
- [x] 4/29に即実行できる Ahrefs コマンドを確定
- [ ] 4/29 Ahrefsリセット後にStep 1実行
- [ ] Step 2 で参照ドメイン取得
- [ ] Step 3〜5 で分析・フィルタ・docs/backlink-outreach.md 反映
