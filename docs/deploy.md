# デプロイ手順（シンレンタルサーバー）

## 前提
- シンレンタルサーバー契約済み
- 独自ドメイン取得済み

## 1. サーバー管理画面での初期設定

1. **ドメイン設定**: 独自ドメインを追加
2. **SSL設定**: 無料SSL（Let's Encrypt）を有効化
3. **PHP設定**: バージョンを 8.x に設定
4. **MySQL設定**: データベースを作成し、以下を控える
   - DB名
   - DBユーザー名
   - DBパスワード
   - DBホスト（通常 `localhost`）

## 2. DBセットアップ

管理画面の「phpMyAdmin」を開き、作成したDBを選択。
「SQL」タブで `sql/schema.sql` の内容を実行する。

※ `CREATE DATABASE` と `USE` の2行は削除してから実行（DBは管理画面で作成済みのため）。

## 3. ファイルアップロード

SSH（SCP）でアップロード:

```bash
scp -r meikan/ ユーザー@サーバー:/home/ユーザー/ドメイン/public_html/meikan/
```

または管理画面のファイルマネージャー / FTP でも可。

`dev-server.php` は本番不要なのでアップロードしない。

## 4. 環境設定

サーバー上で `meikan/.env` を作成:

```
DB_HOST=localhost
DB_NAME=（管理画面で作ったDB名）
DB_USER=（管理画面で作ったDBユーザー）
DB_PASS=（設定したパスワード）

FANZA_API_ID=（FANZA APIのAPI ID）
FANZA_AFFILIATE_ID=（DMMアフィリエイト審査通過後に設定）
```

## 5. パーミッション設定

SSH接続して実行:

```bash
cd ~/ドメイン/public_html/meikan
chmod 755 cache/ logs/
```

## 6. 初期データ投入

```bash
cd ~/ドメイン/public_html/meikan
php batch/import_actresses.php
php batch/fetch_fanza.php
```

## 7. 動作確認

- `https://ドメイン/meikan/` にアクセス
- 女優一覧が表示されること
- 女優ページ（例: `/meikan/mikami-yua/`）が表示されること
- ジャンルページのリンクが正しく動作すること
- CSS/JSが正しく読み込まれること
- sitemap.xml（`/meikan/sitemap.xml`）が正しく出力されること

## 8. 定期バッチ設定（任意）

管理画面の「cron設定」で新作データを定期取得:

```
実行コマンド: /usr/bin/php /home/ユーザー/ドメイン/public_html/meikan/batch/fetch_fanza.php
実行間隔: 毎日1回（例: 毎日 04:00）
```

## 補足

### BASE_PATHの変更
`/meikan/` ではなくドメイン直下で運用したい場合:
- `config/app.php` の `BASE_PATH` を `''` に変更
- ファイルを `public_html/` 直下に配置

### アフィリエイトID設定
DMMアフィリエイト審査通過後:
1. `.env` の `FANZA_AFFILIATE_ID` を更新
2. `php batch/fetch_fanza.php` を再実行（新しいIDでURLが生成される）
3. `php batch/clear_cache.php` でキャッシュクリア
