<?php
/**
 * 作品リスト下部の「新人女優」「人気女優」セクション
 *
 * 入力:
 *   $debutActresses    array   新人女優（最大6名）
 *   $debutMonthLabel   string  例: "2026年4月"
 *   $debutArticleSlug  ?string デビュー記事へのリンク用
 *   $hotActresses      array   人気女優（最大6名）
 *   $insertionMode     'actress' | 'genre'
 *   $genre             array (genre モード時のみ)
 *   $actress           array
 *   $topGenreName      string (actress モード時のみ、人気女優のジャンル名)
 */
$mode = $insertionMode ?? 'actress';
if ($mode === 'genre' && isset($genre)) {
    $popularLabel = '今人気の' . $genre['name'] . '女優';
} elseif (!empty($topGenreName)) {
    $popularLabel = '今人気の' . $topGenreName . '女優';
} else {
    $popularLabel = '今注目の人気女優';
}
?>
<?php if (!empty($debutActresses)): ?>
    <?php
    $inlineTitle = ($debutMonthLabel ?? '') ? ($debutMonthLabel . 'デビューの新人女優') : '新人女優';
    $inlineItems = $debutActresses;
    $inlineCtaUrl = !empty($debutArticleSlug) ? url('article/' . $debutArticleSlug . '/') : null;
    $inlineCtaLabel = '全て見る';
    require __DIR__ . '/actresses-inline.php';
    ?>
<?php endif; ?>
<?php if (!empty($hotActresses)): ?>
    <?php
    $inlineTitle = $popularLabel;
    $inlineItems = $hotActresses;
    $inlineCtaUrl = null;
    $inlineCtaLabel = '';
    require __DIR__ . '/actresses-inline.php';
    ?>
<?php endif; ?>
