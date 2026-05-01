<?php
/**
 * お気に入りボタン共通パーシャル
 *
 * Required:
 *   $favType   "actress" | "work"
 *   $favId     女優slug or 作品cid
 * Optional:
 *   $favName    item_name（GA4送信用、ない場合は空でOK）
 *   $favSource  source_component（"actress_header" / "work_card_v2" 等）
 *   $favVariant "default" | "overlay"（カード重ね表示用）
 *
 * 状態（is-active）はクライアント側で localStorage 参照して付与する。
 * SSR では未登録状態のmarkupを出力する。
 */
$favType    = $favType ?? 'actress';
$favId      = $favId ?? '';
$favName    = $favName ?? '';
$favThumb   = $favThumb ?? '';
$favSource  = $favSource ?? 'unknown';
$favVariant = $favVariant ?? 'default';
if ($favId === '') return;
$btnClass = 'fav-btn' . ($favVariant === 'overlay' ? ' fav-btn--overlay' : '');
?>
<button
    type="button"
    class="<?= $btnClass ?>"
    data-favorite-btn
    data-favorite-type="<?= h($favType) ?>"
    data-favorite-id="<?= h($favId) ?>"
    data-favorite-name="<?= h($favName) ?>"
    <?php if ($favThumb !== ''): ?>data-favorite-thumb="<?= h($favThumb) ?>"<?php endif; ?>
    data-favorite-source="<?= h($favSource) ?>"
    aria-pressed="false"
    aria-label="お気に入りに追加"
>
    <span class="fav-btn__icon" aria-hidden="true">
        <svg class="fav-btn__icon-outline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <svg class="fav-btn__icon-fill" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="100%" height="100%" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
    </span>
    <span class="fav-btn__label">お気に入り</span>
</button>
