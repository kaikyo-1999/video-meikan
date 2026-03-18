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
