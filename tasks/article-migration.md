# 記事移行タスク（hpkenkyu.mixh.jp → video-meikan）

## 概要
hpkenkyu.mixh.jp から20記事を移行。移行自体は完了済み。
現在は **各記事への画像追加** が残タスク。

---

## 移行済み記事一覧

| ファイル | タイトル | カテゴリ | 画像追加 |
|---------|---------|---------|---------|
| xcity.md | XCITYアダルト見放題の口コミ評判 | VODレビュー | 済 |
| dmmpremium.md | DMMプレミアムAV見放題の評判 | VODレビュー | 済（元ページ404のため限定的） |
| rakutentv.md | 楽天TV「AV見放題」口コミ評判 | VODレビュー | 済（元ページ年齢認証のため限定的） |
| sokmil.md | ソクミルの口コミ評判 | VODレビュー | 済 |
| yuuryou-adult-hikaku.md | おすすめ有料アダルト動画サイト比較 | VODレビュー | 済（元ページ404のため限定的） |
| av-mihoudai.md | AVサブスクおすすめ9選 | VODレビュー | 済 |
| 18sai-avjoyuu.md | 18歳AV女優一覧 | 女優ランキング | 済 |
| 40dai-osusume-av.md | 40代AV女優20人 | 女優ランキング | 済 |
| 30dai-osusume-av.md | 30代AV女優一覧 | 女優ランキング | 済 |
| goumou-osusume.md | 剛毛AV女優30人 | 女優ランキング | 済 |
| pocchari.md | ぽっちゃりAV女優20選 | 女優ランキング | 済 |
| chijo-osusume.md | 痴女AV女優20人 | 女優ランキング | 済 |
| teishincho-joyu.md | 低身長AV女優20人 | 女優ランキング | 済 |
| abemikako-osusume.md | あべみかこおすすめ25選 | 女優おすすめ作品 | 済 |
| av-maker.md | 人気AVメーカー100選 | AVメーカー | 未（画像なし記事の可能性） |
| bukkake-osusume.md | ぶっかけAV20選 | 作品ランキング | 済 |
| daeki-osusume.md | 唾液AV10選 | 作品ランキング | 済 |
| iramachio-osusume.md | イラマチオAV20選 | 作品ランキング | 済 |
| saimin-av.md | 催眠AV20選 | 作品ランキング | 済（一部CID未取得） |
| hashimotokanna-av.md | 橋本環奈そっくり5名 | そっくりさん | 済 |

---

## 残タスク：画像追加

### タイプA: 作品ランキング・女優ランキング・そっくりさん記事
**方針**: 各作品のFANZA CIDを調べて `:::samples` ブロックを追加する

**画像URLパターン**:
```
https://pics.dmm.co.jp/digital/video/{cid}/{cid}jp-3.jpg
https://pics.dmm.co.jp/digital/video/{cid}/{cid}jp-7.jpg
```

**CID調査方法（優先順位）**:
1. DBで検索: `mysql -u root --socket=/tmp/mysql_meikan.sock video_meikan -e "SELECT source_id, title FROM works WHERE title LIKE '%...%' LIMIT 5;"`
2. FANZA APIで検索:
```bash
API_ID="uDXrNTMHGm4A1UXMUFUz"
AFF_ID="avhakase2026-990"
curl -s "https://api.dmm.com/affiliate/v3/ItemList?api_id=${API_ID}&affiliate_id=${AFF_ID}&site=FANZA&service=digital&floor=videoa&keyword={URL_ENCODED_KEYWORD}&hits=3&output=json"
```

**記事内の画像追加パターン（anochan-niteiru-av.md を参照）**:
```markdown
**おすすめ作品**：[作品名](https://www.dmm.co.jp/digital/videoa/-/detail/=/cid={cid}/)

:::samples
https://pics.dmm.co.jp/digital/video/{cid}/{cid}jp-3.jpg
https://pics.dmm.co.jp/digital/video/{cid}/{cid}jp-7.jpg
:::
```

**調査済みCID一覧**:
| 記事 | 作品/女優 | CID |
|------|---------|-----|
| daeki-osusume.md | 三上悠亜「欲情した接吻痴女の唾液交換...」 | ssni00279 |
| daeki-osusume.md | 楪カレン「家庭教師のカレン先生のごほうびベロチュー...」 | pred00567 |
| daeki-osusume.md | 神宮寺ナオ「女神のベロキスで涎ダラダラ密着！...」 | midv00212 |
| daeki-osusume.md | 八木奈々「今すぐKissMe舌をず～っと絡ませっ放し...」 | mide00888 |

---

### タイプB: VODレビュー記事
**方針**: 元URLから画像をfetchしてMarkdownに埋め込む

元URL一覧:
- https://hpkenkyu.mixh.jp/xcity/ → xcity.md
- https://hpkenkyu.mixh.jp/dmm-premium/ → dmmpremium.md
- https://hpkenkyu.mixh.jp/rakuten-tv/ → rakutentv.md
- https://hpkenkyu.mixh.jp/sokmil/ → sokmil.md
- https://hpkenkyu.mixh.jp/yuuryou-adult-video/ → yuuryou-adult-hikaku.md
- https://hpkenkyu.mixh.jp/av-mihoudai/ → av-mihoudai.md

**注意**: hpkenkyu.mixh.jp はJSレンダリング（WordPress/Sango）なのでWebFetchでは取得不可。
curlで取得 → Pythonで解析が必要。

---

## 技術メモ

### ローカルdev起動
```bash
cd /Users/kaikyotaro/repository/video-meikan/meikan
php -S localhost:8766 index.php
```

### DB接続
```bash
mysql -u root --socket=/tmp/mysql_meikan.sock video_meikan
```
- works: 28,400件（source_id = FANZA CID）
- actresses: 100件

### .env（FANZA API認証情報）
```
FANZA_API_ID=uDXrNTMHGm4A1UXMUFUz
FANZA_AFFILIATE_ID=avhakase2026-990
```

### 参照記事（画像パターン確認用）
- `/meikan/content/articles/anochan-niteiru-av.md` ← :::cast + :::samples + @actress の使い方
- `/meikan/content/articles/hashimoto-kanna-niteiru-av.md` ← シンプルな:::samplesの使い方
