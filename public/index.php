<?php
require __DIR__ . '/../src/bootstrap-local.php';
$db = db();
$deps = $db->query("SELECT * FROM departments WHERE active=1 ORDER BY sort_order,name")->fetchAll();
$news = $db->query("SELECT a.*,d.name department_name,g.name group_name,m.storage_path cover_path FROM articles a LEFT JOIN departments d ON d.id=a.department_id LEFT JOIN groups g ON g.id=a.group_id LEFT JOIN media m ON m.id=a.cover_image_id WHERE a.active=1 AND a.published_at IS NOT NULL ORDER BY datetime(a.published_at) DESC LIMIT 2")->fetchAll();
$events = $db->query("SELECT e.*,d.name department_name,g.name group_name FROM events e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN groups g ON g.id=e.group_id WHERE e.active=1 AND datetime(e.start_date)>=datetime('now') ORDER BY datetime(e.start_date) LIMIT 6")->fetchAll();
$football = $db->query("SELECT g.*,d.name department_name FROM groups g JOIN departments d ON d.id=g.department_id WHERE d.slug='fussball' AND g.active=1 ORDER BY g.sort_order,g.name")->fetchAll();
$footballIds = [];
foreach ($football as $fg) {
    $footballIds[(int) $fg['id']] = true;
}
$footballChildIds = [];
foreach ($football as $fg) {
    if ($fg['parent_id'])
        $footballChildIds[(int) $fg['parent_id']] = true;
}
$football = array_values(array_filter($football, fn($fg) => !isset($footballChildIds[(int) $fg['id']])));
$volleyball = $db->query("SELECT g.*,d.name department_name FROM groups g JOIN departments d ON d.id=g.department_id WHERE d.slug='volleyball' AND g.active=1 ORDER BY g.sort_order,g.name")->fetchAll();
$sponsors = $db->query("SELECT s.*,m.storage_path logo_path FROM sponsors s LEFT JOIN media m ON m.id=s.logo_media_id WHERE s.active=1 ORDER BY s.featured DESC,s.sort_order,s.name")->fetchAll();
$featuredSponsors = array_values(array_filter($sponsors, fn($s) => (int) ($s['featured'] ?? 0) === 1));
$featuredSponsors = array_slice($featuredSponsors, 0, 2);
$regularSponsors = array_values(array_filter($sponsors, fn($s) => (int) ($s['featured'] ?? 0) !== 1));
$footballDept = $db->query("SELECT * FROM departments WHERE slug='fussball' AND active=1 LIMIT 1")->fetch();
$footballPartners = [];
if ($footballDept) {
    $q = $db->prepare("SELECT * FROM department_partner_links WHERE department_id=? AND active=1 ORDER BY sort_order,name");
    $q->execute([(int) $footballDept['id']]);
    $footballPartners = $q->fetchAll();
}
function labelEvent($t)
{
    return ['spiel' => 'Spiel', 'training' => 'Training', 'veranstaltung' => 'Veranstaltung', 'termin' => 'Termin'][$t] ?? 'Termin';
}
function dt($s)
{
    return date('d.m.Y · H:i', strtotime($s));
}
function sport_svg(string $slug): string
{
    $icons = [
        'fussball' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="22" fill="none" stroke="currentColor" stroke-width="4"/><path d="M32 16l7 8-3 10h-8l-3-10 7-8zm-7 18l-10 7m19-7l10 7m-18 0l-4 10m11-10l4 10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>',
        'volleyball' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="22" fill="none" stroke="currentColor" stroke-width="4"/><path d="M15 25c12-7 24-7 36 0M18 43c7-10 17-16 31-17M28 53c-1-10 2-19 10-28" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>',
        'turnen-gymnastik' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="34" cy="11" r="5" fill="none" stroke="currentColor" stroke-width="4"/><path d="M31 17l-6 15 10 7 8-13m-18-3l-10-6m10 6l13-6m-13 22l-9 13m17-13l8 13" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'leichtathletik' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="40" cy="10" r="5" fill="none" stroke="currentColor" stroke-width="4"/><path d="M35 17l-9 13 8 8 12-7m-20-1l-12-4m12 4l11-9m-3 17l-14 12m22-12l10 11" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ];
    return $icons[$slug] ?? $icons['leichtathletik'];
}
$crest = '/assets/svf-wappen.png';
$footballBy = [];
foreach ($football as $g) {
    $path = group_path($db, (int) $g['id']);
    $top = $path[0]['name'] ?? $g['name'];
    $footballBy[$top][] = $g;
}
?><!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SV Frohnstetten 1912/49 e.V.</title>
    <meta name="description" content="SV Frohnstetten – Fußball, Volleyball, Turnen & Gymnastik und Leichtathletik in Frohnstetten.">
    <meta property="og:title" content="SV Frohnstetten 1912/49 e.V.">
    <meta property="og:description" content="Sport, Gemeinschaft und Engagement in Frohnstetten.">
    <link rel="stylesheet" href="/assets/app.css">
</head>

<body>
    <header class="site-header">
        <div class="container nav"><a class="brand" href="/"><img src="<?= $crest ?>"
                    alt="SV Frohnstetten Wappen"><span><b>SV FROHNSTETTEN</b><small>GEMEINSAM. SPORT.
                        ZUSAMMENHALT.</small></span></a>
            <nav><a href="/">Verein</a><a href="/verein/">Unser Verein</a><a
                    href="/sport/?bereich=fussball">Fußball</a><a href="/sport/?bereich=volleyball">Volleyball</a><a
                    href="/sport/?bereich=turnen-gymnastik">Turnen</a><a
                    href="/sport/?bereich=leichtathletik">Leichtathletik</a><a href="/news/">Aktuelles</a></nav><a
                class="mobile-menu" href="/sport/" aria-label="Sportbereiche"></a>
        </div>
    </header>
    <?php if ($featuredSponsors): ?>
        <section class="featured-sponsors">
            <div class="container featured-sponsors-inner"><span class="featured-sponsors-label">UNSERE
                    HAUPTSPONSOREN</span><?php foreach ($featuredSponsors as $sp): ?><a class="featured-sponsor"
                        href="<?= e($sp['website'] ?: '#') ?>" <?= ($sp['website'] ? 'target="_blank" rel="noopener"' : '') ?>><span
                            class="featured-sponsor-logo"><?php if ($sp['logo_path']): ?><img src="/<?= e($sp['logo_path']) ?>"
                                    alt="<?= e($sp['name']) ?>"><?php else: ?><strong><?= e($sp['name']) ?></strong><?php endif; ?></span></a><?php endforeach; ?>
            </div>
        </section><?php endif; ?>
    <section class="hero hero-home">
        <div class="hero-overlay"></div>
        <div class="container hero-inner">
            <p class="eyebrow light">SV FROHNSTETTEN 1912/49 e.V.</p>
            <h1>TEAMGEIST.<br><span>LEIDENSCHAFT.<br>HEIMAT.</span></h1>
            <p>SV Frohnstetten – mehr als ein Verein. Sport, Gemeinschaft und Engagement mitten in Frohnstetten.</p>
            <div class="hero-actions"><a class="button blue" href="/sport/">Sport entdecken →</a><a class="button ghost"
                    href="/news/">Aktuelles</a></div>
        </div>
    </section>
    <section class="sport-strip">
        <div class="container sport-strip-inner"><?php foreach ($deps as $d): ?><a
                    href="/sport/?bereich=<?= e($d['slug']) ?>"><span class="sport-graphic"
                        aria-hidden="true"><?= sport_svg($d['slug']) ?></span><b><?= e($d['name']) ?></b><small>→</small></a><?php endforeach; ?>
        </div>
    </section>
    <main>
        <?php if ($news): ?>
            <section class="section">
                <div class="container">
                    <div class="section-title">
                        <div>
                            <p class="eyebrow">AKTUELLES AUS DEM VEREIN</p>
                            <h2>Aktuelles</h2>
                        </div><a class="button secondary" href="/news/">Alle Berichte →</a>
                    </div>
                    <div class="news-feature-grid news-feature-grid-two">
                        <?php foreach ($news as $a): ?><a class="feature-card"
                                href="/news/<?= e($a['slug']) ?>/"><?php if ($a['cover_path']): ?><img
                                        src="/<?= e($a['cover_path']) ?>" alt=""><?php endif; ?>
                                <div class="feature-content">
                                    <small><?= e(date('d.m.Y', strtotime($a['published_at']))) ?><?php if ($a['group_name']): ?>
                                            · <?= e(group_label($db, (int) $a['group_id'], true)) ?><?php endif; ?></small>
                                    <h3><?= e($a['title']) ?></h3>
                                    <p><?= e($a['excerpt']) ?></p>
                                </div>
                            </a><?php endforeach; ?>
                    </div>
                </div>
            </section><?php endif; ?>
        <section class="section section-blue">
                <div class="container">
                    <div class="section-title">
                        <div>
                            <p class="eyebrow">TERMINE</p>
                            <h2>Was steht an?</h2>
                            <p class="section-intro">Die nächsten Termine, Spiele und Veranstaltungen des SV Frohnstetten.</p>
                        </div>
                        <a class="button secondary" href="/termine/">Alle Termine →</a>
                    </div>
                    <?php if ($events): ?><div class="event-modern-list"><?php foreach ($events as $ev): ?>
                            <article class="event-modern">
                                <div class="event-date">
                                    <strong><?= e(date('d', strtotime($ev['start_date']))) ?></strong><span><?= e(strtoupper(date('M', strtotime($ev['start_date'])))) ?></span>
                                </div>
                                <div><span class="event-type"><?= e(labelEvent($ev['event_type'])) ?></span>
                                    <h3><?= e($ev['title']) ?></h3>
                                    <p><?= e(dt($ev['start_date'])) ?><?php if ($ev['location']): ?> ·
                                            <?= e($ev['location']) ?><?php endif; ?></p><?php if ($ev['group_id']): ?><small
                                            class="event-context"><?= e(group_label($db, (int) $ev['group_id'], true)) ?></small><?php endif; ?>
                                </div>
                            </article><?php endforeach; ?>
                    </div><?php else: ?><div class="empty-panel home-empty-events"><h3>Aktuell sind keine Termine eingetragen.</h3><p>Neue Spiele, Trainings und Veranstaltungen werden hier angezeigt.</p></div><?php endif; ?>
                </div>
            </section>
        <?php if ($regularSponsors): ?>
            <section class="section sponsors-section">
                <div class="container">
                    <div class="section-title">
                        <div>
                            <p class="eyebrow">UNSERE PARTNER</p>
                            <h2>Sponsoren & Partner</h2>
                            <p>Vielen Dank für die Unterstützung unseres Vereins.</p>
                        </div>
                    </div>
                    <div class="sponsor-grid"><?php foreach ($regularSponsors as $sp): ?><a class="sponsor-card"
                                href="<?= e($sp['website'] ?: '#') ?>" <?= ($sp['website'] ? 'target="_blank" rel="noopener"' : '') ?>>
                                <div class="sponsor-logo"><?php if ($sp['logo_path']): ?><img src="/<?= e($sp['logo_path']) ?>"
                                            alt="<?= e($sp['name']) ?>"><?php else: ?><span><?= e(text_initial($sp['name'])) ?></span><?php endif; ?>
                                </div><strong><?= e($sp['name']) ?></strong><?php if ($sp['description']): ?>
                                    <p><?= e($sp['description']) ?></p><?php endif; ?>
                            </a><?php endforeach; ?></div>
                </div>
            </section><?php endif; ?>
        <section class="club-band">
            <div class="container club-band-inner">
                <div>
                    <p class="eyebrow light">EIN VEREIN. VIELE MÖGLICHKEITEN.</p>
                    <h2>Sport verbindet Menschen.</h2>
                </div>
                <div class="club-stats">
                    <div><strong><?= count($deps) ?></strong><span>Sportarten</span></div>
                    <div><strong>100+</strong><span>Jahre Verein</span></div>
                </div>
            </div>
        </section>
    </main>
    <footer>
        <div class="container footer-grid">
            <div>
                <div class="footer-brand"><img src="<?= $crest ?>" alt="">
                    <div><strong>SV Frohnstetten 1912/49 e.V.</strong>
                        <p>Blau & Weiß. Seit 1912/49.</p>
                    </div>
                </div>
            </div>
            <div class="footer-legal"><a href="/verein/?seite=impressum">Impressum</a><a href="/verein/?seite=datenschutz">Datenschutz</a></div>
            <div class="footer-login"><a href="/login/">Redaktion</a></div>
        </div>
    </footer>
</body>

</html>