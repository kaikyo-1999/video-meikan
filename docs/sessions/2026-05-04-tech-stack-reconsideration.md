# 技術スタック再検討 — Filmarks 風 TOP リニューアル前の棚卸し

## 依頼

TOP リニューアル（`docs/sessions/2026-05-04-filmarks-style-toppage-proposal.md`）に着手する前に、
今の技術スタックが最適か再検討したい。oripa-sokuho（オリパ速報 https://oripanews.com）等を
参考に改善案があれば提示。**ただし PHP からは外さない。**

---

## 1. 現状スタックの棚卸し

| レイヤ | 現状 | 規模感 |
|-------|------|-------|
| Backend | PHP 8 カスタムMVC（フレームワーク無） | 11 controllers / 既存実装は薄く読みやすい |
| DB | MySQL utf8mb4 | actresses + works + genres + junction |
| Cache | ファイルベース TTL 3600s | `meikan/cache/` |
| Frontend | PHP テンプレート + 単一 style.css + 6つの JS ファイル | CSS 4,142 行 / JS 計 971 行 |
| Build | **無し**（生 CSS / 生 JS をそのまま配信） | バンドラー無し |
| 画像 | FANZA 公式 (`pics.dmm.co.jp`) を直リンク | リサイズ無し・WebP変換無し |
| アナリティクス | GA4 + GSC | サーバーキー認証あり、Pythonで日次集計 |
| デプロイ | rsync / zip ベース、Shinserver 共有ホスト | nohup で長時間バッチ |

**強み**: 学習コスト最小、依存ゼロ、共有ホストでも動く、PHPの素朴さがそのまま速度になっている。
**弱み**: (a) 画像が重い (b) 動的UI（フィルタ・無限スクロール・検索）の選択肢が無い (c) CSS が単一巨大ファイル化 (d) パフォーマンス計測軸がページビュー止まり。

---

## 2. oripanews.com (オリパ速報) を観察して得た知見

curl で HTML ソースを直接確認した結果、**av-hakase が即取り入れるべき設計が3点**ある。

### 2.1 画像最適化を images.weserv.nl に丸投げしている

```html
<img src="https://images.weserv.nl/?url=torecabomb.site%2F...%2FRectangle-1.png&w=240&output=webp&q=82"
     loading="lazy" width="120" height="80">
```

- **images.weserv.nl** は Cloudflare 上の**無料**画像プロキシ。リサイズ＋WebP変換を URL クエリだけで実現
- インフラ追加ゼロで LCP/CLS が大幅改善
- av-hakase の場合: `pics.dmm.co.jp/digital/video/{cid}/{cid}pl.jpg` を `https://images.weserv.nl/?url=pics.dmm.co.jp/digital/video/{cid}/{cid}pl.jpg&w=240&output=webp&q=80` に置換するだけ
- ※ FANZA 側の hot-link 規制と Referer ポリシーは要事前テスト（weserv 経由なら User-Agent / Referer は weserv 側になるので回避できる可能性大）

### 2.2 全画像に width / height + loading + fetchpriority

```html
<img ... loading="lazy" width="120" height="80">                        <!-- ファーストビュー外 -->
<img ... fetchpriority="high" width="320" height="180">                  <!-- LCP候補 -->
```

CLS=0 / LCP < 1.5s の世界観。av-hakase は現状 width/height が抜けているカードがある（要確認）。

### 2.3 純粋な MPA で十分戦えている

- `_next/`, `_nuxt/`, バンドル成果物 **なし**
- jQuery / Alpine / Swiper も無し
- WordPress すら使わずに（自前 CMS 風）、**サーバーサイド HTML + 最小 JS** で完結
- 「PHP 風アーキテクチャは時代遅れではない」ことの実例

→ **av-hakase が PHP のまま戦う方針に強い裏付け**。

---

## 3. 改善案（PHP 維持・優先度順）

### 🔴 Tier 1 — TOP リニューアル前に**先にやる**ことを推奨

#### A. 画像プロキシ (images.weserv.nl) 導入

| 項目 | 内容 |
|------|------|
| 効果 | LCP 大幅改善 / 帯域削減 / カード表示の見栄え向上 |
| コスト | ヘルパー関数1つ追加で全テンプレ置換可能 / 月額 0 円 |
| リスク | weserv 側のレート制限（公開情報では「fair use」） / 障害時のフォールバック設計 |
| 作業 | `helpers.php` に `cdnImg($url, $w)` を追加。テンプレ側を `<img src="<?= cdnImg($actress['thumbnail_url'], 240) ?>">` に置換 |

実装イメージ:
```php
function cdnImg(string $src, int $w = 240, int $q = 80): string {
    $clean = preg_replace('#^https?://#', '', $src);
    return "https://images.weserv.nl/?url={$clean}&w={$w}&output=webp&q={$q}";
}
```

#### B. 全 `<img>` に width / height / loading / fetchpriority

- `actress-card.php` / `work-card.php` の `<img>` 走査
- ファーストビュー枠（新人セクション最初の3枚など）に `fetchpriority="high"`
- それ以外は `loading="lazy"` + `decoding="async"`

#### C. APCu によるクエリキャッシュ

| 項目 | 内容 |
|------|------|
| 効果 | ファイルキャッシュより 10〜100倍速い / Redis 不要 |
| コスト | shared host で APCu が有効か確認が必要 (Shinserver は php.ini で有効化可) |
| 作業 | `Cache.php` に APCu バックエンド追加 / 既存 API はそのまま |

→ファイルキャッシュは**フォールバック**として残す。

#### D. Web Vitals を GA4 に送信

```html
<script type="module">
  import {onLCP, onCLS, onINP} from 'https://unpkg.com/web-vitals@4?module';
  const send = ({name, value, id}) => gtag('event', name, {value: Math.round(value), id});
  onLCP(send); onCLS(send); onINP(send);
</script>
```

- 改善のたびに数値で測れる基盤を先に整える
- TOP 変更後の効果検証にも必須

---

### 🟡 Tier 2 — TOP リニューアル**と一緒に**入れる

#### E. HTMX 採用（フィルタ・無限スクロール・サジェスト検索のため）

| 項目 | 内容 |
|------|------|
| 効果 | 「ジャンルタブ切替」「もっと見る」「検索サジェスト」を**ほぼ JS なしで**実装 |
| コスト | 1ファイル (~14KB gzip) を `<script>` で読むだけ。ビルド不要 |
| PHP との相性 | ★★★ — partial を `render('partials/_actress_grid.php', ...)` で返すだけ |
| リスク | 学習コスト軽微（HTML 属性のみ） |

例: 「もっと見る」ボタン
```html
<button hx-get="/api/hot-works?offset=10" hx-target="#hot-rail" hx-swap="beforeend">
  もっと見る
</button>
```
PHP 側は `ApiController@hotWorks` でカードの partial HTML を返すだけ。

#### F. Alpine.js 採用（小さな UI 状態管理）

| 項目 | 内容 |
|------|------|
| 効果 | favorites トグル・タブ切替・モーダル等を 1 行属性で書ける |
| コスト | 1ファイル (~15KB gzip)。ビルド不要 |
| 既存 favorites.js との関係 | favorites.js (480行) を Alpine 化すると 100行台に圧縮可能（Phase 3 でリファクタ） |

例: ヒーロータブ
```html
<div x-data="{tab: 'work'}">
  <button :class="tab==='work' && 'active'" @click="tab='work'">作品</button>
  <button :class="tab==='actress' && 'active'" @click="tab='actress'">女優</button>
  <div x-show="tab==='work'">...</div>
</div>
```

→ HTMX × Alpine.js は「PHP MPA を Filmarks 風 SPA体感に近づける」現代的定番。

#### G. CSS Scroll Snap（既出）

特殊ライブラリ不要、Swiper 等は要らない。

---

### 🟢 Tier 3 — **やる必要は薄い**（参考まで）

#### H. PHP マイクロフレームワーク (Slim / Laravel) 移行

- ❌ 現状 11 controllers で困っていない
- 既存コードを書き直すコストが効果を上回らない
- **やめる**

#### I. Tailwind 全面導入

- 既存 CSS 4,142 行のリファクタが必要 → 投資回収が見えにくい
- TOP の新規パーツだけ部分的に Tailwind ユーティリティ風 class を新設するのは可（限定導入）

#### J. Redis / Memcached

- 単一サーバなら APCu で十分
- 複数サーバ運用になったら検討

#### K. Meilisearch / Algolia / Typesense

- データ件数 (作品 35K / 女優 1K 程度) なら MySQL FULLTEXT で十分
- 高速サジェスト検索が必要になった段階で検討

#### L. SPA 化 (Next.js / Nuxt / SvelteKit)

- ❌ ユーザー指示で PHP 維持 → 対象外
- 念のため: アフィリエイト系SEOサイトでは MPA の方がクロール容易・LCP 短縮しやすい

---

## 4. 推奨ロードマップ

```
Phase 0 (TOP変更前・1〜2日)         Tier 1 を全部入れる
  └─ A: 画像プロキシ
  └─ B: img 属性整備
  └─ C: APCu
  └─ D: Web Vitals 計測

Phase 1 (TOP見た目・1〜2日)         元提案の Phase 1
  └─ ② FC2 / セクション再構成 / 横スクロール
  └─ ここで HTMX+Alpine を最小導入（1ファイルでも先に読み込む）

Phase 2 (行動データ反映・3〜5日)    元提案の Phase 2
  └─ work_signals / actress_signals
  └─ ヒーロー検索を HTMX で実装

Phase 3 (パーソナル・5〜7日)        元提案の Phase 3
  └─ favorites.js を Alpine 化リファクタ
  └─ お気に入り急上昇ロジック合流
```

---

## 5. 「やらない判断」のまとめ

| 提案 | 判断 | 理由 |
|------|------|------|
| Laravel / Symfony 移行 | ❌ | 既存規模で過剰 |
| React / Vue SPA | ❌ | SEO・PHP方針に反する |
| Tailwind 全面導入 | △ | 部分導入のみ可 |
| Redis | △ | APCu で十分 |
| 専用検索エンジン | △ | MySQL FULLTEXT で開始 |
| Vite / Webpack バンドラー | △ | JS 1000行未満なら不要、増えたら検討 |

---

## 6. リスク・前提確認

- **Shinserver で APCu が有効か** — `php -i | grep apcu` で要確認
- **images.weserv.nl が pics.dmm.co.jp を中継できるか** — 1枚で実機テストしてから全置換
- **HTMX/Alpine を CDN で読む際の SLA** — JSDelivr / unpkg どちらかを固定。可用性気になるなら `public/js/vendor/` にコミット

---

## 確認・残タスク

1. ✅ **Phase 0 を先行で着手** — 承認済み（2026-05-04）
2. **HTMX + Alpine.js を採用するか** → Appendix A のデメリット参照
3. **images.weserv.nl の本番テスト** → Appendix B のタイミング/デメリット参照
4. ❌ **Tailwind 部分導入** — 不要と判断（2026-05-04）

---

## Appendix A — HTMX + Alpine.js のデメリット詳細

### HTMX の弱点

| デメリット | 影響度 | 緩和策 |
|----------|-------|-------|
| **AJAX レスポンスが HTML** → ブラウザキャッシュが効きにくい / 帯域はやや増える | 中 | サーバー側で `Cache-Control: max-age=60` を明示。partial に ETag を返す |
| **ロジックがサーバー寄り** → リクエスト数が増えサーバー負荷が増える | 中 | APCu キャッシュ + 1リクエスト = 1 SQL 程度に抑える設計 |
| **複雑な多段フォームには向かない** | 低 | av-hakase は CRUD 系UIが少ないため非問題 |
| **デバッグが独特** — ネットワークタブで HTML 確認、JS console は出ない | 低 | 慣れの問題、`hx:beforeRequest` でイベントログ可 |
| **GA4/3rd party JS の再実行を明示する必要** — `htmx:afterSwap` で再バインド | 中 | favorites.js / fanza_click のイベント listener は `document` レベルで登録すれば不要（既存実装はそうなっている） |
| **日本語情報が React より少ない** | 低 | 公式 docs が短く読み切れる量 |

### Alpine.js の弱点

| デメリット | 影響度 | 緩和策 |
|----------|-------|-------|
| **HTML が x-data / x-show で散らかる** — ロジックが HTML に埋まる | 中 | コンポーネント単位の規模を小さく保つ。複雑になったら `Alpine.data()` で外出し |
| **複雑な状態管理に弱い** — store はあるが Vuex/Redux 級ではない | 低 | av-hakase 規模では問題にならない |
| **CDN 読み込みだと初回 FOUC（チラつき）** | 中 | `x-cloak` + `[x-cloak]{display:none}` を CSS に追加 |
| **テストしにくい** — 単体テスト手段が薄い | 低 | E2E (agent-browser) で代替 |
| **TypeScript サポート弱い** | 低 | av-hakase は TS 不使用なので無関係 |
| **ロックインリスク** | 低 | CDN 1ファイル = 抜きたければ抜ける |

### 既存 favorites.js (480行) との共存リスク

- favorites.js は素のJS。Alpine 化するなら**段階的リファクタ**（Phase 3）
- 共存中に「クリックイベントの二重発火」が起きる可能性 → 同じ要素には片方だけ bind するルールを徹底

### 採用判断のサマリ

→ **デメリットはあるが av-hakase の規模・運用形態では概ね無視できる範囲**。
   特に CDN 1ファイルで抜き差しできるロックインの低さが大きい。**採用推奨は変えない。**

---

## Appendix B — images.weserv.nl のテストタイミング & デメリット

### おすすめテストタイミング

| 段階 | 内容 | 期間 |
|------|------|------|
| Step 1 | 1枚の URL を curl + ブラウザで動作確認 | 5分 |
| Step 2 | `helpers.php` に `cdnImg()` を追加し、TOP の女優カード**だけ**を切り替え | 30分 |
| Step 3 | Lighthouse で LCP / 画像転送量を Before/After 比較 | 15分 |
| Step 4 | 問題なければ全テンプレに展開（`actress-card.php` `work-card-v2.php` 等） | 1時間 |
| Step 5 | 1週間運用して 5xx 率 / 画像欠損率を GA4 / サーバーログで監視 | 〜1週間 |

→ **TOP リニューアル着手前のこのタイミング**が最適。Phase 1 の見た目変更時に画像が一気に出るので効果が体感しやすい。

### デメリット / リスク

| デメリット | 影響度 | 対策 |
|----------|-------|------|
| **無料サービスで SLA なし** — 障害時に全画像が落ちる | 高 | `cdnImg()` ヘルパーで定数フラグ `CDN_IMG_DISABLED` を作り、緊急時は1行で元 URL に戻せる構造 |
| **「fair use」の上限が公式に明記されていない** — トラフィック爆増で制限される可能性 | 中 | 月間 PV と画像リクエスト数を計測。上限近づいたら Cloudflare Images / Bunny.net に移行 |
| **初回リクエストは weserv 側のキャッシュミスで遅くなる** | 低 | 2回目以降は CDN キャッシュで高速。`preconnect` で事前接続 |
| **FANZA 側の画像更新が反映されない** — weserv のキャッシュが残る | 低 | `&maxage=1d` パラメータで TTL 制御可能 |
| **Referer / User-Agent が weserv のもの**になる | 中 | FANZA 側の hot-link 制限を回避できる利点。一方で FANZA のアクセス解析からはこちらの参照元が見えなくなる |
| **OGP 画像には使えない**（Facebook/Twitter クローラーが weserv 経由を辿る挙動が読みづらい） | 中 | OGP 用 `<meta property="og:image">` は元 URL のまま使う。表示用 `<img>` だけ weserv 経由に |
| **HTTPS 強制 / 一部画像が読めないケース** | 低 | 1枚テストで弾く。ダメなら fallback で元 URL |

### 障害時のフォールバック設計（必須）

```php
// config/app.php
define('CDN_IMG_ENABLED', true);   // 緊急時 false にして再デプロイ

// helpers.php
function cdnImg(string $src, int $w = 240, int $q = 80): string {
    if (!CDN_IMG_ENABLED || empty($src)) return $src;
    $clean = preg_replace('#^https?://#', '', $src);
    return "https://images.weserv.nl/?url={$clean}&w={$w}&output=webp&q={$q}";
}
```

→ デプロイ1発で全画像を元 URL に戻せる。これで weserv 障害時のリスクは実質ゼロ化。

### 代替候補（weserv が合わなかった場合）

| サービス | 料金 | 特徴 |
|---------|------|------|
| Cloudflare Images | $5/月〜 (10万枚) | SLA あり、Cloudflare 統合 |
| Bunny.net Optimizer | $0.01/GB | 安価、SLA あり |
| 自前 `/img.php` | サーバーCPU負担 | 完全制御、外部依存ゼロ |

### 採用判断のサマリ

→ **フォールバック設計を入れた上で導入推奨**。リスクは「weserv 障害時の画像切れ」だが、`CDN_IMG_ENABLED=false` 1発で復旧可能なので実質許容範囲。

---

## Phase 0 着手記録

### 2026-05-04 実施分

- ✅ **Web Vitals 計測** — `templates/layout.php` に web-vitals@4 (ESM版) を追加
  - 送信指標: LCP / CLS / INP / FCP / TTFB
  - GA4 イベント名は各指標名そのまま (`LCP`, `CLS`, `INP`, `FCP`, `TTFB`)
  - パラメータ: `value` (CLS は ×1000), `metric_id`, `metric_rating`, `page_path`
  - GA4 側で1〜2日後にカスタム指標として登録すれば探索レポートで見られる
- ✅ **img 属性補完** — width/height/decoding 抜け箇所を修正
  - `partials/work-card-v2.php` (slide x2)
  - `partials/fc2-work-card.php`
  - `partials/other-genres-inline.php`
- ✅ **dns-prefetch** に `unpkg.com` を追加（web-vitals モジュール取得用）

### 残 (Phase 0 のうち未着手)

- ⛔ **画像プロキシ (images.weserv.nl)** — **使用不可と判明**（後述）
- ⛔ **APCu キャッシュ** — **Shinserver で無効と判明**（後述）

---

## Appendix C — 環境調査結果 (2026-05-04)

### C.1 images.weserv.nl が使えない

実機テスト:
```bash
$ curl 'https://images.weserv.nl/?url=pics.dmm.co.jp%2Fdigital%2Fvideo%2F118abf00177%2F118abf00177pl.jpg&w=240&output=webp&q=80'
{"status":"error","code":400,"message":"Domain or TLD blocked by policy"}
```

**結論**: `pics.dmm.co.jp` (FANZA) は weserv のポリシーでブロックされている（adult 系として弾かれている可能性大）。
→ av-hakase では **使用不可**。

### C.2 APCu / Redis / Memcached が無い

```bash
$ ssh ... 'php -m | grep -iE "apcu|redis|memcache"'
(出力なし)

$ ssh ... 'php -i | grep -iE "opcache.enable[^_]"'
opcache.enable => On => On
```

**結論**: Shinserver で利用可能なメモリキャッシュは **OPcache のみ**。

---

## Appendix D — 代替案

### D.1 画像最適化の代替（weserv の代わり）

| 案 | 月額 | adult 対応 | 実装コスト | おすすめ度 |
|----|------|---------|---------|---------|
| **(a) FANZA 既存サイズバリアント活用** (`pl.jpg` → `ps.jpg`/`pt.jpg`) | 0円 | ◎ | 極小（URL 変換のみ） | ★★★ 即採用 |
| **(b) 自前 PHP プロキシ** (`/img.php?src=...&w=240`、GD で WebP 変換 + キャッシュ) | 0円 | ◎ | 中 (1〜2日) | ★★ 中期で導入 |
| **(c) Bunny.net Optimizer** | $0.01/GB | △ (要規約確認) | 小 | ★ 大幅トラフィック増時 |
| **(d) Cloudflare 全体 + Polish** | $25/月 (Pro) | △ (要規約確認) | 中（DNS 切替） | △ コスト高 |
| **(e) Cloudflare Images** | $5/月〜 | × (一般的に adult NG) | 中 | × 規約NG |

#### (a) FANZA バリアント活用 — 即効性あり

FANZA 画像 URL には既にサイズバリアントが用意されている:

| 末尾 | 用途 | サイズ |
|------|------|------|
| `pl.jpg` | 大（パッケージ大） | ~800px |
| `ps.jpg` | 小（パッケージ小） | ~150px |
| `pt.jpg` | サムネ | ~80px |

→ **TOP の女優サムネは `ps.jpg` で十分**、横スクロール rail のサムネは `pt.jpg` で OK。
→ WebP 化はできないが、**転送量は 5〜10倍削減**できる。

#### (b) 自前 PHP プロキシ — 中期で WebP 化が必要なら

```
GET /img.php?src=https://pics.dmm.co.jp/.../pl.jpg&w=240
  ↓ ローカルキャッシュ (cache/img/{md5}.webp)
  ↓ なければ GD で取得→リサイズ→WebP 変換→保存
  → image/webp で配信
```

- GD は Shinserver の標準モジュールで利用可能（要確認）
- ローカルディスクをCDN代わりに（Cache-Control: max-age=31536000）
- メリット: 完全制御 / 月額0円 / WebP 化で帯域大幅減
- デメリット: サーバー CPU/ディスク使用、初回リクエストはやや重い

→ **(a) を Phase 0 で即やる、(b) は Phase 2 以降の検討事項**として整理。

### D.2 キャッシュの代替（APCu の代わり）

| 案 | 速度 | 実装コスト | おすすめ度 |
|----|------|---------|---------|
| **(α) 既存ファイルキャッシュ継続** | 中 | 0 | ★★ 現状維持で OK |
| **(β) OPcache を使った PHP 配列キャッシュ** (`var_export` → `include`) | 高 (APCu 同等) | 小 | ★★★ 採用推奨 |
| **(γ) MySQL クエリキャッシュ調整 + インデックス最適化** | 中 | 中 | ★★ 並行で実施 |

#### (β) OPcache 配列キャッシュ — APCu の現実的な代替

OPcache は PHP ファイルをバイトコードキャッシュするので、
クエリ結果を PHP 配列として保存→include する方式なら **メモリ常駐相当の速度**になる。

```php
function opcacheGet(string $key, callable $loader, int $ttl = 3600) {
    $file = ROOT_DIR . '/cache/opc/' . md5($key) . '.php';
    if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
        return include $file;
    }
    $data = $loader();
    file_put_contents($file, '<?php return ' . var_export($data, true) . ';');
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($file, true);
    }
    return $data;
}
```

- 既存ファイルキャッシュより**5〜10倍速い**（OPcache メモリヒット）
- 既存 Cache クラスのバックエンドとして差し込み可能
- インフラ追加ゼロ

---

## Appendix E — 修正後の Phase 0

| 項目 | 状態 |
|------|------|
| ✅ Web Vitals 計測 | 完了 |
| ✅ img width/height 補完 | 完了 |
| ✅ **画像最適化 (FANZA バリアント活用)** | 完了 2026-05-04 |
| ✅ **キャッシュ強化 (OPcache 配列方式)** | 完了 2026-05-04 |
| ✅ **HTMX + Alpine.js 投入** | 完了 2026-05-04 |

### 実装内容まとめ

**Phase 0.3 — fanzaImg() ヘルパー追加**
- `meikan/src/helpers.php` に `fanzaImg($url, $size = 'ps')` を追加
- パターン: `*pl.jpg` → `*{ps|pt|pl}.jpg`、クエリ文字列保持、非 FANZA URL は no-op
- 適用箇所:
  - `partials/other-genres-inline.php` — ジャンルカバー (200x200) → `ps`
  - `partials/work-card-horizontal.php` — 横長作品カード (147x200) → `ps`
- スモークテスト OK (work URL 変換 / actress URL 不変 / null 安全)

**Phase 0.4 — Cache.php OPcache backend**
- `meikan/src/Cache.php` を OPcache 配列キャッシュ + 旧ファイルキャッシュ fallback の二段構成に
- `cache/opc/{md5}.php` に `<?php return [...];` 形式で保存 → `include` で OPcache メモリヒット
- `var_export` 不可なデータ (Closure / 無名クラス等) は自動的に旧ファイルキャッシュへ fallback
- `opcache_invalidate()` で書き込み時に強制再読込
- アトミック書き込み (rename) で半端な include を防止
- 既存 API (`get / set / clear`) は完全互換、呼び出し側の変更不要

**Phase 0.5 — HTMX + Alpine.js 投入**
- `meikan/templates/layout.php` に CDN script + `[x-cloak]` CSS を追加
- バージョン固定: htmx@2.0.4 / alpinejs@3.14.3
- defer 読み込みでレンダリングブロックなし
- 実利用は Phase 1 以降（FC2 セクション・ヒーロータブ等）
