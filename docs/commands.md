# よく使うコマンド一覧

## SSH接続

```bash
ssh -p 10022 サーバーID@ホスト名
```

## デプロイ（サーバー上で実行）

### 初回セットアップ
```bash
cd ~
git clone https://github.com/kaikyou1223/video-meikan.git repo
rsync -av --exclude='.env*' --exclude='dev-server.php' --exclude='sql/' --exclude='batch/' --exclude='logs/' repo/meikan/ ~/av-hakase.com/public_html/
```

### 更新反映
```bash
cd ~/repo
git pull
rsync -av --exclude='.env*' --exclude='dev-server.php' --exclude='sql/' --exclude='batch/' --exclude='logs/' meikan/ ~/av-hakase.com/public_html/
```

### キャッシュクリア
```bash
cd ~/av-hakase.com/public_html
php batch/clear_cache.php
```

## DB関連（名鑑ページ追加時）

### 初期データ投入
```bash
cd ~/av-hakase.com/public_html
php batch/import_actresses.php
php batch/fetch_fanza.php
```

### FANZA作品データの再取得
```bash
cd ~/av-hakase.com/public_html
php batch/fetch_fanza.php
php batch/clear_cache.php
```

## ローカル開発

### 開発サーバー起動
```bash
cd /Users/kaikyotaro/repository/video-meikan/meikan
php -S localhost:8000 dev-server.php
```

### Git push（ローカルで実行）
```bash
cd /Users/kaikyotaro/repository/video-meikan
git add -A
git commit -m "変更内容"
git push origin main
```

### MySQL起動（ローカル開発用）
```bash
/opt/homebrew/opt/mysql/bin/mysqld --user=$(whoami) --datadir=/tmp/mysql_meikan_data --socket=/tmp/mysql_meikan.sock --port=3307 &
```
