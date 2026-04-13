// FC2 ランキング: 投票・コピー・お気に入り
document.addEventListener('DOMContentLoaded', function () {

    // --- いいね（投票）---
    document.querySelectorAll('.fc2-vote-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) return;

            var workId = btn.dataset.workId;
            if (!workId) return;

            btn.disabled = true;

            var formData = new FormData();
            formData.append('work_id', workId);

            fetch(BASE_URL + 'fc2/vote/', {
                method: 'POST',
                body: formData,
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    // 票数を親カードの表示に反映
                    var card = btn.closest('.fc2-work-card');
                    var countEl = card && card.querySelector('.fc2-work-card__vote-count');
                    if (countEl) countEl.textContent = data.vote_count + '票';

                    if (data.success || data.already) {
                        btn.classList.add('is-voted');
                    } else {
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                });
        });
    });

    // --- コピー ---
    document.querySelectorAll('.fc2-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cid = btn.dataset.cid;
            if (!cid) return;

            navigator.clipboard.writeText(cid).then(function () {
                btn.classList.add('is-copied');
                btn.title = 'コピーしました';
                setTimeout(function () {
                    btn.classList.remove('is-copied');
                    btn.title = '番号をコピー';
                }, 1500);
            });
        });
    });

    // --- お気に入り（localStorage）---
    var FAV_KEY = 'fc2_favorites';

    function getFavs() {
        try { return JSON.parse(localStorage.getItem(FAV_KEY) || '[]'); } catch (e) { return []; }
    }
    function saveFavs(favs) {
        localStorage.setItem(FAV_KEY, JSON.stringify(favs));
    }

    document.querySelectorAll('.fc2-fav-btn').forEach(function (btn) {
        var cid = btn.dataset.cid;
        if (getFavs().indexOf(cid) !== -1) {
            btn.classList.add('is-faved');
            btn.innerHTML = '&#x2605;'; // ★
        }

        btn.addEventListener('click', function () {
            var favs = getFavs();
            var idx  = favs.indexOf(cid);
            if (idx === -1) {
                favs.push(cid);
                btn.classList.add('is-faved');
                btn.innerHTML = '&#x2605;';
            } else {
                favs.splice(idx, 1);
                btn.classList.remove('is-faved');
                btn.innerHTML = '&#x2606;';
            }
            saveFavs(favs);
        });
    });
});
