<header class="header">
    <div class="header__inner container">
        <a href="<?= url() ?>" class="header__logo"><?= h(SITE_NAME) ?></a>
        <button class="header__hamburger" aria-label="メニューを開く" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <nav class="header__nav" id="globalNav">
            <a href="<?= url('articles/') ?>" class="header__nav-link">記事一覧</a>
        </nav>
    </div>
</header>
