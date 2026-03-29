---
name: create-debut-article
description: "新人AV女優デビュー記事を作成する。月別デビュー一覧記事と条件別（爆乳・小柄・18歳等）記事の2モード対応。'新人記事', 'デビュー記事', 'debut article', '月別デビュー', '条件別デビュー', '新人av記事'で発動。"
metadata:
  version: 1.0.0
---

# 新人AV女優デビュー記事 作成スキル

あなたは新人AV女優のデビュー記事の専門ライターです。以下のフローに従って、SEOに強く読者の興味を引く記事を作成します。

2つのモードがあります：
- **モードA**: 月別デビュー一覧記事（例: 2025年9月デビュー）
- **モードB**: 条件別デビュー記事（例: 爆乳新人、小柄新人）

---

## Step 1: モード選択と情報収集

### ユーザーから確認する情報

- **モード**: A（月別）or B（条件別）
- **モードA の場合**: 対象年月（例: 2025-09）
- **モードB の場合**: 条件カテゴリ（爆乳/小柄/18歳/芸能人/元アイドル/インフルエンサー/Cカップ/美尻/女子大生/Lカップ）

### DB照会で候補を取得

モードAの場合:
```bash
php -r '
require_once "config/app.php"; require_once "config/database.php"; require_once "src/Database.php"; require_once "src/Cache.php"; require_once "src/models/Actress.php";
$results = Actress::findByDebutMonth("YYYY-MM");
foreach ($results as $r) echo $r["name"] . " | " . $r["slug"] . " | debut: " . $r["debut_date"] . " | works: " . $r["work_count"] . "\n";
'
```

モードBの場合:
```bash
php -r '
require_once "config/app.php"; require_once "config/database.php"; require_once "src/Database.php"; require_once "src/Cache.php"; require_once "src/models/Actress.php";
$results = Actress::findRecentDebuts(6);
foreach ($results as $r) echo $r["name"] . " | " . $r["slug"] . " | debut: " . $r["debut_date"] . " | works: " . $r["work_count"] . "\n";
'
```

### 自力で調べる情報（Web検索）

各女優について以下をリサーチする：

| 情報 | 用途 | 調べ方 |
|-----|------|--------|
| 身長・スリーサイズ・カップ | 基本プロフ | Web検索「{女優名} プロフィール」 |
| 年齢・生年月日 | 18歳判定、プロフ | Web検索「{女優名} 年齢」 |
| 経歴・前職 | 芸能人/アイドル/インフルエンサー判定 | Web検索「{女優名} 経歴」「{女優名} 元アイドル」 |
| おすすめ作品CID | 作品カード | FANZA検索 or DB照会 |
| 特徴・人気の理由 | 本文執筆 | Web検索「{女優名} 特徴」「{女優名} おすすめ」 |

**モードB 条件別の判定基準**:

| 条件 | 判定基準 |
|------|---------|
| 爆乳 | Fカップ以上。FANZAジャンル「巨乳」(2001)の作品がある場合も対象 |
| 小柄・ミニマム | 身長155cm以下 |
| 18歳 | デビュー時点で18歳（生年月日から計算） |
| 芸能人 | テレビ出演、映画出演、グラビア等の芸能活動歴あり |
| 元アイドル | アイドルグループ在籍歴あり（地下アイドル含む） |
| インフルエンサー | SNS（TikTok、Instagram、YouTube等）のフォロワー多数 |
| Cカップ | Cカップと公表 |
| 美尻 | 作品タイトル/ジャンルに「美尻」、Web上で美尻評価あり |
| 女子大生 | デビュー時点で大学在学中（18〜22歳で大学在学中と公言） |
| Lカップ | Lカップと公表 |

---

## Step 2: 構成をユーザーに確認

以下をユーザーに提示して合意を得る：

1. **記事タイトル案**（2〜3案）
2. **女優リスト**（名前 + 簡易プロフ一覧）
3. **各女優の紹介方向性**（1行ずつ）
4. **記事の目標人数**（モードA: 全員掲載、モードB: 5〜15名程度）

---

## Step 3: 記事を執筆

### ファイル配置

```
meikan/content/articles/{slug}.md
```

### slug命名規則

| モード | パターン | 例 |
|-------|---------|-----|
| A: 月別 | `shinjin-av-YYYY-MM` | `shinjin-av-2025-09` |
| B: 条件別 | `shinjin-av-{condition}` | `shinjin-av-bakunyu` |

### フロントマター

```yaml
---
title: {タイトル}
slug: {slug}
description: {120文字以内のmeta description}
category: 新人女優
published_at: {今日の日付 YYYY-MM-DD}
updated_at: {今日の日付 YYYY-MM-DD}
---
```

### モードA: 月別デビュー記事テンプレート

```markdown
{導入文: その月のデビュー状況の概要、注目ポイント 2〜3文}

:::say
av女優博士です。{YYYY年M月}にデビューした新人AV女優をまとめて紹介します。
:::

## {YYYY年M月}デビューの新人AV女優一覧

| 女優名 | デビュー日 | 身長 | スリーサイズ | 所属レーベル |
| --- | --- | --- | --- | --- |
| {各女優の行} |

### {女優名1}

![{女優名1}のサンプル]({サンプル画像URL})

| 項目 | 詳細 |
| --- | --- |
| デビュー日 | {YYYY年M月D日} |
| 身長 | {cm} |
| スリーサイズ | {B/W/H（カップ）} |
| 所属レーベル | {レーベル名} |

{本文: 経歴、特徴、注目ポイントを2〜4段落}

@actress[{slug}]

**デビュー作品**：[{作品タイトル}](https://www.dmm.co.jp/digital/videoa/-/detail/=/cid={CID}/)

:::samples
{サンプルURL1}
{サンプルURL2}
:::

:::say
{av博士の一言コメント: この女優の今後の期待度や見どころ}
:::

（以下、全女優分を繰り返す）

## まとめ

{その月のデビュー女優の総括。注目度の高い女優への言及。}
```

### モードB: 条件別デビュー記事テンプレート

```markdown
{導入文: 条件の魅力と新人への期待感 2〜3文}

:::say
av女優博士です。直近半年にデビューした新人の中から、{条件}の女優を厳選して紹介します。
:::

:::box[{条件}新人AV女優の選定基準]
- {基準1}
- {基準2}
- {基準3}
:::

## {条件}のおすすめ新人AV女優

| 女優名 | デビュー月 | 身長 | スリーサイズ | おすすめ度 |
| --- | --- | --- | --- | --- |
| {各女優の行。おすすめ度は★3〜5} |

### {女優名1}

![{女優名1}のサンプル]({サンプル画像URL})

| 項目 | 詳細 |
| --- | --- |
| デビュー月 | {YYYY年M月} |
| 身長 | {cm} |
| スリーサイズ | {B/W/H（カップ）} |

{本文: 条件に合致する理由、特徴、おすすめポイントを3〜5段落}

@actress[{slug}]

**おすすめ作品**：[{作品タイトル}](https://www.dmm.co.jp/digital/videoa/-/detail/=/cid={CID}/)

:::samples
{サンプルURL1}
{サンプルURL2}
:::

:::say
{av博士コメント}
:::

（以下、全女優分を繰り返す）

## まとめ

{条件カテゴリの総括。読者へのおすすめまとめ。}
```

---

## マークダウン構文リファレンス

この記事システムで使える構文の一覧。**標準のマークダウンとは異なるカスタム構文**がある。

### インライン装飾

| 構文 | 出力 |
|---|---|
| `**太字**` | 太字 |
| `==マーカー==` | 黄色マーカー |
| `==[red]赤マーカー==` | 赤マーカー |
| `[テキスト](URL)` | リンク（自動でnofollow付与） |
| `!img[URL]` | インライン小画像（テーブル内等） |

### ブロック要素

| 構文 | 用途 |
|---|---|
| `## 見出し` | h2 |
| `### 見出し` | h3 |
| `![alt](URL)` | ブロックレベル画像（全幅表示） |
| `@actress[slug]` | DB女優カード埋め込み |
| `[テキスト](FANZA detail URL)` | 作品カード埋め込み（縦レイアウト） |
| `[テキスト](FANZA search URL)` | FANZA検索リンクカード |

### カスタムブロック

#### 吹き出し（av女優博士）
```
:::say
テキスト
:::
```

#### ボックス
```
:::box[タイトル]
- リスト項目
:::
```

#### メリデメ比較表
```
:::proscons[タイトル]
pros[メリットラベル]:
- 項目1
- 項目2
cons[デメリットラベル]:
- 項目1
- ==[red]強調項目==
:::
```

#### 警告ボックス
CSSが⚠️アイコンをタイトル前に自動付与するため、タイトルに⚠️を含めないこと。
```
:::alert[タイトル]
警告テキスト
:::
```

#### CTAボタン
```
[btn ボタンテキスト](URL)
```
ピンク色のボタンとして中央揃えで表示される。

#### サンプル画像ギャラリー
```
:::samples
https://画像URL1
https://画像URL2
:::
```
2枚の画像が横並びで表示される。

---

## 画像URL仕様

### FANZA サンプル画像のURLパターン

```
https://pics.dmm.co.jp/digital/video/{CID}/{CID}jp-{N}.jpg
```

- `{CID}`: 作品のcontent_id（例: `1mmgh00125`）
- `{N}`: 連番（1〜12程度。作品により枚数は異なる）

### パッケージ画像のURLパターン

```
https://pics.dmm.co.jp/digital/video/{CID}/{CID}pl.jpg
```

### 画像の使い分けルール

| 用途 | 選び方 |
|---|---|
| **一覧テーブル** | 使わない（テキストのみ） |
| **h3直下のブロック画像** | おすすめ/デビュー作品のサンプル画像から、顔や体型がよく見える1枚（jp-5〜8あたり） |
| **:::samples** | h3直下とは**別の**サンプル画像を2枚（jp-2〜3, jp-9〜10あたり） |

**重要**: h3直下の画像と:::samplesの画像は必ず異なるものを使うこと。

---

## 文体・トーンのルール

- **語り口**: 男性向けアダルトメディアの体裁。常体（だ・である調）を基本とする
- **新人への期待感**: 「期待の新星」「新たな逸材」など、ポジティブなトーンを維持
- **身体的特徴の描写**: 読者が視覚的にイメージできる具体的な表現を心がける
- **av女優博士の吹き出し**: 各女優のおすすめ作品の後に、1〜2文の短いコメント。作品の見どころやデビュー作の評価を語る
- **情報の正確性**: 経歴・身体スペック・デビュー日などの事実は、Web検索で裏取りしてから書く
- **SEOキーワード**: タイトルと導入文に「新人AV女優」「AVデビュー」「{年月}」を自然に含める

---

## Step 4: ユーザーに確認

完成した記事をユーザーに提示し、以下を確認する：

1. 女優リストに過不足はないか
2. 各女優の紹介文の内容・トーンは適切か
3. サンプル画像のURLは正しく表示されるか
4. 追記・修正したい箇所はあるか

---

## Step 4.5: 画像URL全件バリデーション（必須）

**記事を保存したら、ユーザーに確認を出す前に必ずこれを実行する。**

```bash
# 記事内の全画像URLのHTTPステータスを確認
grep -hE 'pics\.dmm\.co\.jp[^)]+\.jpg' meikan/content/articles/{SLUG}.md | \
  grep -oE 'https://[^)]+\.jpg' | sort -u | while read url; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "$url")
  [ "$code" != "200" ] && echo "NG ${code}: ${url}"
done
echo "画像チェック完了"
```

NGが出た場合は以下で対処してから先に進む：

### 画像NGの対処手順

**1. 「1」プレフィックス問題（302が返る場合）**
FANZAリンクCIDに「1」が含まれていても、画像URLでは除く：
```
1sone00221 → sone00221
1cawd00952 → cawd00952
```
※ ただし `1sdab00xxx` `1fns00xxx` は除かない（1付きが正しい場合あり）

**2. jp番号オーバー（作品によりサンプル枚数が異なる）**
```bash
CID="XXXXXX"
for n in 10 9 8 7 6 5 4 3; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "https://pics.dmm.co.jp/digital/video/${CID}/${CID}jp-${n}.jpg")
  [ "$code" = "200" ] && echo "最大: jp-${n}" && break
done
```
最大番号が `jp-8` なら、h3直下は `jp-6〜7`、samplesは `jp-2` と `jp-8` を使う。

**3. 全番号で302（CID自体が間違い）**
DBから同女優の別作品source_idを取得して代替CIDとして使う：
```bash
php -r '
require_once "config/app.php"; require_once "config/database.php"; require_once "src/Database.php"; require_once "src/Cache.php";
$db = Database::getInstance();
$stmt = $db->prepare("SELECT w.source_id, w.title FROM works w JOIN actress_work aw ON w.id=aw.work_id JOIN actresses a ON aw.actress_id=a.id WHERE a.slug=? ORDER BY w.release_date");
$stmt->execute(["SLUG_HERE"]);
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) echo $r["source_id"] . " | " . mb_substr($r["title"],0,40) . "\n";
'
```

---

## チェックリスト（公開前）

- [ ] frontmatterのslug・title・descriptionが正しいか
- [ ] 一覧テーブルの全女優のフィールドが揃っているか
- [ ] 各h3の直下に `![alt](url)` のブロック画像があるか
- [ ] 各おすすめ作品の下に `:::samples` があるか
- [ ] h3画像と:::samplesの画像URLが重複していないか
- [ ] DBにいる女優は `@actress[slug]` で埋め込んでいるか
- [ ] DBにいない女優はFANZA検索リンクを配置しているか
- [ ] `:::say` の吹き出しが各女優のおすすめ作品の後にあるか
- [ ] FANZA作品リンクのCIDが正しいか
- [ ] **画像URL全件バリデーション済み（Step 4.5）** ← 必須
