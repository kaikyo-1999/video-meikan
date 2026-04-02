// Hamburger Menu
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.querySelector('.header__hamburger');
    var nav = document.getElementById('globalNav');
    if (!btn || !nav) return;

    btn.addEventListener('click', function () {
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));
        nav.classList.toggle('is-open');
    });
});

// Sidebar Filter Toggle (SP)
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('filterToggle');
    var sidebar = document.getElementById('filterSidebar');
    var overlay = document.getElementById('filterOverlay');
    var close = document.getElementById('filterClose');
    if (!toggle || !sidebar) return;

    function openSidebar() {
        sidebar.classList.add('is-open');
        if (overlay) overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('is-open');
        if (overlay) overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', openSidebar);
    if (close) close.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});

// Cast Table: もっと見る / 閉じる
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.cast-table-collapsible').forEach(function (wrap) {
        var btn = wrap.querySelector('.cast-table__toggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            wrap.classList.toggle('is-open');
        });
    });
});

// Sidebar Genre List: もっと見る / 閉じる
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sidebar-genre-list__toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var list = btn.previousElementSibling;
            var expanded = list.classList.toggle('is-expanded');
            btn.textContent = expanded ? '閉じる' : btn.dataset.label;
        });
        btn.dataset.label = btn.textContent;
    });
});

// Genre Grid: もっと見る / 閉じる
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.genre-grid__toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var grid = btn.previousElementSibling;
            var expanded = grid.classList.toggle('is-expanded');
            btn.textContent = expanded ? '閉じる' : 'もっと見る';
        });
    });
});
