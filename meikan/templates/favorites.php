<?php
/**
 * お気に入り一覧ページ
 *
 * 表示はクライアントサイドで localStorage を読み込んで JS で描画する。
 * SSRでは骨組みだけ出力。
 */
?>
<h1 class="page-title">お気に入り</h1>

<div class="favorites-page__intro">
    <strong>※ お気に入りはこの端末のブラウザに保存されています。</strong>
    端末を変えたりブラウザ履歴を消すと消えてしまうため、将来 LINE 連携で同期できる仕組みを準備中です。
</div>

<section class="favorites-page__section" data-favorites-section="actresses">
    <h2 class="favorites-page__section-title">
        女優<span class="favorites-page__count" data-favorites-count="actresses">(0)</span>
    </h2>
    <div data-favorites-list="actresses">
        <div class="favorites-page__empty" data-favorites-empty="actresses">
            まだお気に入りに登録された女優はありません。<br>
            <a href="<?= url('meikan/') ?>">名鑑から女優を探す</a>
        </div>
    </div>
</section>

<section class="favorites-page__section" data-favorites-section="works">
    <h2 class="favorites-page__section-title">
        作品<span class="favorites-page__count" data-favorites-count="works">(0)</span>
    </h2>
    <div data-favorites-list="works">
        <div class="favorites-page__empty" data-favorites-empty="works">
            まだお気に入りに登録された作品はありません。<br>
            女優ページの作品カードからお気に入り登録できます。
        </div>
    </div>
</section>

<div class="favorites-page__future-cta">
    <div class="favorites-page__future-cta-title">🔔 将来予定: LINE 連携で端末をまたいで同期</div>
    LINE 連携機能をリリースすると、お気に入り登録した女優の<strong>新作が出たタイミングで通知が届く</strong>ようになります。準備中なのでお楽しみに。
</div>

<script>
(function () {
    if (!window.AvHakaseFavorites) {
        // favorites.js がまだ読み込まれていない場合は少し待つ
        document.addEventListener('DOMContentLoaded', initRender);
        return;
    }
    initRender();

    function initRender() {
        if (!window.AvHakaseFavorites) {
            setTimeout(initRender, 50);
            return;
        }
        sendListView();
        renderActresses();
        renderWorks();
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

        if (list.length === 0) return;

        var html = '<ul class="favorites-page__actress-grid">';
        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            html += renderActressItem(item, i);
        }
        html += '</ul>';
        container.innerHTML = html;
        container.querySelectorAll('[data-favorite-item-link]').forEach(bindClickEvent);
    }

    function renderActressItem(item, position) {
        var url = '/' + encodeURIComponent(item.slug) + '/';
        var safeSlug = String(item.slug).replace(/[<>"']/g, '');
        return '<li class="favorites-page__actress-item">'
            + '<a href="' + url + '" data-favorite-item-link data-fav-type="actress" data-fav-id="' + safeSlug + '" data-fav-position="' + position + '">'
            + safeSlug
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
            var item = list[i];
            html += renderWorkItem(item, i);
        }
        html += '</ul>';
        container.innerHTML = html;
        container.querySelectorAll('[data-favorite-item-link]').forEach(bindClickEvent);
    }

    function renderWorkItem(item, position) {
        var safeCid = String(item.cid).replace(/[<>"']/g, '');
        var fanzaUrl = 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=' + safeCid + '/';
        var thumb = 'https://pics.dmm.co.jp/digital/video/' + safeCid + '/' + safeCid + 'pl.jpg';
        return '<li class="favorites-page__work-item">'
            + '<a href="' + fanzaUrl + '" target="_blank" rel="nofollow noopener" data-favorite-item-link data-fav-type="work" data-fav-id="' + safeCid + '" data-fav-position="' + position + '" data-destination="fanza">'
            + '<img src="' + thumb + '" alt="" loading="lazy" width="120">'
            + '<span>' + safeCid + '</span>'
            + '</a>'
            + ' <button type="button" class="favorites-page__remove" data-fav-remove data-fav-type="work" data-fav-id="' + safeCid + '">削除</button>'
            + '</li>';
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
        if (!btn) return;
        e.preventDefault();
        if (window.AvHakaseFavorites.remove(btn.dataset.favType, btn.dataset.favId)) {
            if (btn.dataset.favType === 'actress') renderActresses();
            else renderWorks();
        }
    });
})();
</script>

<style>
.favorites-page__actress-grid,
.favorites-page__work-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.favorites-page__actress-item,
.favorites-page__work-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid var(--color-border);
}
.favorites-page__actress-item a,
.favorites-page__work-item a {
    color: var(--color-text);
    text-decoration: none;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 12px;
}
.favorites-page__actress-item a:hover,
.favorites-page__work-item a:hover {
    color: var(--color-link);
}
.favorites-page__work-item img {
    border-radius: 4px;
}
.favorites-page__remove {
    background: none;
    border: 1px solid var(--color-border);
    border-radius: 100px;
    color: var(--color-text-sub);
    font-size: 12px;
    padding: 4px 12px;
    cursor: pointer;
    flex-shrink: 0;
}
.favorites-page__remove:hover {
    border-color: var(--color-danger);
    color: var(--color-danger);
}
</style>
