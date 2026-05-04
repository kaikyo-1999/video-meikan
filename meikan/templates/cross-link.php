<?php $headingText = '相互リンク'; require TEMPLATE_DIR . '/partials/section-heading.php'; ?>

<div class="cross-link">
    <p class="cross-link__intro">AV博士と相互リンクして頂いているおすすめサイトの一覧です。</p>

    <ul class="cross-link__list">
        <?php foreach ($links as $link): ?>
        <li class="cross-link__item">
            <a class="cross-link__name" href="<?= h($link['url']) ?>" target="_blank" rel="noopener">
                <?= h($link['name']) ?>
            </a>
            <p class="cross-link__desc"><?= h($link['description']) ?></p>
        </li>
        <?php endforeach; ?>
    </ul>

    <h2 class="cross-link__section-title">相互リンクについて</h2>
    <p>当サイトと相互リンクをご希望のサイト様は、下記いずれかの方法でご連絡ください。サイトの内容を確認のうえ、当サイトの趣旨と合うサイト様を掲載させて頂きます。</p>

    <ul class="cross-link__contact-list">
        <li>メール：<a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a></li>
    </ul>

    <p class="cross-link__note">
        ※ 日本の法律に違反しているサイト・極端に内容がかけ離れているサイトは掲載をお断りする場合があります。<br>
        ※ ご連絡頂く際は、貴サイトの「サイト名・URL・説明文」をお知らせください。
    </p>

    <h2 class="cross-link__section-title">当サイトのリンク情報</h2>
    <p>下記テキストをそのままお使い頂けます。</p>

    <dl class="cross-link__info">
        <dt>サイト名</dt>
        <dd><?= h(SITE_NAME) ?></dd>
        <dt>URL</dt>
        <dd><a href="<?= h($siteUrl) ?>"><?= h($siteUrl) ?></a></dd>
        <dt>説明文</dt>
        <dd>AV女優をジャンル別に検索できるデータベースサイト。女優×ジャンルの組み合わせから理想の作品を見つけられます。</dd>
    </dl>
</div>
