<?php
/**
 * お気に入り一覧ページ
 *
 * 表示はクライアントサイドで localStorage を読み込んで JS で描画する。
 * SSRでは骨組みだけ出力。
 */
?>
<h1 class="favorites-h1">お気に入り</h1>

<div class="favorites-intro">
    <span class="favorites-intro__badge">⚠ 注意</span>
    <strong>お気に入りはこの端末のブラウザに保存されています。</strong>
    端末を変えたりブラウザの履歴を消すと消えてしまうので注意！
</div>

<section class="favorites-page__section" data-favorites-section="actresses">
    <h2 class="favorites-page__section-title">
        お気に入りの女優<span class="favorites-page__count" data-favorites-count="actresses">(0)</span>
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
        お気に入りの作品<span class="favorites-page__count" data-favorites-count="works">(0)</span>
    </h2>
    <div data-favorites-list="works">
        <div class="favorites-page__empty" data-favorites-empty="works">
            <div class="favorites-page__empty-icon">📼</div>
            <p class="favorites-page__empty-title">まだお気に入りの作品がありません！</p>
            <p class="favorites-page__empty-text">女優ページの作品カード右上の ♥ から登録できます</p>
        </div>
    </div>
</section>

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
            // 空状態に戻す: empty-stateを再描画
            if (!container.querySelector('[data-favorites-empty]')) {
                container.innerHTML = '<div class="favorites-page__empty" data-favorites-empty="actresses">'
                    + '<div class="favorites-page__empty-icon">💔</div>'
                    + '<p class="favorites-page__empty-title">まだお気に入りの女優がいません！</p>'
                    + '<p class="favorites-page__empty-text">気になる女優を ♥ でストックしておくと、新作チェックがラクになります</p>'
                    + '<a href="<?= url('meikan/') ?>" class="favorites-page__empty-cta">→ 名鑑から女優を探す</a>'
                    + '</div>';
            }
            return;
        }

        var html = '<ul class="favorites-page__actress-grid">';
        for (var i = 0; i < list.length; i++) {
            html += renderActressItem(list[i], i);
        }
        html += '</ul>';
        container.innerHTML = html;
        container.querySelectorAll('[data-favorite-item-link]').forEach(bindClickEvent);
        container.querySelectorAll('[data-fav-remove]').forEach(bindRemoveLongPress);
    }

    function renderActressItem(item, position) {
        var safeSlug = String(item.slug).replace(/[<>"']/g, '');
        var url = '/' + encodeURIComponent(safeSlug) + '/';
        var displayName = item.name ? escapeHtml(item.name) : safeSlug;
        var visual = item.thumbnail
            ? '<img class="favorites-page__item-thumb" src="' + escapeHtml(item.thumbnail) + '" alt="" loading="lazy">'
            : '<div class="favorites-page__item-heart-fallback">♥</div>';
        return '<li class="favorites-page__actress-item">'
            + '<a href="' + url + '" class="favorites-page__item-link" data-favorite-item-link data-fav-type="actress" data-fav-id="' + safeSlug + '" data-fav-position="' + position + '">'
            + visual
            + '<span class="favorites-page__item-name">' + displayName + '</span>'
            + '</a>'
            + '<button type="button" class="favorites-page__remove" data-fav-remove data-fav-type="actress" data-fav-id="' + safeSlug + '" data-fav-name="' + displayName + '" aria-label="お気に入りから削除（長押し）"><span aria-hidden="true">×</span></button>'
            + '</li>';
    }

    function renderWorks() {
        var list = window.AvHakaseFavorites.list('work');
        var container = document.querySelector('[data-favorites-list="works"]');
        var countEl = document.querySelector('[data-favorites-count="works"]');
        countEl.textContent = '(' + list.length + ')';

        if (list.length === 0) {
            if (!container.querySelector('[data-favorites-empty]')) {
                container.innerHTML = '<div class="favorites-page__empty" data-favorites-empty="works">'
                    + '<div class="favorites-page__empty-icon">📼</div>'
                    + '<p class="favorites-page__empty-title">まだお気に入りの作品がありません！</p>'
                    + '<p class="favorites-page__empty-text">女優ページの作品カード右上の ♥ から登録できます</p>'
                    + '</div>';
            }
            return;
        }

        var html = '<ul class="favorites-page__work-list">';
        for (var i = 0; i < list.length; i++) {
            html += renderWorkItem(list[i], i);
        }
        html += '</ul>';
        container.innerHTML = html;
        container.querySelectorAll('[data-favorite-item-link]').forEach(bindClickEvent);
        container.querySelectorAll('[data-fav-remove]').forEach(bindRemoveLongPress);
    }

    function renderWorkItem(item, position) {
        var safeCid = String(item.cid).replace(/[<>"']/g, '');
        var fanzaUrl = 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=' + safeCid + '/';
        var thumb = item.thumbnail || ('https://pics.dmm.co.jp/digital/video/' + safeCid + '/' + safeCid + 'pl.jpg');
        var displayName = item.name ? escapeHtml(item.name) : safeCid;
        return '<li class="favorites-page__work-item">'
            + '<a href="' + fanzaUrl + '" target="_blank" rel="nofollow noopener" class="favorites-page__item-link" data-favorite-item-link data-fav-type="work" data-fav-id="' + safeCid + '" data-fav-position="' + position + '" data-destination="fanza">'
            + '<img src="' + escapeHtml(thumb) + '" alt="" loading="lazy">'
            + '<span class="favorites-page__item-name">' + displayName + '</span>'
            + '</a>'
            + '<button type="button" class="favorites-page__remove" data-fav-remove data-fav-type="work" data-fav-id="' + safeCid + '" data-fav-name="' + displayName + '" aria-label="お気に入りから削除（長押し）"><span aria-hidden="true">×</span></button>'
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

    // === 長押し削除 + 確認モーダル ===
    var LONG_PRESS_MS = 600;

    function bindRemoveLongPress(btn) {
        if (btn.dataset.longPressBound === '1') return;
        btn.dataset.longPressBound = '1';

        var timer = null;

        function start(e) {
            // 主ボタン以外無視
            if (e.button !== undefined && e.button !== 0) return;
            btn.classList.add('is-pressing');
            timer = setTimeout(function () {
                timer = null;
                btn.classList.remove('is-pressing');
                if (navigator.vibrate) { try { navigator.vibrate(30); } catch (e) {} }
                openConfirmModal({
                    name: btn.dataset.favName || btn.dataset.favId || '',
                    onConfirm: function () { removeAndRerender(btn); }
                });
            }, LONG_PRESS_MS);
        }

        function cancel() {
            btn.classList.remove('is-pressing');
            if (timer) { clearTimeout(timer); timer = null; }
        }

        btn.addEventListener('mousedown', start);
        btn.addEventListener('touchstart', start, { passive: true });
        btn.addEventListener('mouseup', cancel);
        btn.addEventListener('mouseleave', cancel);
        btn.addEventListener('touchend', cancel);
        btn.addEventListener('touchcancel', cancel);
        // 短いクリックで何も起きないように（誤操作防止）
        btn.addEventListener('click', function (e) { e.preventDefault(); });
        // モバイルの contextmenu (long-press) を抑制
        btn.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    }

    function removeAndRerender(btn) {
        if (!window.AvHakaseFavorites) return;
        if (window.AvHakaseFavorites.remove(btn.dataset.favType, btn.dataset.favId)) {
            if (btn.dataset.favType === 'actress') renderActresses();
            else renderWorks();
            if (window.AvHakaseFavorites.updateHeaderBadge) {
                window.AvHakaseFavorites.updateHeaderBadge();
            }
        }
    }

    function ensureModal() {
        if (document.getElementById('favConfirmModal')) return;
        var html = '<div class="confirm-modal" id="favConfirmModal" hidden role="dialog" aria-modal="true" aria-labelledby="favConfirmTitle">'
            + '<div class="confirm-modal__backdrop" data-confirm-cancel></div>'
            + '<div class="confirm-modal__card">'
            + '<p class="confirm-modal__title" id="favConfirmTitle">本当に削除してもいい？</p>'
            + '<p class="confirm-modal__desc">「<span data-confirm-name></span>」をお気に入りから削除します</p>'
            + '<div class="confirm-modal__buttons">'
            + '<button type="button" class="confirm-modal__btn confirm-modal__btn--cancel" data-confirm-cancel>キャンセル</button>'
            + '<button type="button" class="confirm-modal__btn confirm-modal__btn--ok" data-confirm-ok>削除する</button>'
            + '</div>'
            + '</div>'
            + '</div>';
        document.body.insertAdjacentHTML('beforeend', html);
    }

    var modalCurrentOnConfirm = null;
    var modalKeyHandler = null;

    function openConfirmModal(opts) {
        ensureModal();
        var modal = document.getElementById('favConfirmModal');
        var nameEl = modal.querySelector('[data-confirm-name]');
        if (nameEl) nameEl.textContent = opts.name || '';
        modal.removeAttribute('hidden');
        modalCurrentOnConfirm = opts.onConfirm || null;

        // ボタン/backdropクリックで close (一度だけ全体に登録)
        if (!modal.dataset.bound) {
            modal.dataset.bound = '1';
            modal.addEventListener('click', function (e) {
                var ok = e.target.closest('[data-confirm-ok]');
                var cancel = e.target.closest('[data-confirm-cancel]');
                if (ok) {
                    var fn = modalCurrentOnConfirm;
                    closeConfirmModal();
                    if (typeof fn === 'function') fn();
                } else if (cancel) {
                    closeConfirmModal();
                }
            });
        }

        // ESCで閉じる
        modalKeyHandler = function (e) {
            if (e.key === 'Escape') closeConfirmModal();
        };
        document.addEventListener('keydown', modalKeyHandler);
    }

    function closeConfirmModal() {
        var modal = document.getElementById('favConfirmModal');
        if (modal) modal.setAttribute('hidden', '');
        modalCurrentOnConfirm = null;
        if (modalKeyHandler) {
            document.removeEventListener('keydown', modalKeyHandler);
            modalKeyHandler = null;
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]);
        });
    }
})();
</script>
