<?php
require __DIR__ . '/../../src/bootstrap-local.php';
$db = db();
$slug = trim($_GET['slug'] ?? '');
$article = null;
if ($slug) {
    $s = $db->prepare("SELECT a.*,d.name department_name,g.name group_name,m.storage_path cover_path FROM articles a LEFT JOIN departments d ON d.id=a.department_id LEFT JOIN groups g ON g.id=a.group_id LEFT JOIN media m ON m.id=a.cover_image_id WHERE a.slug=? AND a.active=1 AND a.published_at IS NOT NULL");
    $s->execute([$slug]);
    $article = $s->fetch();
    if (!$article) {
        http_response_code(404);
        exit('Bericht nicht gefunden.');
    }
} else {
    $articles = $db->query("SELECT a.*,d.name department_name,g.name group_name,m.storage_path cover_path FROM articles a LEFT JOIN departments d ON d.id=a.department_id LEFT JOIN groups g ON g.id=a.group_id LEFT JOIN media m ON m.id=a.cover_image_id WHERE a.active=1 AND a.published_at IS NOT NULL ORDER BY datetime(a.published_at) DESC LIMIT 30")->fetchAll();
}
$crest = '/assets/svf-wappen.png';
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>
        <?= e($article['title'] ?? 'Aktuelles') ?> – SV Frohnstetten
    </title>
    <meta name="description" content="Aktuelles, Berichte und Neuigkeiten des SV Frohnstetten.">
    <link rel="stylesheet" href="/assets/app.css">
</head>

<body>
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="/">
                <img src="<?= $crest ?>" alt="SV Frohnstetten Wappen">
                <span><b>SV FROHNSTETTEN</b>
                    <small>GEMEINSAM. SPORT. ZUSAMMENHALT.</small></span></a>
            <nav>
                <a href="/">Verein</a>
                <a href="/verein/">Unser Verein</a><a href="/sport/?bereich=fussball">Fußball</a>
                <a href="/sport/?bereich=volleyball">Volleyball</a>
                <a href="/sport/?bereich=turnen-gymnastik">Turnen</a>
                <a href="/sport/?bereich=leichtathletik">Leichtathletik</a>
                <a href="/news/">Aktuelles</a>
            </nav>
            <a class="mobile-menu" href="/sport/"><span class="menu-bars" aria-hidden="true"></span></a>
        </div>
    </header>
    <main>
        <?php if ($article): ?>
            <section class="page-hero">
                <div class="container">
                    <p class="eyebrow light">AKTUELLES<?php if ($article['group_id']): ?> ·
                            <?= e(group_label($db, (int) $article['group_id'], true)) ?>    <?php endif; ?></p>
                    <h1><?= e($article['title']) ?>
                    </h1>
                    <p><?= e(date('d.m.Y', strtotime($article['published_at']))) ?>
                    </p>
                </div>
            </section>
            <section class="section">
                <div class="container article-layout">
                    <article class="article-card">
                        <?php if ($article['cover_path']): ?>
                            <img class="article-cover" src="/<?= e($article['cover_path']) ?>" alt="">
                        <?php endif; ?>
                        <div class="article-content">
                            <?= nl2br(e($article['content'])) ?>
                        </div>
                    </article>
                </div>
            </section>
        <?php else: ?>
            <section class="page-hero">
                <div class="container">
                    <p class="eyebrow light">SV FROHNSTETTEN</p>
                    <h1>Aktuelles</h1>
                    <p>Berichte, Neuigkeiten und Geschichten aus unserem Verein.</p>
                </div>
            </section>
            <section class="section">
                <div class="container">
                    <div class="news-page-grid">
                        <?php foreach ($articles as $a): ?>
                            <a class="news-page-card" href="/news/<?= e($a['slug']) ?>/">
                                <?php if ($a['cover_path']): ?>
                                    <img src="/<?= e($a['cover_path']) ?>" alt=""><?php else: ?>
                                    <div class="news-page-placeholder">SVF</div>
                                <?php endif; ?>
                                <div>
                                    <small>
                                        <?= e(date('d.m.Y', strtotime($a['published_at']))) ?>
                                    </small><?php if ($a['group_id']): ?>
                                        <span><?= e(group_label($db, (int) $a['group_id'], true)) ?>
                                        </span>
                                    <?php endif; ?>
                                    <h2><?= e($a['title']) ?></h2>
                                    <p><?= e($a['excerpt']) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$articles): ?>
                        <div class="empty-panel">
                            <h2>Noch keine Berichte.</h2>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <footer>
        <div class="container footer-grid">
            <div>
                <div class="footer-brand">
                    <img src="<?= $crest ?>" alt="">
                    <div>
                        <strong>SV Frohnstetten 1912/49 e.V.</strong>
                        <p>Blau & Weiß. Seit 1912/49.</p>
                    </div>
                </div>
            </div>
            <div class="footer-legal"><a href="/verein/?seite=impressum">Impressum</a><a href="/verein/?seite=datenschutz">Datenschutz</a></div>
            <div class="footer-login">
                <a href="/login/">Login</a>
            </div>
        </div>
    </footer>
</body>

</html>