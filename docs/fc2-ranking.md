# FC2ランキング機能 設計ドキュメント

## 概要

[magic7.site](https://magic7.site/) のようなFC2コンテンツのランキング機能をAV博士に追加する。
ユーザーがFC2作品を投票・発見できる専用ページを `/fc2/` 以下に構築する。

---

## 要件

| 項目 | 内容 |
|------|------|
| 配置 | av-hakase.com のサブディレクトリ（`/fc2/`） |
| データ登録 | 初期: バッチスクレイピング、以降: ユーザー投稿 |
| 投票方式 | IPベース（1IP1票） |
| FANZAとの関係 | 独立したランキング専用ページ |

---

## DBスキーマ

```sql
-- FC2作品テーブル
CREATE TABLE fc2_works (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cid VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(1000),
    price INT DEFAULT 0,
    duration INT,                          -- 再生時間（分）
    affiliate_url VARCHAR(1000),           -- アフィリエイトID取得後に設定
    is_approved TINYINT(1) DEFAULT 1,      -- ユーザー投稿は0で保留
    submitted_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 投票テーブル（1IP1票）
CREATE TABLE fc2_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fc2_work_id INT NOT NULL,
    voter_ip VARCHAR(45) NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote (fc2_work_id, voter_ip),
    FOREIGN KEY (fc2_work_id) REFERENCES fc2_works(id)
);
```

---

## ルーティング

| メソッド | パス | コントローラ | 用途 |
|---------|------|-------------|------|
| GET | `/fc2/` | Fc2RankingController@index | ランキング表示 |
| GET | `/fc2/submit/` | Fc2RankingController@submit | CID投稿フォーム |
| POST | `/fc2/submit/` | Fc2RankingController@store | CID登録 |
| POST | `/fc2/vote/` | Fc2RankingController@vote | 投票API（JSON） |

---

## 実装フェーズ

### Phase 1: 基盤
- `sql/fc2_ranking.sql` — DBマイグレーション
- `src/controllers/Fc2RankingController.php`
- Router へのルート追加

### Phase 2: 初期データ投入
- `batch/fetch_fc2_works.php` — FC2コンテンツページのスクレイピング
  - タイトル・サムネイル・価格・再生時間を取得
  - 初回のみ実行（手持ちCIDリスト or 人気順から取得）

### Phase 3: ランキング表示
- `templates/pages/fc2_ranking.php`
- `templates/partials/fc2_work_card.php`
- 全体 / 月間 / 週間 タブ切り替え（JS）

### Phase 4: 投票機能
- 投票ボタン（AJAX POST → `/fc2/vote/`）
- IP重複チェック（DBの UNIQUE KEY）
- 投票済み状態の表示

### Phase 5: ユーザー投稿
- CID投稿フォーム（`/fc2/submit/`）
- 投稿時にFC2ページをスクレイピングしてメタデータ取得
- `is_approved = 0` で登録 → 管理者承認後に表示

---

## FC2アフィリエイト

- 現時点では未取得。ID取得後に `affiliate_url` カラムを更新する
- それまでは直接リンク（`https://fc2.com/content/{cid}/`）で代替

### 制約（調査済み）

- 販売者が「アフィリエイト許可」に設定した商品のみ対象
- 公式APIなし → データ取得はスクレイピングに依存
- スクレイピングはToSリスクあり（初期データ取得の範囲で限定使用）

---

## 未決定事項

- [ ] 初期スクレイピング対象のCIDをどこから取るか（手持ちリスト or 人気順クロール）
- [ ] FC2アフィリエイトID取得（申請が必要）
- [ ] スパム投稿対策（CAPTCHAなど、Phase 5実装時に検討）
