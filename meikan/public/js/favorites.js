/**
 * AV博士 お気に入り機能
 *
 * - localStorage ベース（ログイン不要）
 * - GA4 への (user, item, action, context) シグナル送信
 * - visitor_id は <head> インライン初期化に依存（layout.php 側）
 *
 * Public API:
 *   AvHakaseFavorites.getVisitorId()
 *   AvHakaseFavorites.isFavorite(itemType, itemId)
 *   AvHakaseFavorites.add(itemType, itemId, options)
 *   AvHakaseFavorites.remove(itemType, itemId)
 *   AvHakaseFavorites.toggle(itemType, itemId, options)
 *   AvHakaseFavorites.list(itemType)
 *   AvHakaseFavorites.sendEvent(name, params)
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'av-hakase:user';
    var SCHEMA_VERSION = 1;

    // ------------------------------------------------------------
    // Storage layer
    // ------------------------------------------------------------
    function loadRaw() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function save(data) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
            console.warn('[favorites] localStorage save failed', e);
        }
    }

    function uuidv4() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            var v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function ensureShape(data) {
        if (!data || typeof data !== 'object') {
            data = {};
        }
        if (data.v !== SCHEMA_VERSION) {
            // 将来のマイグレーションフック
            data.v = SCHEMA_VERSION;
        }
        if (!data.visitor_id) {
            data.visitor_id = uuidv4();
        }
        if (!data.first_visit_at) {
            data.first_visit_at = new Date().toISOString();
        }
        if (!data.favorites || typeof data.favorites !== 'object') {
            data.favorites = { actresses: [], works: [] };
        }
        if (!Array.isArray(data.favorites.actresses)) data.favorites.actresses = [];
        if (!Array.isArray(data.favorites.works)) data.favorites.works = [];
        return data;
    }

    function getOrInit() {
        var data = ensureShape(loadRaw());
        save(data);
        return data;
    }

    // ------------------------------------------------------------
    // Item helpers
    // ------------------------------------------------------------
    function getList(data, itemType) {
        return itemType === 'actress' ? data.favorites.actresses : data.favorites.works;
    }

    function getIdField(itemType) {
        return itemType === 'actress' ? 'slug' : 'cid';
    }

    function findIndex(list, idField, itemId) {
        for (var i = 0; i < list.length; i++) {
            if (list[i][idField] === itemId) return i;
        }
        return -1;
    }

    function totalFavorites(data) {
        return data.favorites.actresses.length + data.favorites.works.length;
    }

    // ------------------------------------------------------------
    // GA4 event sender
    // ------------------------------------------------------------
    function sendEvent(name, params) {
        if (typeof gtag === 'undefined') return;
        var clean = {};
        for (var key in params) {
            if (Object.prototype.hasOwnProperty.call(params, key)) {
                var val = params[key];
                if (val !== undefined && val !== null) {
                    clean[key] = val;
                }
            }
        }
        gtag('event', name, clean);
    }

    // ------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------
    var AvHakaseFavorites = {
        getVisitorId: function () {
            return getOrInit().visitor_id;
        },

        getUserData: function () {
            return getOrInit();
        },

        isFavorite: function (itemType, itemId) {
            var data = getOrInit();
            var list = getList(data, itemType);
            var idField = getIdField(itemType);
            return findIndex(list, idField, itemId) !== -1;
        },

        list: function (itemType) {
            var data = getOrInit();
            return getList(data, itemType).slice();
        },

        add: function (itemType, itemId, options) {
            options = options || {};
            var data = getOrInit();
            var list = getList(data, itemType);
            var idField = getIdField(itemType);

            if (findIndex(list, idField, itemId) !== -1) return false;

            var totalBefore = totalFavorites(data);
            var item = {};
            item[idField] = itemId;
            item.added_at = new Date().toISOString();
            item.source = options.source || 'unknown';
            if (options.itemName) item.name = options.itemName;
            if (options.thumbnail) item.thumbnail = options.thumbnail;
            list.push(item);
            save(data);

            sendEvent('added_favorite', {
                visitor_id: data.visitor_id,
                item_type: itemType,
                item_id: itemId,
                item_name: options.itemName || '',
                source_page_path: location.pathname,
                source_component: options.source || 'unknown',
                list_position: options.listPosition,
                referrer_item_id: options.referrerItemId,
                user_total_favorites_after: totalBefore + 1,
                user_first_favorite: totalBefore === 0,
                ms_since_page_load: typeof performance !== 'undefined' && performance.now ? Math.round(performance.now()) : null
            });

            // 3件目以降到達時に1度だけ「ブックマーク登録おすすめ」トースト表示
            maybeShowBookmarkPrompt(totalBefore + 1);

            return true;
        },

        remove: function (itemType, itemId) {
            var data = getOrInit();
            var list = getList(data, itemType);
            var idField = getIdField(itemType);

            var idx = findIndex(list, idField, itemId);
            if (idx === -1) return false;

            var item = list[idx];
            var secondsSinceAdd = null;
            if (item.added_at) {
                secondsSinceAdd = Math.round((Date.now() - new Date(item.added_at).getTime()) / 1000);
            }
            list.splice(idx, 1);
            save(data);

            sendEvent('removed_favorite', {
                visitor_id: data.visitor_id,
                item_type: itemType,
                item_id: itemId,
                seconds_since_add: secondsSinceAdd,
                user_total_favorites_after: totalFavorites(data)
            });

            return true;
        },

        toggle: function (itemType, itemId, options) {
            if (this.isFavorite(itemType, itemId)) {
                return this.remove(itemType, itemId) ? 'removed' : null;
            }
            return this.add(itemType, itemId, options) ? 'added' : null;
        },

        sendEvent: sendEvent
    };

    window.AvHakaseFavorites = AvHakaseFavorites;

    // ------------------------------------------------------------
    // DOM 連携
    // ------------------------------------------------------------
    function updateBtnState(btn, isFav) {
        btn.classList.toggle('is-active', isFav);
        btn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
        btn.setAttribute('aria-label', isFav ? 'お気に入りから削除' : 'お気に入りに追加');
        var titleEl = btn.querySelector('.fav-btn__label');
        if (titleEl) {
            titleEl.textContent = isFav ? '登録済み' : 'お気に入り';
        }
    }

    function showAddedFx(btn) {
        var fx = document.createElement('span');
        fx.className = 'fav-btn__fx';
        fx.textContent = '+1 ♥';
        btn.appendChild(fx);
        setTimeout(function () {
            if (fx.parentNode) fx.parentNode.removeChild(fx);
        }, 900);
    }

    function updateHeaderBadge() {
        var badges = document.querySelectorAll('[data-fav-badge]');
        if (!badges.length) return;
        var data = AvHakaseFavorites.getUserData();
        var total = data.favorites.actresses.length + data.favorites.works.length;
        for (var i = 0; i < badges.length; i++) {
            badges[i].textContent = total;
            badges[i].setAttribute('data-count', String(total));
        }
    }

    // ============================================================
    // ブックマーク登録 促進（toast + modal）
    // ============================================================
    var BOOKMARK_PROMPT_THRESHOLD = 3;
    var BOOKMARK_FLAG_KEY = 'av-hakase:bookmark_prompt_shown';

    function detectDevice() {
        var ua = navigator.userAgent || '';
        if (/iPhone|iPad|iPod/i.test(ua)) return 'ios';
        if (/Android/i.test(ua)) return 'android';
        if (/Mac/i.test(ua)) return 'mac';
        if (/Windows|Linux|X11/i.test(ua)) return 'win';
        return 'desktop';
    }

    function maybeShowBookmarkPrompt(totalAfter) {
        if (totalAfter < BOOKMARK_PROMPT_THRESHOLD) return;
        try {
            if (localStorage.getItem(BOOKMARK_FLAG_KEY)) return;
            localStorage.setItem(BOOKMARK_FLAG_KEY, '1');
        } catch (e) { return; }
        showBookmarkToast();
    }

    function showBookmarkToast() {
        // 既存のtoastがあれば消してから出し直し
        var prev = document.getElementById('bookmarkToast');
        if (prev) prev.parentNode.removeChild(prev);

        var html = '<div class="bookmark-toast" id="bookmarkToast" role="status">'
            + '<div class="bookmark-toast__inner">'
            + '<span class="bookmark-toast__icon" aria-hidden="true">📌</span>'
            + '<div class="bookmark-toast__body">'
            + '<strong>お気に入りが3件溜まりました！</strong>'
            + '消えないようにブックマーク登録もおすすめ'
            + '</div>'
            + '<button type="button" class="bookmark-toast__action" data-bookmark-toast-show>方法を見る</button>'
            + '<button type="button" class="bookmark-toast__close" data-bookmark-toast-close aria-label="閉じる">×</button>'
            + '</div>'
            + '</div>';
        document.body.insertAdjacentHTML('beforeend', html);

        var toast = document.getElementById('bookmarkToast');
        var actionBtn = toast.querySelector('[data-bookmark-toast-show]');
        var closeBtn = toast.querySelector('[data-bookmark-toast-close]');

        actionBtn.addEventListener('click', function () {
            sendEvent('clicked_bookmark_method', {
                visitor_id: AvHakaseFavorites.getVisitorId(),
                trigger: 'toast'
            });
            showBookmarkModal('toast');
            dismissBookmarkToast(false);
        });
        closeBtn.addEventListener('click', function () {
            sendEvent('dismissed_bookmark_prompt', {
                visitor_id: AvHakaseFavorites.getVisitorId(),
                trigger: 'toast_close'
            });
            dismissBookmarkToast(true);
        });

        // 12秒で自動消灯
        setTimeout(function () {
            if (document.getElementById('bookmarkToast')) {
                sendEvent('dismissed_bookmark_prompt', {
                    visitor_id: AvHakaseFavorites.getVisitorId(),
                    trigger: 'toast_timeout'
                });
                dismissBookmarkToast(true);
            }
        }, 12000);

        // 表示インプレッション
        sendEvent('viewed_bookmark_prompt', {
            visitor_id: AvHakaseFavorites.getVisitorId(),
            trigger: 'favorite_threshold',
            total_favorites: BOOKMARK_PROMPT_THRESHOLD
        });
    }

    function dismissBookmarkToast(animateOut) {
        var toast = document.getElementById('bookmarkToast');
        if (!toast) return;
        if (animateOut) {
            toast.classList.add('is-leaving');
            setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 250);
        } else {
            toast.parentNode.removeChild(toast);
        }
    }

    function ensureBookmarkModal() {
        if (document.getElementById('bookmarkModal')) return;
        var device = detectDevice();
        var item = function (key, label, body) {
            return '<li class="bookmark-modal__item' + (device === key ? ' is-current-device' : '') + '">'
                + '<span class="bookmark-modal__device">' + label + (device === key ? ' <em>(あなたの環境)</em>' : '') + '</span>'
                + '<p>' + body + '</p>'
                + '</li>';
        };
        var html = '<div class="bookmark-modal" id="bookmarkModal" hidden role="dialog" aria-modal="true" aria-labelledby="bookmarkModalTitle">'
            + '<div class="bookmark-modal__backdrop" data-bookmark-close></div>'
            + '<div class="bookmark-modal__card">'
            + '<h3 id="bookmarkModalTitle">📌 ブックマーク登録の方法</h3>'
            + '<p class="bookmark-modal__lead">いつでも博士に戻ってこれるように、ブックマーク or ホーム画面に追加！</p>'
            + '<ul class="bookmark-modal__list">'
            + item('ios',     '🍎 iPhone / iPad (Safari)',    '画面下の<strong>共有ボタン</strong>から<strong>「ホーム画面に追加」</strong>')
            + item('android', '🤖 Android (Chrome)',          '右上の<strong>メニュー (⋮)</strong> → <strong>「ホーム画面に追加」</strong>')
            + item('mac',     '💻 Mac (PC)',                  '<kbd>⌘</kbd> + <kbd>D</kbd> でブックマーク登録')
            + item('win',     '💻 Windows / Linux (PC)',      '<kbd>Ctrl</kbd> + <kbd>D</kbd> でブックマーク登録')
            + '</ul>'
            + '<button type="button" class="bookmark-modal__close" data-bookmark-close>閉じる</button>'
            + '</div>'
            + '</div>';
        document.body.insertAdjacentHTML('beforeend', html);

        var modal = document.getElementById('bookmarkModal');
        modal.addEventListener('click', function (e) {
            if (e.target.closest('[data-bookmark-close]')) {
                modal.setAttribute('hidden', '');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
                modal.setAttribute('hidden', '');
            }
        });
    }

    function showBookmarkModal(trigger) {
        ensureBookmarkModal();
        var modal = document.getElementById('bookmarkModal');
        modal.removeAttribute('hidden');
        // toastからのトリガは toast側で既にviewed送信済 → 重複防止
        if (trigger !== 'toast') {
            sendEvent('viewed_bookmark_prompt', {
                visitor_id: AvHakaseFavorites.getVisitorId(),
                trigger: trigger || 'manual'
            });
        }
    }

    // ページ内の [data-bookmark-show] ボタン全部に handler を bind
    function initBookmarkButtons(root) {
        root = root || document;
        root.querySelectorAll('[data-bookmark-show]').forEach(function (btn) {
            if (btn.dataset.bmBound === '1') return;
            btn.dataset.bmBound = '1';
            btn.addEventListener('click', function () {
                sendEvent('clicked_bookmark_method', {
                    visitor_id: AvHakaseFavorites.getVisitorId(),
                    trigger: btn.dataset.bookmarkSource || 'cta'
                });
                showBookmarkModal(btn.dataset.bookmarkSource || 'cta');
            });
        });
    }

    function bindButton(btn) {
        if (btn.dataset.favoriteBound === '1') return;
        btn.dataset.favoriteBound = '1';

        var itemType = btn.dataset.favoriteType;
        var itemId = btn.dataset.favoriteId;
        if (!itemType || !itemId) return;

        var itemName = btn.dataset.favoriteName || '';
        var source = btn.dataset.favoriteSource || 'unknown';
        var thumbnail = btn.dataset.favoriteThumb || '';

        updateBtnState(btn, AvHakaseFavorites.isFavorite(itemType, itemId));

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var result = AvHakaseFavorites.toggle(itemType, itemId, {
                source: source,
                itemName: itemName,
                thumbnail: thumbnail
            });
            var added = result === 'added';
            updateBtnState(btn, added);
            if (added) showAddedFx(btn);
            updateHeaderBadge();
        });
    }

    function initButtons(root) {
        root = root || document;
        var btns = root.querySelectorAll('[data-favorite-btn]');
        for (var i = 0; i < btns.length; i++) {
            bindButton(btns[i]);
        }
    }

    // GA4 user_properties に visitor_id を載せる（layout.php インライン側でも実施するが冗長安全）
    function setGtagUserProperty() {
        if (typeof gtag === 'undefined') return;
        try {
            gtag('set', 'user_properties', {
                visitor_id: AvHakaseFavorites.getVisitorId()
            });
        } catch (e) {
            // noop
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setGtagUserProperty();
            initButtons();
            updateHeaderBadge();
            initBookmarkButtons();
        });
    } else {
        setGtagUserProperty();
        initButtons();
        updateHeaderBadge();
        initBookmarkButtons();
    }

    // 動的に追加されたカード用：必要になったら明示呼び出し
    AvHakaseFavorites.initButtons = initButtons;
    AvHakaseFavorites.updateHeaderBadge = updateHeaderBadge;
    AvHakaseFavorites.showBookmarkModal = showBookmarkModal;
    AvHakaseFavorites.initBookmarkButtons = initBookmarkButtons;
})();
