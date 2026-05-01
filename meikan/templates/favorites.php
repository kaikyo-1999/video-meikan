<?php
/**
 * お気に入り一覧ページ
 *
 * 表示はクライアントサイドで localStorage を読み込んで JS で描画する。
 * SSRでは骨組みだけ出力。
 *
 * 受け取る変数:
 *   $popularActresses: array (DB利用可時、人気女優Top5。離脱防止導線用)
 */
$popularActresses = $popularActresses ?? [];
?>
<h1 class="favorites-h1">❤️ お気に入り</h1>

<div class="favorites-intro">
    <span class="favorites-intro__badge">⚠ 注意</span>
    <strong>お気に入りはこの端末のブラウザに保存されています。</strong>
    端末を変えたりブラウザの履歴を消すと消えてしまうので注意！ 将来 LINE 連携で同期できる仕組みを準備中です。
</div>

<section class="favorites-page__section" data-favorites-section="actresses">
    <h2 class="favorites-page__section-title">
        💕 お気に入りの女優<span class="favorites-page__count" data-favorites-count="actresses">(0)</span>
    </h2>
    <div data-favorites-list="actresses">
        <div class="favorites-page__empty" data-favorites-empty="actresses">
            <div class="favorites-page__empty-icon">💔</div>
            <p class="favorites-page__empty-title">まだお気に入りの女優がいません！</p>
            <p class="favorites-page__empty-text">気になる女優を ♥ でストックしておくと、新作チェックがラクになります</p>
            <a href="<?= url('meikan/') ?>" class="favorites-page__empty-cta">→ 名鑑から女優を探す</a>
        </div>
    </div>
</section>

<section class="favorites-page__section" data-favorites-section="works">
    <h2 class="favorites-page__section-title">
        🎬 お気に入りの作品<span class="favorites-page__count" data-favorites-count="works">(0)</span>
    </h2>
    <div data-favorites-list="works">
        <div class="favorites-page__empty" data-favorites-empty="works">
            <div class="favorites-page__empty-icon">📼</div>
            <p class="favorites-page__empty-title">まだお気に入りの作品がありません！</p>
            <p class="favorites-page__empty-text">女優ページの作品カード右上の ♥ から登録できます</p>
        </div>
    </div>
</section>

<?php if (!empty($popularActresses)): ?>
<section class="favorites-page__section favorites-page__popular">
    <h2 class="favorites-page__section-title">
        🔥 みんなが見てる人気の女優TOP<?= count($popularActresses) ?>
    </h2>
    <ul class="favorites-popular__grid">
        <?php foreach ($popularActresses as $i => $a): ?>
        <li class="favorites-popular__item">
            <a href="<?= url(h($a['slug']) . '/') ?>" class="favorites-popular__link">
                <span class="favorites-popular__rank">#<?= $i + 1 ?></span>
                <?php if (!empty($a['thumbnail_url'])): ?>
                <img class="favorites-popular__thumb" src="<?= h($a['thumbnail_url']) ?>" alt="<?= h($a['name']) ?>" loading="lazy" width="80" height="80">
                <?php endif; ?>
                <div class="favorites-popular__info">
                    <span class="favorites-popular__name"><?= h($a['name']) ?></span>
                    <span class="favorites-popular__count">作品<?= (int)$a['work_count'] ?>本</span>
                </div>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<div class="favorites-page__future-cta">
    <div class="favorites-page__future-cta-ribbon">📢 NEW! 近日公開予定</div>
    <div class="favorites-page__future-cta-title">LINE 連携で端末をまたいで同期できるように！</div>
    <p>LINE 連携機能をリリースすると、お気に入り登録した女優の<strong>新作が出たタイミングでLINEに通知が届く</strong>ようになります。準備中なのでお楽しみに！</p>
</div>

<script>
(function () {
    function initRender() {
        if (!window.AvHakaseFavorites) {
            setTimeout(initRender, 50);
            return;
        }
        sendListView();
        renderActresses();
        renderWorks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRender);
    } else {
        initRender();
    }

    function sendListView() {
        if (typeof gtag === 'undefined') return;
        gtag('event', 'favorite_list_view', {
            visitor_id: window.AvHakaseFavorites.getVisitorId(),
            actresses_count: window.AvHakaseFavorites.list('actress').length,
            works_count: window.AvHakaseFavorites.list('work').length
        });
    }

    function renderActresses() {
        var list = window.AvHakaseFavorites.list('actress');
        var container = document.querySelector('[data-favorites-list="actresses"]');
        var countEl = document.querySelector('[data-favorites-count="actresses"]');
        countEl.textContent = '(' + list.length + ')';

        if (list.length === 0) {
            // 空状態のままにする
            return;
        }

        var html = '<ul class="favorites-page__actress-grid">';
        for (var i = 0; i < list.length; i++) {
            html += renderActressItem(list[i], i);
        }
        html += '</ul>';
        container.innerHTML = html;
        container.querySelectorAll('[data-favorite-item-link]').forEach(bindClickEvent);
    }

    function renderActressItem(item, position) {
        var url = '/' + encodeURIComponent(item.slug) + '/';
        var safeSlug = String(item.slug).replace(/[<>"']/g, '');
        var displayName = item.name ? escapeHtml(item.name) : safeSlug;
        var thumbHtml = item.thumbnail ? '<img class="favorites-page__item-thumb" src="' + escapeHtml(item.thumbnail) + '" alt="" loading="lazy" width="48" height="48">' : '<span class="favorites-page__item-heart">♥</span>';
        return '<li class="favorites-page__actress-item">'
            + '<a href="' + url + '" data-favorite-item-link data-fav-type="actress" data-fav-id="' + safeSlug + '" data-fav-position="' + position + '">'
            + thumbHtml
            + '<span class="favorites-page__item-name">' + displayName + '</span>'
            + '</a>'
            + ' <button type="button" class="favorites-page__remove" data-fav-remove data-fav-type="actress" data-fav-id="' + safeSlug + '">削除</button>'
            + '</li>';
    }

    function renderWorks() {
        var list = window.AvHakaseFavorites.list('work');
        var container = document.querySelector('[data-favorites-list="works"]');
        var countEl = document.querySelector('[data-favorites-count="works"]');
        countEl.textContent = '(' + list.length + ')';

        if (list.length === 0) return;

        var html = '<ul class="favorites-page__work-list">';
        for (var i = 0; i < list.length; i++) {
            html += renderWorkItem(list[i], i);
        }
        html += '</ul>';
        container.innerHTML = html;
        container.querySelectorAll('[data-favorite-item-link]').forEach(bindClickEvent);
    }

    function renderWorkItem(item, position) {
        var safeCid = String(item.cid).replace(/[<>"']/g, '');
        var fanzaUrl = 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=' + safeCid + '/';
        var thumb = 'https://pics.dmm.co.jp/digital/video/' + safeCid + '/' + safeCid + 'pl.jpg';
        var displayName = item.name ? escapeHtml(item.name) : safeCid;
        return '<li class="favorites-page__work-item">'
            + '<a href="' + fanzaUrl + '" target="_blank" rel="nofollow noopener" data-favorite-item-link data-fav-type="work" data-fav-id="' + safeCid + '" data-fav-position="' + position + '" data-destination="fanza">'
            + '<img src="' + thumb + '" alt="" loading="lazy" width="80" height="60">'
            + '<span class="favorites-page__item-name">' + displayName + '</span>'
            + '</a>'
            + ' <button type="button" class="favorites-page__remove" data-fav-remove data-fav-type="work" data-fav-id="' + safeCid + '">削除</button>'
            + '</li>';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]);
        });
    }

    function bindClickEvent(el) {
        el.addEventListener('click', function () {
            if (typeof gtag === 'undefined') return;
            gtag('event', 'favorite_item_click', {
                visitor_id: window.AvHakaseFavorites.getVisitorId(),
                item_type: el.dataset.favType,
                item_id: el.dataset.favId,
                list_position: parseInt(el.dataset.favPosition, 10),
                destination: el.dataset.destination || 'internal'
            });
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-fav-remove]');
        if (!btn || !window.AvHakaseFavorites) return;
        e.preventDefault();
        if (window.AvHakaseFavorites.remove(btn.dataset.favType, btn.dataset.favId)) {
            if (btn.dataset.favType === 'actress') renderActresses();
            else renderWorks();
            if (window.AvHakaseFavorites.updateHeaderBadge) {
                window.AvHakaseFavorites.updateHeaderBadge();
            }
        }
    });
})();
</script>
