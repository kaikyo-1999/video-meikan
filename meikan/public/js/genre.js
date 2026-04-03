/**
 * 作品一覧: 検索・ソート・フィルター・無限スクロール
 * ジャンルページ・女優ページ共用
 */
(function () {
    'use strict';

    var list = document.getElementById('workList');
    if (!list) return;

    var actressId = list.dataset.actressId;
    var genreId = list.dataset.genreId || '';
    var totalPages = parseInt(list.dataset.totalPages, 10) || 1;
    var currentPage = 1;
    var loading = false;

    var searchInput = document.getElementById('workSearch');
    var searchBtn = document.getElementById('workSearchBtn');
    var sortContainer = document.getElementById('workSort');
    var singleCheckbox = document.getElementById('workSingle');
    var noResults = document.getElementById('workNoResults');
    var loader = document.getElementById('infiniteLoader');

    var vrContainer = document.getElementById('workVr');
    var totalCountEl = document.getElementById('workTotalCount');

    var currentSort = 'rank';
    var currentQuery = '';
    var currentSingle = true;
    var currentVr = '';

    // --- API呼び出し ---
    function fetchWorks(page, append) {
        if (loading) return;
        loading = true;

        var params = new URLSearchParams({
            actress_id: actressId,
            page: page,
            sort: currentSort,
            q: currentQuery,
            single: currentSingle ? '1' : '0',
            vr: currentVr
        });

        if (genreId) {
            params.set('genre_id', genreId);
        }

        if (loader) loader.style.display = '';

        fetch(BASE_URL + 'api/works/?' + params.toString())
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (append) {
                    list.insertAdjacentHTML('beforeend', data.html);
                } else {
                    list.innerHTML = data.html;
                }

                totalPages = data.total_pages;
                currentPage = data.page;

                // 件数更新
                if (totalCountEl) {
                    totalCountEl.textContent = data.total;
                }

                // 結果なし表示
                if (noResults) {
                    noResults.style.display = data.total === 0 ? '' : 'none';
                }

                // ローダー表示制御
                if (loader) {
                    loader.style.display = currentPage >= totalPages ? 'none' : '';
                }

                loading = false;
            })
            .catch(function () {
                loading = false;
                if (loader) loader.style.display = 'none';
            });
    }

    // 条件変更時: 1ページ目からリセット
    function reload() {
        currentPage = 1;
        fetchWorks(1, false);
    }

    // --- 検索 ---
    if (searchBtn) {
        searchBtn.addEventListener('click', function () {
            currentQuery = searchInput.value.trim();
            reload();
        });
    }
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                currentQuery = searchInput.value.trim();
                reload();
            }
        });
    }

    // --- ソート ---
    if (sortContainer) {
        sortContainer.addEventListener('click', function (e) {
            var btn = e.target.closest('.sort-header__tab') || e.target.closest('.work-controls__pill');
            if (!btn) return;

            sortContainer.querySelectorAll('.sort-header__tab, .work-controls__pill').forEach(function (el) {
                el.classList.remove('is-active');
            });
            btn.classList.add('is-active');

            currentSort = btn.dataset.sort || '';
            reload();
        });
    }

    // --- VRフィルター ---
    if (vrContainer) {
        vrContainer.addEventListener('click', function (e) {
            var btn = e.target.closest('.work-controls__pill');
            if (!btn) return;

            vrContainer.querySelectorAll('.work-controls__pill').forEach(function (el) {
                el.classList.remove('is-active');
            });
            btn.classList.add('is-active');

            currentVr = btn.dataset.vr || '';
            reload();
        });
    }

    // --- 単体作品フィルター ---
    if (singleCheckbox) {
        singleCheckbox.addEventListener('change', function () {
            currentSingle = singleCheckbox.checked;
            reload();
        });
    }

    // --- 無限スクロール ---
    if (loader && 'IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting && !loading && currentPage < totalPages) {
                fetchWorks(currentPage + 1, true);
            }
        }, { rootMargin: '200px' });

        observer.observe(loader);
    }

})();
