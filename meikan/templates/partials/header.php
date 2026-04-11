<header class="header">
    <div class="header__inner container">
        <a href="<?= url() ?>" class="header__logo"><?= h(SITE_NAME) ?></a>
        <button class="header__hamburger" aria-label="メニューを開く" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <nav class="header__nav" id="globalNav">
            <a href="<?= url('meikan/') ?>" class="header__nav-link">名鑑</a>
            <a href="<?= url('article/') ?>" class="header__nav-link">記事一覧</a>
            <a href="<?= url('author/') ?>" class="header__nav-link">運営者</a>
        </nav>
        <form class="header__search" action="<?= url('meikan/') ?>" method="get" role="search">
            <input type="search" name="q" class="header__search-input" placeholder="フリーワード" value="<?= isset($_GET['q']) ? h($_GET['q']) : '' ?>">
            <button type="submit" class="header__search-btn">検索</button>
        </form>
    </div>
</header>
