<?php $headingText = '著者について'; require TEMPLATE_DIR . '/partials/section-heading.php'; ?>

<div class="author-profile">
    <div class="author-profile__header">
        <div class="author-profile__avatar">博</div>
        <div class="author-profile__main">
            <h1 class="author-profile__name">av博士</h1>
            <p class="author-profile__tagline">月1万課金が止められない独身AVオタク</p>
        </div>
    </div>

    <div class="author-profile__body">
        <h2 class="author-profile__section-title">何者？</h2>
        <p>どうも、av博士です。都内でひとり暮らしをしている30代の会社員です。彼女はいません。いない歴はご想像にお任せします。</p>

        <p>趣味はAV鑑賞。…いや、趣味というか、もはや生活の一部です。仕事から帰ってきて、風呂入って、ビール片手にFANZAを開く。この流れが崩れると逆に体調を崩します。たぶん。</p>

        <p>気づけば毎月1万円以上をAVに注ぎ込むようになり、年間の視聴本数は500本を余裕で超えるようになりました。友達に「お前それもう研究だろ」と言われたので、じゃあ博士を名乗ろうと。そんな軽いノリでこのサイトが生まれました。</p>

        <h2 class="author-profile__section-title">こんな特技があります</h2>
        <ul class="author-profile__list">
            <li><strong>品番を言われればどの作品か即答できます。</strong>「SSIS-834」って言われたら「三上悠亜のラスト作品ね」って0.5秒で返せます。飲み会で披露しても誰も喜びませんでした。</li>
            <li><strong>素人モノでも出演女優が分かります。</strong>モザイクの向こう側に誰がいるか、体のライン・仕草・声で判別できます。友人には「その能力もっと別のことに使え」と言われました。正論です。</li>
            <li><strong>サムネイルだけで地雷作品を回避できます。</strong>長年の経験で培った「ハズレセンサー」が発動します。的中率は体感8割くらい。残り2割は勉強代だと思ってます。</li>
            <li><strong>女優の移籍先を高確率で予想できます。</strong>SNSの動きとか撮影スタジオの写り込みとかで推測するんですが、これに関しては我ながらキモいと思ってます。</li>
        </ul>

        <h2 class="author-profile__section-title">なんでこのサイト作ったの？</h2>
        <p>きっかけは「三上悠亜のNTR作品だけまとめて見たいのに、探すのめんどくさすぎる」という極めて個人的な不満でした。</p>

        <p>AVって、同じ女優でもジャンルが違うとまるで別人なんですよ。清楚系の女優がNTRに出てると破壊力がヤバいし、ギャル系が人妻モノに出てるとギャップで脳がバグる。この「女優×ジャンル」の組み合わせこそが至高なのに、それをサクッと探せるサイトがなかった。</p>

        <p>じゃあ自分で作るか、と。会社員の副業スキルをフル活用して、誰にも頼まれてないのに勝手に作りました。</p>

        <h2 class="author-profile__section-title">好きなジャンル</h2>
        <div class="author-profile__tags">
            <span class="author-profile__tag">NTR</span>
            <span class="author-profile__tag">人妻</span>
            <span class="author-profile__tag">痴女</span>
            <span class="author-profile__tag">巨乳</span>
            <span class="author-profile__tag">お姉さん</span>
        </div>
        <p>NTRは人類が生み出した最高のフィクションだと思っています。異論は認めます。</p>

        <h2 class="author-profile__section-title">毎月どれくらい使ってるの？</h2>
        <ul class="author-profile__list">
            <li>単品購入（新作チェック用）：5,000〜8,000円</li>
            <li>見放題プラン：約3,000円</li>
            <li>深夜のテンションで買う衝動買い：2,000〜3,000円</li>
        </ul>
        <p>合計すると月1万〜1.5万円くらい。飲み会2回分だと思えば安い。…安くないか。まあでも、飲み会より確実に満足度は高いので良しとしています。</p>

        <p>ちなみに一番後悔した買い物は、酔った勢いで買った10本セットの福袋です。7本くらいハズレでした。福袋は買うな。</p>

        <h2 class="author-profile__section-title">最後に</h2>
        <p>このサイトが、あなたの夜のお供探しに少しでも役立てば嬉しいです。「このジャンルのおすすめ教えて」みたいなリクエストがあれば、記事にするかもしれません。たぶん。気が向いたら。</p>
    </div>
</div>

<?php $headingText = '最新記事'; require TEMPLATE_DIR . '/partials/section-heading.php'; ?>
<div class="article-list">
    <?php foreach ($articles as $article): ?>
    <a href="<?= h(url('articles/' . $article['slug'] . '/')) ?>" class="article-list-card">
        <div class="article-list-card__body">
            <?php if (!empty($article['category'])): ?>
            <span class="article-list-card__category"><?= h($article['category']) ?></span>
            <?php endif; ?>
            <h2 class="article-list-card__title"><?= h($article['title']) ?></h2>
            <time class="article-list-card__date"><?= h($article['published_at']) ?></time>
        </div>
    </a>
    <?php endforeach; ?>
</div>
