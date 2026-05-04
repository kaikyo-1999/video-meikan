<footer class="footer">
    <div class="footer__inner container">
        <nav class="footer__nav">
            <a class="footer__nav-link" href="<?= h(url('author/')) ?>">著者について</a>
            <a class="footer__nav-link" href="<?= h(url('cross-link/')) ?>">相互リンク</a>
        </nav>
        <p class="footer__copyright">&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?> All Rights Reserved.</p>
    </div>
</footer>
