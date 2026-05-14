<?php
/**
 * 作品グリッド内の挿入ロジック（共通）
 *
 * 入力:
 *   $globalIndex   現在の作品の通し番号（1始まり）
 *   $insertionMode 'actress' | 'genre'
 *   $actress       (両モード共通)
 *   $genre         ('genre'モード時)
 *   $similarActresses / $relatedActresses
 *   $debutActresses / $debutMonthLabel / $debutArticleSlug
 *   $hotActresses / $topGenreName ('actress'モード時、人気女優セクション見出し用)
 *   $otherGenres   ('genre'モード時)
 *
 * 挿入ルール:
 *   index 5      → 関連カード（URLハッシュで以下から1つ固定選択）
 *                  actress: similar / new / popular（similarには関連女優のフォールバックを含む）
 *                  genre  : other-genres / similar / related / new / popular
 *   index 9,13,17... (4件おき) → 広告（banner/widget 交互）
 */

if (($globalIndex - 9) >= 0 && ($globalIndex - 9) % 4 === 0) {
    $adNum = (($globalIndex - 9) / 4) + 1;
    $adSize = 'infeed';
    $adLabel = "インフィード広告 #{$adNum}";
    // バナー始まりで交互（odd=banner / even=widget）
    $adType = ($adNum % 2 === 1) ? 'banner' : 'widget';
    // 1番目（globalIndex 9）は即時、それ以降は viewport 接近時に遅延ロード
    $adLazy = ($adNum > 1);
    require __DIR__ . '/ad-slot.php';
    return;
}

if ($globalIndex !== 5) {
    return;
}

$mode = $insertionMode ?? 'actress';

// 候補は固定順序で並べる（ハッシュ結果の安定性のため）
$candidates = [];
if ($mode === 'genre' && !empty($otherGenres ?? [])) {
    $candidates[] = 'other-genres';
}
if (!empty($similarActresses ?? [])) {
    $candidates[] = 'similar';
}
if (!empty($relatedActresses ?? [])) {
    $candidates[] = 'related';
}
if (!empty($debutActresses ?? [])) {
    $candidates[] = 'new';
}
if (!empty($hotActresses ?? [])) {
    $candidates[] = 'popular';
}
if (empty($candidates)) {
    return;
}

// URL ハッシュで固定選択（同一ページは常に同じ候補）
$hashSource = $mode === 'genre'
    ? ($actress['slug'] . '/' . ($genre['slug'] ?? ''))
    : $actress['slug'];
$chosen = $candidates[crc32($hashSource) % count($candidates)];

switch ($chosen) {
    case 'other-genres':
        require __DIR__ . '/other-genres-inline.php';
        break;

    case 'similar':
        $inlineTitle = $actress['name'] . 'が好きな人にオススメ';
        $inlineItems = $similarActresses;
        $inlineCtaUrl = null;
        require __DIR__ . '/actresses-inline.php';
        break;

    case 'related':
        $inlineTitle = $actress['name'] . 'と似ているタイプの女優';
        $inlineItems = $relatedActresses;
        $inlineCtaUrl = null;
        require __DIR__ . '/actresses-inline.php';
        break;

    case 'new':
        $inlineTitle = !empty($debutMonthLabel)
            ? ($debutMonthLabel . 'デビューの新人女優')
            : '新人女優';
        $inlineItems = $debutActresses;
        $inlineCtaUrl = !empty($debutArticleSlug) ? url('article/' . $debutArticleSlug . '/') : null;
        $inlineCtaLabel = '全て見る';
        require __DIR__ . '/actresses-inline.php';
        break;

    case 'popular':
        if ($mode === 'genre' && !empty($genre['name'])) {
            $inlineTitle = '今人気の' . $genre['name'] . '女優';
        } elseif (!empty($topGenreName)) {
            $inlineTitle = '今人気の' . $topGenreName . '女優';
        } else {
            $inlineTitle = '今注目の人気女優';
        }
        $inlineItems = $hotActresses;
        $inlineCtaUrl = null;
        require __DIR__ . '/actresses-inline.php';
        break;
}
