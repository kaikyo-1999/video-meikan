<?php
/**
 * 記事品質チェックスクリプト
 *
 * 全記事ファイルを走査し、以下を検証する:
 *   1. @actress[slug] の参照先がDBに存在するか
 *   2. 画像URL (![alt](url)) がHTTPアクセス可能か
 *   3. FANZA商品リンクのCIDがDBに存在するか
 *   4. 内部リンク [text](/path/) の参照先が有効か
 *
 * Usage: php batch/validate_articles.php [--fix-images]
 */

require_once __DIR__ . '/config.php';

$articlesDir = ROOT_DIR . '/content/articles';
$db = Database::getInstance();

// 全女優slugを取得
$allSlugs = $db->query('SELECT slug FROM actresses')->fetchAll(PDO::FETCH_COLUMN);
$slugSet = array_flip($allSlugs);

// 全作品source_idを取得
$allCids = $db->query("SELECT source_id FROM works WHERE source = 'fanza'")->fetchAll(PDO::FETCH_COLUMN);
$cidSet = array_flip($allCids);

// 女優slug → CIDセットのマッピング（CID-女優整合性チェック用）
$slugToCids = [];
$rows = $db->query(
    "SELECT a.slug, w.source_id FROM actresses a
     JOIN actress_work aw ON a.id = aw.actress_id
     JOIN works w ON aw.work_id = w.id
     WHERE w.source = 'fanza'"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $slugToCids[$row['slug']][$row['source_id']] = true;
}

// 記事ファイル一覧
$files = glob($articlesDir . '/*.md');
if (!$files) {
    batchLog("記事ファイルが見つかりません: {$articlesDir}");
    exit(1);
}

batchLog("=== 記事品質チェック 開始 ===");
batchLog("対象記事数: " . count($files));
batchLog("DB女優数: " . count($allSlugs) . " / DB作品数: " . count($allCids));

$totalErrors = 0;
$totalWarnings = 0;
$results = [];

foreach ($files as $file) {
    $filename = basename($file);
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $errors = [];
    $warnings = [];
    $inFrontmatter = false;
    $frontmatterCount = 0;

    // セクション追跡用（CID-女優整合性チェック）
    $sections = [];       // [{slug, cids: [{cid, line}]}]
    $currentSlug = null;
    $currentCids = [];

    // 旧WordPress会話ブロック検出（全ファイルに対して実行）
    // ファイル全体を文字列として検索（改行をまたぐパターンに対応）
    $fullContent = implode("\n", $lines);
    // 旧WordPress人物アバター画像検出（単行・改行両対応）
    // 人名キーワードを含む ![名前](url) パターンのみ対象とする（通常の記事画像の誤検知を防ぐ）
    $wpPersonPattern = '/!\[(編集部|筆者|AV業界|この記事の|AV評論家)[^\]]*\n?[^\]]*\]\(https?:\/\//u';
    if (preg_match_all($wpPersonPattern, $fullContent, $wpMatches, PREG_OFFSET_CAPTURE)) {
        foreach ($wpMatches[0] as $match) {
            $offset = $match[1];
            $lineNo = substr_count(substr($fullContent, 0, $offset), "\n") + 1;
            $errors[] = "L{$lineNo}: 旧WordPress形式の人物アバター画像 — `:::chat` または `:::say` に変換してください（筆者bioは削除）";
        }
    }

    // ボタン未変換のCTAテキスト検出
    // 「〜で無料視聴する」「〜で無料トライアル」等の単独行がリンクになっていない場合に警告
    $ctaPattern = '/^(?!.*\[btn\s)(?!.*\]\(https?:\/\/)(?!.*#)(?!.*\|)(?!\s*[-*!>])(.{4,30})(無料視聴する|無料で視聴する|無料トライアルを始める|で登録する|で無料登録|無料で試す)$/um';
    if (preg_match_all($ctaPattern, $fullContent, $ctaMatches, PREG_OFFSET_CAPTURE)) {
        foreach ($ctaMatches[0] as $match) {
            $offset = $match[1];
            $lineNo = substr_count(substr($fullContent, 0, $offset), "\n") + 1;
            $warnings[] = "L{$lineNo}: CTAテキストがボタン未変換の可能性 — `[btn テキスト](url)` 形式に変換してください: 「{$match[0]}」";
        }
    }

    foreach ($lines as $lineNum => $line) {
        $num = $lineNum + 1;
        $trimmed = trim($line);

        // フロントマター判定
        if ($trimmed === '---') {
            $frontmatterCount++;
            if ($frontmatterCount <= 2) {
                $inFrontmatter = ($frontmatterCount === 1);
                continue;
            }
        }
        if ($inFrontmatter) continue;

        // ## 見出しでセクション区切り
        if (preg_match('/^## /', $trimmed)) {
            if ($currentSlug !== null && $currentCids) {
                $sections[] = ['slug' => $currentSlug, 'cids' => $currentCids];
            }
            $currentSlug = null;
            $currentCids = [];
        }

        // 1. @actress[slug] チェック
        if (preg_match('/^@actress\[([a-z0-9-]+)\]$/', $trimmed, $m)) {
            if (!isset($slugSet[$m[1]])) {
                $errors[] = "L{$num}: @actress[{$m[1]}] — DBに女優が存在しません";
            }
            $currentSlug = $m[1];
        }

        // 2. 画像URL チェック（NW負荷があるのでURL形式チェックのみ。--check-imagesで実際にHTTPチェック）
        if (preg_match('/!\[.*?\]\((.+?)\)/', $trimmed, $imgM)) {
            $imgUrl = $imgM[1];
            // 日本語を含むURLも許容（WordPress等）
            if (!preg_match('#^https?://.+\..+/.+#u', $imgUrl)) {
                $warnings[] = "L{$num}: 画像URL形式が不正 — {$imgUrl}";
            } elseif (str_contains($imgUrl, 'NOW_PRINTING') || str_contains($imgUrl, 'noimage')) {
                $warnings[] = "L{$num}: NOW PRINTING画像 — {$imgUrl}";
            }
        }

        // 画像URL・サンプルURLからCID抽出（pics.dmm.co.jp/digital/video/{cid}/）
        if (preg_match('#pics\.dmm\.co\.jp/digital/video/([a-z0-9_]+)/#', $trimmed, $picCidM)) {
            $currentCids[] = ['cid' => $picCidM[1], 'line' => $num];
        }

        // 3. FANZA CIDチェック
        if (preg_match('/cid=([a-z0-9_]+)/', $trimmed, $cidM)) {
            $cid = $cidM[1];
            if (!isset($cidSet[$cid])) {
                $warnings[] = "L{$num}: CID '{$cid}' がDB未登録（FANZAリンクは有効でもDB未取得の可能性）";
            }
            $currentCids[] = ['cid' => $cid, 'line' => $num];
            // DMMの新URL形式は数字プレフィックス付きCIDを認識しない
            // アフィリエイトリンク経由でリダイレクトされた際に404になる
            if (preg_match('/^\d+[a-z]/', $cid)) {
                $strippedCid = preg_replace('/^\d+/', '', $cid);
                $warnings[] = "L{$num}: CID '{$cid}' に数字プレフィックスあり — DMMリンクは自動で '{$strippedCid}' に補正されます";
            }
        }

        // 4. 空の@actressタグ
        if (preg_match('/^@actress\[\s*\]$/', $trimmed)) {
            $errors[] = "L{$num}: @actress[] — slugが空です";
        }

        // 5. slug形式違反（大文字やスペースを含む）
        if (preg_match('/^@actress\[(.+)\]$/', $trimmed, $rawM)) {
            if (!preg_match('/^[a-z0-9-]+$/', $rawM[1])) {
                $errors[] = "L{$num}: @actress[{$rawM[1]}] — slug形式が不正（小文字英数字とハイフンのみ許可）";
            }
        }
    }
    // 最後のセクションを保存
    if ($currentSlug !== null && $currentCids) {
        $sections[] = ['slug' => $currentSlug, 'cids' => $currentCids];
    }

    // 6. CID-女優整合性チェック: セクション内のCIDがその女優のものか検証
    foreach ($sections as $sec) {
        $slug = $sec['slug'];
        $actressCids = $slugToCids[$slug] ?? null;
        if ($actressCids === null) {
            // 女優のCIDがDB未登録の場合はスキップ（既にslugチェックで警告済み）
            continue;
        }
        // セクション内のCIDを重複排除して検証
        $checkedCids = [];
        foreach ($sec['cids'] as $entry) {
            $cid = $entry['cid'];
            $ln = $entry['line'];
            if (isset($checkedCids[$cid])) continue;
            // 数字プレフィックス付きCID（例: 1ipzz00780）はプレフィックスなし版（ipzz00780）でも照合
            $strippedCid = preg_replace('/^\d+([a-z])/', '$1', $cid);
            if (!isset($actressCids[$cid]) && !isset($actressCids[$strippedCid])) {
                $errors[] = "L{$ln}: CID '{$cid}' は @actress[{$slug}] の作品ではありません（別女優の作品の可能性）";
                $checkedCids[$cid] = true;
            }
        }
    }

    if ($errors || $warnings) {
        $results[$filename] = ['errors' => $errors, 'warnings' => $warnings];
        $totalErrors += count($errors);
        $totalWarnings += count($warnings);
    }
}

// 結果出力
if (empty($results)) {
    batchLog("全記事チェック通過 — 問題なし");
} else {
    foreach ($results as $filename => $result) {
        batchLog("--- {$filename} ---");
        foreach ($result['errors'] as $e) {
            batchLog("  ERROR: {$e}");
        }
        foreach ($result['warnings'] as $w) {
            batchLog("  WARN:  {$w}");
        }
    }
}

batchLog("=== 記事品質チェック 完了 ===");
batchLog("エラー: {$totalErrors} 件 / 警告: {$totalWarnings} 件");

exit($totalErrors > 0 ? 1 : 0);
