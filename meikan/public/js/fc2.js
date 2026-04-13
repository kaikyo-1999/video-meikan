// FC2 ランキング: 投票ボタン
document.addEventListener('DOMContentLoaded', function () {
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
                    var countEl = btn.querySelector('.fc2-vote-btn__count');
                    if (countEl) countEl.textContent = data.vote_count;

                    if (data.success || data.already) {
                        btn.classList.add('is-voted');
                        // 「投票済み」ラベルがなければ追加
                        var card = btn.closest('.fc2-work-card__vote');
                        if (card && !card.querySelector('.fc2-vote-btn__label')) {
                            var label = document.createElement('span');
                            label.className = 'fc2-vote-btn__label';
                            label.textContent = '投票済み';
                            card.appendChild(label);
                        }
                    } else {
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                });
        });
    });
});
