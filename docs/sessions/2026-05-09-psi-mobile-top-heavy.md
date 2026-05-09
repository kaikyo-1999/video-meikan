# TOP モバイル「重い」体感の原因調査（PSI API）

## 依頼
TOPページ（`https://av-hakase.com/`）をモバイルで開くと重く感じる。PSI API を叩いて原因特定。

## 結論（先に）

**「重い」のはラボデータ（PSI シミュレータ）。実ユーザー（CrUX）は p75 LCP 1,220ms で FAST 判定。** ただしページ転送量は **4,617 KiB** と肥大しており、回線が細いと体感に効く。

主犯は **FC2 ランキングセクションの PNG サムネイル**（3rd party `storage201000.contents.fc2.com`）。1 枚 200〜600 KB の PNG が 10 件並び、表示サイズの 5〜10 倍の解像度（750〜1080 px）で配信されている。WebP/AVIF 非対応・リサイズ不可。

これに対し過去（2026-05-08）に試した `fetchpriority=high` + preload + above-fold eager は**逆効果**で revert 済み（`feedback_psi_image_preload.md`）。**画像軽量化が先**という方針は変わらず。

## PSI 測定結果（2026-05-09 / mobile）

### スコア / Core Web Vitals

| 指標 | ラボ値 | スコア | CrUX p75 |
|---|---|---|---|
| Performance | 81/100 | — | — |
| LCP | **4.6 s** | 0.34（poor） | **1,220 ms（FAST）** |
| FCP | 1.4 s | 0.97 | 900 ms（FAST） |
| TTI | **8.1 s** | 0.41 | — |
| TBT | 50 ms | 1.0 | — |
| CLS | 0 | 1.0 | 0（FAST） |
| Speed Index | 4.1 s | 0.80 | — |
| TTFB | — | — | 495 ms（FAST） |

→ **実ユーザーは全指標 FAST**。ラボの 4G スロットル + CPU 4x スロットル下では重い。

### LCP 要素

```
<img src="https://pics.dmm.co.jp/digital/video/snos00115/snos00115jp-2.jpg"
     alt="新人NO.1 STYLE ビジュ爆発！…博多彩葉 AVデビュー"
     width="220" height="148" loading="lazy" decoding="async">
```
- `hot-rail--works`（一番人気の作品 horizontal scroll）の **1 件目**
- DMM の `jp-2.jpg`（800×538、既に圧縮済み JPEG）

PSI の lcp-discovery checklist:
- requestDiscoverable: ✅ true
- eagerlyLoaded: ❌ false（`loading="lazy"`）
- priorityHinted: ❌ false（`fetchpriority=high` なし）

→ **lazy + 優先度なし**。が、これを高優先度化する施策は 2026-05-08 に逆効果と判明し revert 済み。理由: LCP 候補画像自体は 24 KB と軽いが、同時に並ぶ FC2 PNG（200〜600 KB × 10 枚）が帯域を食い、優先度ヒントを付けると LCP が逆に遅延する。

### 重い画像 Top 10（image-delivery-insight, 推定削減 3,805 KiB）

| URL | 実サイズ | 表示サイズ | wasted |
|---|---|---|---|
| FC2 PPV `1776065325.67.png` | **757×757** | 210×210 | 537 KB |
| FC2 PPV `1776061539.27.png` | **1015×1014** | 210×210 | 524 KB |
| FC2 PPV `1775816895.96.jpg` | 600×600 | 210×280 | 228 KB |
| FC2 PPV `1776065914.51.jpg` | **1080×1080** | 210×210 | 258 KB |
| FC2 PPV `1776065736.56.png` | 414×414 | 210×210 | 213 KB |
| FC2 PPV `1766476426.75.jpg` | **1280×1280** | 210×210 | 205 KB |
| FC2 PPV `1776065120.7.png` | 416×416 | 210×210 | 175 KB |
| DMM `mird00277jp-2.jpg` | 793×534 | 388×259 | 163 KB |
| DMM `atkd00347pl.jpg` | 800×538 | 385×259 | 152 KB |
| FC2 `1776068167.35.JPG` | 733×734 | 378×210 | 79 KB |

→ 上位 7 件はすべて **FC2 ストレージの PNG/JPG**。サーバー側でリサイズ・WebP 変換できない（3rd party）。

### 他の audit

| audit | 値 | 内容 |
|---|---|---|
| total-byte-weight | 4,617 KiB | ページ全体の転送量が重い |
| cache-insight | 削減 2,808 KiB | DMM 画像が `Cache-Control` 未設定（ttl=0）/ FC2 は 24h |
| render-blocking-insight | 375 ms | `style.css?v=...`（22.7 KB） |
| unused-javascript | 64 KiB / 41% | gtag.js（GA4）— 削除不可 |
| max-potential-fid | 250 ms | スコア 0.49 |

## 「なぜ重く感じるか」答え

1. **FC2 ランキングセクションが上位 fold 近辺にあり、巨大 PNG（1 枚 500〜600 KB）が複数並ぶ**
2. **3rd party 画像（FC2 / DMM）はサーバー側リサイズ・WebP 変換ができない**
3. ラボシミュレータ（4G スロットル）では LCP 4.6 s に劣化するが、**実ユーザー（CrUX）は LCP 1.2 s で FAST**
4. ただし転送量 4.6 MB は大きく、地下鉄や電波弱い環境では体感重い

## 改善余地（現状の方針との衝突回避）

過去の lesson: **重い画像への preload / fetchpriority は逆効果**（`memory/feedback_psi_image_preload.md` / `tasks/lessons.md`）。
→ 軽量化が先、という原則を守りつつ取れる手:

### A. 効果が大きい順（提案）

1. **画像プロキシ／CDN 経由配信（最大効果）**
   - 例: Cloudflare Workers で `/img-proxy/?url=...&w=220&fmt=webp` を作る
   - 1080×1080 PNG → 220×220 WebP で **1 枚 500 KB → 30 KB** が現実的
   - 削減見込み: 3 MB 超
   - コスト: 実装 0.5 日 + Workers 月 $5 程度
   - リスク: FC2 サムネ URL のリファラ／CORS 制限要確認

2. **FC2 セクションの above-fold 露出を減らす**
   - 現在 TOP に FC2 ランキングが 10 件並ぶ（hot-rail--fc2）
   - 例: 初期表示 5 件 + 「もっと見る」で残り 5 件を遅延読込
   - 効果: 上位 10 枚の半分が即時読込されなくなる → ラボ Speed Index / Total Byte 改善
   - リスク: FC2 ランキングのクリック導線（収益）が落ちる可能性 → A/B 推奨

3. **FC2 ランキングを TOP の下方に移動**
   - 現在ページ構成（home.php）: ① FC2 → ② 一番人気の作品 → ③ セール
   - これを ② → ③ → ① に変える
   - 効果: LCP 要素の競合帯域を減らす
   - リスク: FC2 のクリック・収益への影響を測る必要あり

4. **DMM 画像の `Cache-Control` 改善は不可**（pics.dmm.co.jp は 3rd party）
   - ただし上記 1（CDN プロキシ）に乗せれば自前で TTL 制御可

### B. 取らない方が良い手（過去に逆効果）

- LCP 画像へ `fetchpriority="high"` 付与
- LCP 画像 preload（`<link rel="preload">`）
- above-fold 画像の `loading="eager"` 化

→ 全て 2026-05-08 に検証済み・revert 済み。軽量化なしでこれらを足すと LCP がむしろ悪化する。

## 推奨アクション

**まず CrUX で FAST が出ているという事実をベースラインにする**。ラボ数値の改善より、ページ転送量 4.6 MB を減らす方向で:

- **次の一手: FC2 サムネを CDN プロキシ経由（WebP / 220×220 リサイズ）にする**（上記 A1）
- 並行: FC2 セクションの初期表示件数を 10 → 5 に減らす A/B（上記 A2）

ユーザーに確認したい:
- CDN プロキシ実装に進めるか？（Cloudflare Workers or 自前サーバー画像変換）
- FC2 セクションの位置・件数を変更してよいか？（収益影響あり）

## 参考データ
- 生 PSI レスポンス: `/tmp/psi-mobile-top.json`（579 KB, 2026-05-09 取得）
- 関連 lesson: `tasks/lessons.md` / `memory/feedback_psi_image_preload.md`
- 関連 commit: `78bcee5`（revert）, `700a65a`（lessons 追記）

## 確認・残タスク
- [ ] FC2 サムネ CDN プロキシ化、進めて良いか
- [ ] FC2 セクションの件数削減 / 位置変更の許可
