<?php
require __DIR__ . '/../../src/bootstrap-local.php';

$db = db();
$pages = $db->query("SELECT * FROM club_pages WHERE active=1 ORDER BY sort_order,title")->fetchAll();
$docs = $db->query("SELECT * FROM club_documents WHERE active=1 ORDER BY sort_order,title")->fetchAll();

$slug = trim($_GET['seite'] ?? '');
$page = null;
$docPage = null;
if ($slug) {
    $st = $db->prepare("SELECT * FROM club_pages WHERE slug=? AND active=1 LIMIT 1");
    $st->execute([$slug]);
    $page = $st->fetch();
    if (!$page) {
        $st = $db->prepare("SELECT * FROM club_documents WHERE slug=? AND active=1 LIMIT 1");
        $st->execute([$slug]);
        $docPage = $st->fetch();
    }
}

$crest = '/assets/svf-wappen.png';
$people = $db->query("SELECT * FROM club_people WHERE active=1 ORDER BY area,sort_order,name")->fetchAll();
$training = $db->query("SELECT * FROM club_training WHERE active=1 ORDER BY sort_order,id")->fetchAll();
$links = $db->query("SELECT * FROM club_links WHERE active=1 ORDER BY sort_order,id")->fetchAll();
?>
<!doctype html>
<html lang="de">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($page['title'] ?? 'Unser Verein') ?> – SV Frohnstetten</title>
  <meta name="description" content="Vorstandschaft, Übungszeiten, Dokumente und wichtige Informationen über den SV Frohnstetten.">
  <link rel="stylesheet" href="/assets/app.css">
</head>

<body>
  <header class="site-header">
    <div class="container nav">
      <a class="brand" href="/">
        <img src="<?= $crest ?>" alt="SV Frohnstetten Wappen">
        <span>
          <b>SV FROHNSTETTEN</b>
          <small>GEMEINSAM. SPORT. ZUSAMMENHALT.</small>
        </span>
      </a>
      <nav>
        <a href="/">Verein</a>
        <a href="/verein/">Unser Verein</a>
        <a href="/sport/?bereich=fussball">Fußball</a>
        <a href="/sport/?bereich=volleyball">Volleyball</a>
        <a href="/sport/?bereich=turnen-gymnastik">Turnen</a>
        <a href="/sport/?bereich=leichtathletik">Leichtathletik</a>
        <a href="/news/">Aktuelles</a>
      </nav>
    </div>
  </header>

  <main>
    <?php if ($page || $docPage): $item = $page ?: $docPage;
      $isDoc = (bool) $docPage; ?>
      <section class="page-hero">
        <div class="container">
          <p class="eyebrow light"><a href="/verein/">← Unser Verein</a></p>
          <h1><?= e($item['title']) ?></h1>
          <p><?= e($item['intro'] ?? $item['description'] ?? '') ?></p>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <article class="club-content-card <?= $isDoc ? 'club-document-content' : '' ?>">
            <?php if (!$isDoc && $page['slug'] === 'vorstand'): ?>
              <div class="structured-grid">
                <?php foreach ($people as $person):
                  if ($person['area'] !== 'vorstand') {
                      continue;
                  } ?>
                  <div class="structured-card">
                    <span class="structured-label"><?= e($person['role']) ?></span>
                    <h3><?= e($person['name']) ?></h3>
                    <?php if ($person['phone']): ?>
                      <p><b>Telefon:</b> <?= e($person['phone']) ?></p>
                    <?php endif; ?>
                    <?php if ($person['email']): ?>
                      <p><b>E-Mail:</b> <?= e($person['email']) ?></p>
                    <?php endif; ?>
                    <?php if ($person['address']): ?>
                      <p><?= nl2br(e($person['address'])) ?></p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>

            <?php elseif (!$isDoc && $page['slug'] === 'uebungsleiter'): ?>
              <?php $cats = [];
              foreach ($training as $t) {
                  $cats[$t['category']][] = $t;
              }
              foreach ($cats as $cat => $items): ?>
                <div class="training-block">
                  <div class="section-title compact">
                    <div>
                      <p class="eyebrow">TRAINING</p>
                      <h2><?= e($cat) ?></h2>
                    </div>
                  </div>
                  <div class="training-grid">
                    <?php foreach ($items as $t): ?>
                      <div class="training-card">
                        <span class="structured-label"><?= e($t['weekday']) ?><?= ($t['time'] ? ' · ' . e($t['time']) : '') ?></span>
                        <h3><?= e($t['title']) ?></h3>
                        <?php if ($t['age_group']): ?>
                          <p><b>Altersgruppe:</b> <?= e($t['age_group']) ?></p>
                        <?php endif; ?>
                        <?php if ($t['location']): ?>
                          <p><b>Ort:</b> <?= e($t['location']) ?></p>
                        <?php endif; ?>
                        <?php if ($t['leader']): ?>
                          <p><b>Übungsleitung:</b> <?= e($t['leader']) ?></p>
                        <?php endif; ?>
                        <?php if ($t['note']): ?>
                          <p><?= nl2br(e($t['note'])) ?></p>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>

            <?php elseif (!$isDoc && $page['slug'] === 'links'): ?>
              <?php $cats = [];
              foreach ($links as $l) {
                  $cats[$l['category']][] = $l;
              }
              foreach ($cats as $cat => $items): ?>
                <div class="link-block">
                  <h2><?= e($cat) ?></h2>
                  <div class="link-grid">
                    <?php foreach ($items as $l): ?>
                      <a class="link-card" href="<?= e($l['url'] ?: '#') ?>" <?= $l['url'] ? 'target="_blank" rel="noopener"' : '' ?>>
                        <span class="club-card-mark" aria-hidden="true"></span>
                        <strong><?= e($l['title']) ?></strong>
                        <b>→</b>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>

            <?php elseif ($isDoc && $item['slug'] === 'praevention-kinderschutz'): ?>
              <div class="club-content-copy">
                <h2>Der Schutz unserer Kinder ist uns wichtig!</h2>
                <p>Als Schutzbeauftragte und Ansprechpartner stehen folgende Personen zur Verfügung:</p>
              </div>
              <div class="structured-grid">
                <?php foreach ($people as $person):
                  if ($person['area'] !== 'kinderschutz') {
                      continue;
                  } ?>
                  <div class="structured-card">
                    <span class="structured-label"><?= e($person['role']) ?></span>
                    <h3><?= e($person['name']) ?></h3>
                    <?php if ($person['address']): ?>
                      <p><?= nl2br(e($person['address'])) ?></p>
                    <?php endif; ?>
                    <?php if ($person['phone']): ?>
                      <p><b>Telefon:</b> <?= e($person['phone']) ?></p>
                    <?php endif; ?>
                    <?php if ($person['email']): ?>
                      <p><b>E-Mail:</b> <?= e($person['email']) ?></p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="club-content-copy">
                <p>Bitte beachten Sie die Schutzordnung.</p>
              </div>

            <?php else: ?>
              <div class="club-content-copy"><?= nl2br(e($item['content'] ?? '')) ?></div>
            <?php endif; ?>

            <?php if ($isDoc && !empty($item['storage_path'])): ?>
              <div class="club-document-action">
                <a class="button" href="/<?= e($item['storage_path']) ?>" target="_blank" rel="noopener">Dokument herunterladen →</a>
              </div>
            <?php endif; ?>
          </article>
          <a class="button secondary" href="/verein/">← Zur Übersicht</a>
        </div>
      </section>

    <?php else: ?>
      <section class="page-hero">
        <div class="container">
          <p class="eyebrow light"><span class="eyebrow-graphic" aria-hidden="true"></span>DER VEREIN</p>
          <h1>Unser Verein</h1>
          <p>Alles rund um den SV Frohnstetten – von Vorstand und Übungsleitern bis zu Satzung, Ordnungen und wichtigen Vereinsunterlagen.</p>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <div class="section-title">
            <div>
              <p class="eyebrow">VEREINSLEBEN</p>
              <h2>Das ist der SV Frohnstetten</h2>
            </div>
          </div>

          <div class="club-page-grid">
            <?php foreach ($pages as $p):
              $icon = match ($p['slug']) {
                  'vorstand' => 'people',
                  'uebungsleiter' => 'training',
                  'links' => 'link',
                  default => 'club',
              };
              ?>
              <a class="club-page-card" href="/verein/?seite=<?= e($p['slug']) ?>">
                <span class="club-card-mark club-card-icon club-card-icon-<?= e($icon) ?>" aria-hidden="true">
                  <?php if ($icon === 'people'): ?>
                    <svg viewBox="0 0 48 48" focusable="false"><circle cx="18" cy="17" r="6"/><circle cx="31" cy="19" r="5"/><path d="M7 36c1-7 5-10 11-10s10 3 11 10"/><path d="M27 29c5-1 10 1 12 7"/></svg>
                  <?php elseif ($icon === 'training'): ?>
                    <svg viewBox="0 0 48 48" focusable="false"><circle cx="29" cy="9" r="5"/><path d="M25 16l-5 10 8 5 4-8 4 7"/><path d="M20 26l-9 7"/><path d="M28 31l-6 10"/><path d="M36 30l7 8"/><path d="M18 18l9 4"/></svg>
                  <?php elseif ($icon === 'link'): ?>
                    <svg viewBox="0 0 48 48" focusable="false"><path d="M19 29l-3 3a7 7 0 0 1-10-10l6-6a7 7 0 0 1 10 0"/><path d="M29 19l3-3a7 7 0 0 1 10 10l-6 6a7 7 0 0 1-10 0"/><path d="M17 31l14-14"/></svg>
                  <?php else: ?>
                    <svg viewBox="0 0 48 48" focusable="false"><path d="M24 6l15 6v11c0 9-6 15-15 19C15 38 9 32 9 23V12l15-6z"/><path d="M17 24l5 5 10-11"/></svg>
                  <?php endif; ?>
                </span>
                <div>
                  <small>VEREIN</small>
                  <h3><?= e($p['title']) ?></h3>
                  <p><?= e($p['intro']) ?></p>
                </div>
                <b>→</b>
              </a>
            <?php endforeach; ?>
          </div>

          <div class="section-title club-doc-title">
            <div>
              <p class="eyebrow">DOKUMENTE & RECHTLICHES</p>
              <h2>Wichtige Vereinsunterlagen</h2>
            </div>
          </div>

          <div class="club-doc-grid">
            <?php foreach ($docs as $d): ?>
              <article class="club-doc-card">
                <div class="club-doc-mark" aria-hidden="true"></div>
                <div>
                  <small>VEREINSUNTERLAGE</small>
                  <h3><?= e($d['title']) ?></h3>
                  <p><?= e($d['description']) ?></p>
                  <div class="club-doc-actions">
                    <a class="button small" href="/verein/?seite=<?= e($d['slug']) ?>">Inhalt ansehen →</a>
                    <?php if ($d['storage_path']): ?>
                      <a class="text-link" href="/<?= e($d['storage_path']) ?>" target="_blank" rel="noopener">Datei öffnen</a>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
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
        <a href="/login/">Redaktion Login</a>
      </div>
    </div>
  </footer>
</body>

</html>
