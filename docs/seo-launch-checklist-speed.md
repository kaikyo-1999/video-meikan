# SEO サイトスピードチェック結果

- サイトタイプ: メディア・ブログ（AV女優データベース）
- 最終チェック日: 2026-04-03
- チェック実施者: Claude (check-seo-launch skill)
- 対象: パフォーマンス（Core Web Vitals）関連項目のみ

---

## 4. パフォーマンス（Core Web Vitals）

| 優先度 | タスク | 判定 | 備考 |
|:---:|---|:---:|---|
| **必須** | HTTPS を強制 | OK | `.htaccess:12-13` HTTP→HTTPS 301リダイレクト実装済み。HSTS `max-age=31536000` 設定済み |
| **必須** | LCP ≤ 2.5秒 | 要計測 | Lighthouse実測が必要。コード上はファーストビュー画像の最適化に課題あり（後述） |
| **必須** | INP ≤ 200ms | OK | JS合計336行/11.5KB。DOMContentLoaded駆動のUI操作のみで長いタスクなし |
| **必須** | CLS ≤ 0.1 | OK | 主要画像にwidth/height属性あり。CSS aspect-ratio も多数設定（`style.css:228,504,559,728,814`） |
| **高** | LCP画像に `fetchpriority="high"` を付与 | NG | 全テンプレートで `fetchpriority` 未使用。ファーストビュー画像への付与が必要 |
| **高** | 画像に width / height 属性を明示 | OK | `actress-card.php:4`（300x300）, `work-card-horizontal.php:6`（147x200）等、主要画像に設定済み |
| **高** | 画像に loading="lazy" を適用 | NG | `actress-card.php:4` で全インスタンスに `loading="lazy"` が付いており、ファーストビューの画像も遅延読み込みされてしまう。トップページ最初のセクション画像は `loading="eager"` にすべき |
| **高** | 画像に alt 属性を設定 | OK | 全img タグ（12個）に動的alt設定済み（女優名・作品名等） |
| **高** | Gzip / Brotli 圧縮を有効化 | OK | nginx側でBrotli圧縮が有効（レスポンスヘッダー `content-encoding: br` 確認済み）。.htaccessでの設定は不要 |
| **高** | ブラウザキャッシュを設定 | OK | nginx側で CSS/JS に `cache-control: max-age=604800`（7日）設定済み。`helpers.php:44-45` でアセットにバージョンパラメータ付与 |
| **高** | Lighthouse CLI で主要ページを計測 | 未実施 | 別途実施が必要 |
| **中** | セキュリティヘッダーを設定 | OK（部分） | HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy 実装済み。**CSP 未設定** |
| **低** | Speculation Rules API でプリレンダリング | 未実装 | 優先度低。静的コンテンツ中心のため効果は見込めるが、対応は任意 |

---

## サマリー

| 判定 | 件数 |
|---|---|
| OK | 8 |
| NG | 2 |
| 未実装/未実施 | 2 |
| 対象外 | 0 |

### 緊急度: 高（LCP直結）

- [ ] **ファーストビュー画像の最適化** — `actress-card.php` の全imgに `loading="lazy"` が付いており、トップページ最初のセクション画像もLazy Loadされている。ファーストビューの画像には `loading="eager"` + `fetchpriority="high"` を付与すべき
  - 対応案: テンプレートに `$lazy` パラメータを追加し、呼び出し側で制御

### 緊急度: 低

- [ ] **CSP ヘッダー未設定** — XSS耐性向上のため `.htaccess` に追加を推奨
- [ ] **Speculation Rules API** — 次ページプリレンダリング。効果はあるが優先度低
- [ ] **Lighthouse 実測** — 本番URLでモバイル/デスクトップの計測を実施し、スコアを記録

---

## 本番レスポンスヘッダー確認結果（2026-04-03）

```
# HTML (https://av-hakase.com/)
content-encoding: br              ← Brotli圧縮 OK
strict-transport-security: max-age=31536000  ← HSTS OK

# CSS (style.css)
cache-control: max-age=604800     ← 7日キャッシュ OK
expires: Thu, 09 Apr 2026         ← Expires OK

# JS (app.js)
cache-control: max-age=604800     ← 7日キャッシュ OK
```
