<div class="fc2-submit">
    <h1 class="page-title">FC2作品を投稿</h1>
    <p class="page-description">7桁のCIDを入力して作品をランキングに追加できます。投稿は承認後に公開されます。</p>

    <?php if (!empty($success)): ?>
        <div class="fc2-submit__message fc2-submit__message--success">
            <?= h($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="fc2-submit__message fc2-submit__message--error">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form class="fc2-submit__form" method="post" action="<?= url('fc2/submit/') ?>">
        <div class="fc2-submit__field">
            <label class="fc2-submit__label" for="fc2_cid">FC2 CID（7桁の数字）</label>
            <input
                class="fc2-submit__input"
                type="text"
                id="fc2_cid"
                name="cid"
                placeholder="例: 1234567"
                pattern="\d{7}"
                maxlength="7"
                required
                autocomplete="off"
            >
            <p class="fc2-submit__hint">FC2コンテンツページのURLに含まれる7桁の数字です。例: <code>adult.contents.fc2.com/article/<strong>1234567</strong>/</code></p>
        </div>
        <button class="fc2-submit__submit" type="submit">投稿する</button>
    </form>

    <p class="fc2-submit__back"><a href="<?= url('fc2/') ?>">&larr; ランキングに戻る</a></p>
</div>
