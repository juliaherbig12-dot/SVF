<?php
require __DIR__ . '/../../src/bootstrap-local.php';

$db = db();
$slug = trim($_GET['bereich'] ?? '');
if ($slug === 'turnen') {
  $slug = 'turnen-gymnastik';
}
$groupId = (int) ($_GET['gruppe_id'] ?? 0);
$department = null;

if ($slug) {
  $s = $db->prepare("SELECT * FROM departments WHERE slug=? AND active=1");
  $s->execute([$slug]);
  $department = $s->fetch();
}

$departments = $db->query("SELECT * FROM departments WHERE active=1 ORDER BY sort_order,name")->fetchAll();

$partners = [];
if ($department && (int) ($department['is_spielgemeinschaft'] ?? 0)) {
  $q = $db->prepare("SELECT * FROM department_partner_links WHERE department_id=? AND active=1 ORDER BY sort_order,name");
  $q->execute([(int) $department['id']]);
  $partners = $q->fetchAll();
}

$group = null;
$children = [];
$widgets = [];
$groupArticles = [];
$groupEvents = [];
$breadcrumbs = [];
$squadPlayers = [];
$squadStaff = [];
$groupMedia = [];

if ($department) {
  if ($groupId) {
    $s = $db->prepare("SELECT * FROM groups WHERE id=? AND department_id=? AND active=1 LIMIT 1");
    $s->execute([$groupId, (int) $department['id']]);
    $group = $s->fetch();
  }
  if ($group) {
    $parent = (int) ($group['parent_id'] ?? 0);
    $guard = 0;
    while ($parent > 0 && $guard++ < 20) {
      $s = $db->prepare("SELECT id,name,parent_id FROM groups WHERE id=? AND department_id=? AND active=1");
      $s->execute([$parent, (int) $department['id']]);
      $p = $s->fetch();
      if (!$p) {
        break;
      }
      array_unshift($breadcrumbs, $p);
      $parent = (int) ($p['parent_id'] ?? 0);
    }

    $s = $db->prepare("SELECT * FROM groups WHERE parent_id=? AND active=1 ORDER BY sort_order,name");
    $s->execute([(int) $group['id']]);
    $children = $s->fetchAll();

    $q = $db->prepare("SELECT * FROM group_training WHERE group_id=? AND active=1 ORDER BY sort_order,title");
    $q->execute([(int) $group['id']]);
    $groupTraining = $q->fetchAll();

    $q = $db->prepare("SELECT * FROM group_contacts WHERE group_id=? AND active=1 ORDER BY sort_order,name");
    $q->execute([(int) $group['id']]);
    $groupContacts = $q->fetchAll();

    $s = $db->prepare("SELECT * FROM football_widgets WHERE group_id=? AND active=1 ORDER BY CASE widget_type WHEN 'next-match' THEN 10 WHEN 'table' THEN 20 WHEN 'last-matches' THEN 30 WHEN 'schedule' THEN 40 ELSE 90 END");
    $s->execute([(int) $group['id']]);
    $widgets = $s->fetchAll();

    $s = $db->prepare("SELECT a.*,m.storage_path cover_path FROM articles a LEFT JOIN media m ON m.id=a.cover_image_id WHERE a.group_id=? AND a.active=1 AND a.published_at IS NOT NULL ORDER BY a.published_at DESC LIMIT 6");
    $s->execute([(int) $group['id']]);
    $groupArticles = $s->fetchAll();

    $s = $db->prepare("SELECT e.*,d.name department_name,g.name group_name FROM events e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN groups g ON g.id=e.group_id WHERE e.group_id=? AND e.active=1 AND datetime(e.start_date)>=datetime('now','localtime') ORDER BY datetime(e.start_date) LIMIT 8");
    $s->execute([(int) $group['id']]);
    $groupEvents = $s->fetchAll();

    $q = $db->prepare("SELECT p.*,m.storage_path photo_path FROM squad_players p LEFT JOIN media m ON m.id=p.photo_media_id WHERE p.group_id=? AND p.active=1 ORDER BY p.sort_order,p.last_name,p.first_name");
    $q->execute([(int) $group['id']]);
    $squadPlayers = $q->fetchAll();

    $q = $db->prepare("SELECT s.*,m.storage_path photo_path FROM squad_staff s LEFT JOIN media m ON m.id=s.photo_media_id WHERE s.group_id=? AND s.active=1 ORDER BY s.sort_order,s.name");
    $q->execute([(int) $group['id']]);
    $squadStaff = $q->fetchAll();

    $q = $db->prepare("SELECT * FROM media WHERE group_id=? ORDER BY created_at DESC, id DESC LIMIT 12");
    $q->execute([(int) $group['id']]);
    $groupMedia = $q->fetchAll();
  } else {
    $s = $db->prepare("SELECT * FROM groups WHERE department_id=? AND parent_id IS NULL AND active=1 ORDER BY sort_order,name");
    $s->execute([(int) $department['id']]);
    $children = $s->fetchAll();
  }
}

function label(string $type): string
{
  return [
    'next-match' => 'Nächstes Spiel',
    'table' => 'Tabelle',
    'last-matches' => 'Letzte Spiele',
    'schedule' => 'Spielplan',
  ][$type] ?? 'FUSSBALL.DE';
}

function widgetBody(string $code): string
{
  return trim(preg_replace('~<script\b[^>]*>.*?</script>~is', '', $code));
}

function eventLabel(string $type): string
{
  return [
    'spiel' => 'Spiel',
    'training' => 'Training',
    'veranstaltung' => 'Veranstaltung',
    'termin' => 'Termin',
  ][$type] ?? 'Termin';
}

function positionGroups(array $players, string $departmentSlug): array
{
  $orders = $departmentSlug === 'volleyball'
    ? [
      'Zuspiel' => 'Zuspiel',
      'Außenangriff' => 'Außenangriff',
      'Mittelblock' => 'Mittelblock',
      'Diagonal' => 'Diagonal',
      'Libero' => 'Libero',
      'Universal' => 'Universal',
    ]
    : [
      'Torwart' => 'Torhüter',
      'Abwehr' => 'Abwehr',
      'Mittelfeld' => 'Mittelfeld',
      'Angriff' => 'Angriff',
      'Allrounder' => 'Weitere Spieler',
    ];
  $out = [];
  foreach ($orders as $key => $label) {
    $bucket = array_values(array_filter($players, fn($p) => ($p['position'] ?? '') === $key));
    if ($bucket) {
      $out[$label] = $bucket;
    }
  }
  $other = array_values(array_filter($players, fn($p) => !isset($orders[$p['position'] ?? ''])));
  if ($other) {
    $out['Weitere Spieler'] = $other;
  }
  return $out;
}

function sport_svg_public(string $slug): string
{
  $icons = [
    'fussball' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="22" fill="none" stroke="currentColor" stroke-width="4"/><path d="M32 16l7 8-3 10h-8l-3-10 7-8zm-7 18l-10 7m19-7l10 7m-18 0l-4 10m11-10l4 10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>',
    'volleyball' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="22" fill="none" stroke="currentColor" stroke-width="4"/><path d="M15 25c12-7 24-7 36 0M18 43c7-10 17-16 31-17M28 53c-1-10 2-19 10-28" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>',
    'turnen-gymnastik' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="34" cy="11" r="5" fill="none" stroke="currentColor" stroke-width="4"/><path d="M31 17l-6 15 10 7 8-13m-18-3l-10-6m10 6l13-6m-13 22l-9 13m17-13l8 13" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'leichtathletik' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="40" cy="10" r="5" fill="none" stroke="currentColor" stroke-width="4"/><path d="M35 17l-9 13 8 8 12-7m-20-1l-12-4m12 4l11-9m-3 17l-14 12m22-12l10 11" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  ];
  return $icons[$slug] ?? $icons['leichtathletik'];
}

$crest = '/assets/svf-wappen.png';
?>
<!doctype html>
<html lang="de">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($group['name'] ?? $department['name'] ?? 'Sportangebote') ?> – SV Frohnstetten</title>
  <meta name="description" content="<?= e(($group['name'] ?? $department['name'] ?? 'Sportangebote') . ' beim SV Frohnstetten – Trainingszeiten, Ansprechpartner, Berichte und Informationen.') ?>">
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
      <a class="mobile-menu" href="/sport/" aria-label="Sportbereiche"></a>
    </div>
  </header>

  <main>
    <?php if (!$department): ?>
      <section class="page-hero">
        <div class="container">
          <p class="eyebrow light">SV FROHNSTETTEN</p>
          <h1>Sportangebote</h1>
          <p>Alle Abteilungen und Gruppen des Vereins – klar strukturiert und direkt erreichbar.</p>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <div class="department-grid-modern">
            <?php foreach ($departments as $d): ?>
              <a class="department-card-modern" href="/sport/?bereich=<?= e($d['slug']) ?>">
                <span
                  class="department-icon department-icon-<?= e($d['slug']) ?>"><?= sport_svg_public($d['slug']) ?></span>
                <div>
                  <small>SPORTBEREICH</small>
                  <h2><?= e($d['name']) ?></h2>
                  <p><?= e($d['description']) ?></p>
                </div>
                <b>→</b>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

    <?php elseif (!$group): ?>
      <section class="page-hero page-hero-sport page-hero-<?= e($department['slug']) ?>">
        <div class="container">
          <p class="eyebrow light">
            <span class="eyebrow-graphic" aria-hidden="true"><?= sport_svg_public($department['slug']) ?></span>
            <?= e($department['name']) ?>
          </p>
          <h1><?= e($department['name']) ?></h1>
          <p><?= e($department['description']) ?></p>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <?php if ($department['slug'] === 'fussball' && (int) ($department['is_spielgemeinschaft'] ?? 0)): ?>
            <div class="sg-banner">
              <div class="sg-logo">
                <img src="/assets/sgm-heuberg.png" alt="SGM Heuberg Logo">
              </div>
              <div>
                <span class="sg-kicker">FUSSBALL</span>
                <strong>SGM Heuberg</strong>
                <p>
                  <?= e($department['sg_text'] ?: 'Der Fußballbereich des SV Frohnstetten wird als SGM Heuberg geführt.') ?>
                </p>
                <?php if ($partners): ?>
                  <div class="sg-links">
                    <?php foreach ($partners as $partner): ?>
                      <span class="sg-partner">
                        <a href="<?= e($partner['url'] ?: '#') ?>" <?= ($partner['url'] ? 'target="_blank" rel="noopener"' : '') ?>><?= e($partner['name']) ?></a>
                        <?php if (!empty($partner['instagram_url'])): ?>
                          <a class="sg-instagram" href="<?= e($partner['instagram_url']) ?>" target="_blank"
                            rel="noopener noreferrer" aria-label="Instagram <?= e($partner['name']) ?>">Instagram</a>
                        <?php endif; ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <small>Die zugehörigen Vereine können in der Redaktion hinterlegt und verlinkt werden.</small>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($children): ?>
            <div class="sport-group-tiles sport-group-tiles-<?= e($department['slug']) ?>">
              <?php foreach ($children as $g): ?>
                <a class="sport-group-tile"
                  href="/sport/?bereich=<?= e($department['slug']) ?>&gruppe_id=<?= (int) $g['id'] ?>">
                  <div class="sport-group-top">
                    <span class="sport-group-icon" aria-hidden="true"><?= sport_svg_public($department['slug']) ?></span>
                    <span class="sport-group-kicker"><?= e($department['name']) ?></span>
                  </div>
                  <h2><?= e($g['name']) ?></h2>
                  <p><?= e($g['description'] ?: 'Informationen, Gruppen und Angebote ansehen.') ?></p>
                  <span>Bereich öffnen <b>→</b></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-panel">
              <h2>Noch keine aktiven Gruppen.</h2>
            </div>
          <?php endif; ?>
        </div>
      </section>

    <?php else: ?>
      <section class="page-hero team-hero">
        <div class="container team-hero-inner">
          <div class="team-hero-copy">
            <p class="eyebrow light">
              <a href="/sport/?bereich=<?= e($department['slug']) ?>">← <?= e($department['name']) ?></a>
              <?php foreach ($breadcrumbs as $b): ?>
                · <a href="/sport/?bereich=<?= e($department['slug']) ?>&gruppe_id=<?= $b['id'] ?>"><?= e($b['name']) ?></a>
              <?php endforeach; ?>
            </p>
            <h1><?= e($group['name']) ?></h1>
            <p><?= e(group_label($db, (int) $group['id'], true)) ?></p>
            <?php if ($group['description']): ?>
              <div class="hero-subcopy"><?= e($group['description']) ?></div>
            <?php endif; ?>
          </div>
          <?php if (!empty($groupMedia[0]['storage_path'])): ?>
            <div class="team-hero-photo">
              <img src="/<?= e($groupMedia[0]['storage_path']) ?>" alt="<?= e($groupMedia[0]['alt_text'] ?: $group['name']) ?>">
            </div>
          <?php else: ?>
            <div class="team-hero-badge" aria-hidden="true"><?= sport_svg_public($department['slug']) ?></div>
          <?php endif; ?>
        </div>
      </section>

      <section class="section section-team">
        <div class="container">
          <nav class="team-anchor-nav" aria-label="Bereichsnavigation">
            <?php if ($widgets): ?><a href="#spielbetrieb">Spielbetrieb</a><?php endif; ?>
            <?php if ($squadPlayers || $squadStaff): ?><a href="#kader">Kader</a><?php endif; ?>
            <?php if ($groupTraining): ?><a href="#training">Training</a><?php endif; ?>
            <?php if ($groupContacts): ?><a href="#kontakt">Ansprechpartner</a><?php endif; ?>
            <?php if ($groupArticles): ?><a href="#berichte">Berichte</a><?php endif; ?>
            <?php if ($groupEvents): ?><a href="#termine">Termine</a><?php endif; ?>
            <?php if ($groupMedia): ?><a href="#bilder">Bilder</a><?php endif; ?>
          </nav>
          <div class="team-intro">
            <div>
              <p class="eyebrow"><?= e($department['name']) ?></p>
              <h2><?= e($group['name']) ?></h2>
            </div>
            <p>Alle wichtigen Informationen zu diesem Bereich auf einen Blick.</p>
          </div>
        <div class="container">
          <?php if ($department['slug'] === 'fussball' && (int) ($department['is_spielgemeinschaft'] ?? 0)): ?>
            <div class="sg-banner sg-banner-inline">
              <div class="sg-logo">
                <img src="/assets/sgm-heuberg.png" alt="SGM Heuberg Logo">
              </div>
              <div>
                <span class="sg-kicker">FUSSBALL</span>
                <strong>SGM Heuberg</strong>
                <?php if ($partners): ?>
                  <div class="sg-links">
                    <?php foreach ($partners as $partner): ?>
                      <span class="sg-partner">
                        <a href="<?= e($partner['url'] ?: '#') ?>" <?= ($partner['url'] ? 'target="_blank" rel="noopener"' : '') ?>><?= e($partner['name']) ?></a>
                        <?php if (!empty($partner['instagram_url'])): ?>
                          <a class="sg-instagram" href="<?= e($partner['instagram_url']) ?>" target="_blank"
                            rel="noopener noreferrer" aria-label="Instagram <?= e($partner['name']) ?>">Instagram</a>
                        <?php endif; ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($children): ?>
            <div class="subgroup-section">
              <div class="subgroup-section-head">
                <p class="eyebrow">WEITERE GRUPPEN</p>
                <h2>Weitere Gruppen</h2>
              </div>
              <div class="sport-group-tiles sport-group-tiles-<?= e($department['slug']) ?>">
                <?php foreach ($children as $g): ?>
                  <a class="sport-group-tile sport-group-tile-small"
                    href="/sport/?bereich=<?= e($department['slug']) ?>&gruppe_id=<?= (int) $g['id'] ?>">
                    <div class="sport-group-top">
                      <span class="sport-group-icon" aria-hidden="true"><?= sport_svg_public($department['slug']) ?></span>
                      <span class="sport-group-kicker"><?= e($department['name']) ?></span>
                    </div>
                    <h2><?= e($g['name']) ?></h2>
                    <span>Bereich öffnen <b>→</b></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($widgets): ?>
            <section class="sport-panel" id="spielbetrieb">
              <div class="section-title">
                <div>
                  <p class="eyebrow">FUSSBALL.DE</p>
                  <h2>Spielbetrieb</h2>
                </div>
                <?php $footballUrl = $widgets[0]['football_url'] ?? '';
                if ($footballUrl): ?>
                  <a class="button secondary" href="<?= e($footballUrl) ?>" target="_blank" rel="noopener">Auf FUSSBALL.DE
                    öffnen</a>
                <?php endif; ?>
              </div>
              <script type="text/javascript" src="https://www.fussball.de/widgets.js"></script>
              <div class="football-widget-grid">
                <?php foreach ($widgets as $w): ?>
                  <section class="football-widget-card">
                    <p class="eyebrow"><?= e(label($w['widget_type'])) ?></p>
                    <?php if (trim($w['widget_code'])): ?>
                      <div class="football-embed"><?= widgetBody($w['widget_code']) ?></div>
                    <?php else: ?>
                      <p>Für dieses Widget wurde noch kein Code hinterlegt.</p>
                    <?php endif; ?>
                  </section>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endif; ?>

          <?php if ($squadPlayers || $squadStaff): ?>
            <section class="sport-panel squad-panel" id="kader">
              <div class="section-title">
                <div>
                  <p class="eyebrow">MANNSCHAFT</p>
                  <h2>Kader</h2>
                  <p><?= e(group_label($db, (int) $group['id'], true)) ?></p>
                </div>
              </div>

              <?php if ($squadStaff): ?>
                <div class="roster-heading">
                  <h3>Trainer & Betreuer</h3>
                </div>
                <div class="staff-modern-grid">
                  <?php foreach ($squadStaff as $st): ?>
                    <article class="staff-modern-card">
                      <div class="roster-photo-wrap">
                        <?php if ($st['photo_path']): ?>
                          <img src="/<?= e($st['photo_path']) ?>" alt="<?= e($st['name']) ?>">
                        <?php else: ?>
                          <div class="roster-placeholder">SVF</div>
                        <?php endif; ?>
                      </div>
                      <div class="roster-card-body">
                        <span class="roster-role"><?= e($st['role']) ?></span>
                        <h3><?= e($st['name']) ?></h3>
                        <?php if ($st['bio']): ?>
                          <p><?= nl2br(e($st['bio'])) ?></p>
                        <?php endif; ?>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if ($squadPlayers): ?>
                <div class="roster-heading">
                  <h3>Spielerkader</h3>
                  <span><?= count($squadPlayers) ?> Spieler</span>
                </div>
                <?php $positioned = positionGroups($squadPlayers, $department['slug']);
                foreach ($positioned as $positionLabel => $playersInPosition): ?>
                  <section class="position-group">
                    <div class="position-group-title">
                      <span class="position-line"></span>
                      <h4><?= e($positionLabel) ?></h4>
                      <span class="position-count"><?= count($playersInPosition) ?></span>
                    </div>
                    <div class="roster-grid">
                      <?php foreach ($playersInPosition as $pl): ?>
                        <article class="roster-card">
                          <div class="roster-photo-wrap">
                            <?php if ($pl['photo_path']): ?>
                              <img src="/<?= e($pl['photo_path']) ?>" alt="<?= e($pl['first_name'] . ' ' . $pl['last_name']) ?>">
                            <?php else: ?>
                              <div class="roster-placeholder">
                                <span>SVF</span>
                                <small>Foto folgt</small>
                              </div>
                            <?php endif; ?>
                            <?php if ($pl['jersey_number']): ?>
                              <span class="roster-number">#<?= e($pl['jersey_number']) ?></span>
                            <?php endif; ?>
                          </div>
                          <div class="roster-card-body">
                            <h3><?= e($pl['first_name'] . ' ' . $pl['last_name']) ?></h3>
                            <span class="roster-role"><?= e($pl['position'] ?: 'Spieler') ?></span>
                            <?php if ($pl['birth_year']): ?>
                              <small>Jahrgang <?= e($pl['birth_year']) ?></small>
                            <?php endif; ?>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  </section>
                <?php endforeach; ?>
              <?php endif; ?>
            </section>
          <?php endif; ?>

          <?php if ($groupTraining || $groupContacts): ?>
            <section class="sport-panel" id="organisation">
              <div class="section-title">
                <div>
                  <p class="eyebrow">ORGANISATION</p>
                  <h2>Trainingszeiten & Ansprechpartner</h2>
                </div>
              </div>
              <div class="training-contact-grid">
                <?php if ($groupTraining): ?>
                  <div id="training">
                    <h3>Trainingszeiten</h3>
                    <div class="training-list-modern">
                      <?php foreach ($groupTraining as $t): ?>
                        <article>
                          <strong><?= e($t['title']) ?></strong>
                          <span><?= e($t['weekday']) ?> · <?= e($t['time']) ?></span>
                          <?php if ($t['age_group']): ?>
                            <small><?= e($t['age_group']) ?></small>
                          <?php endif; ?>
                          <?php if ($t['location']): ?>
                            <small><?= e($t['location']) ?></small>
                          <?php endif; ?>
                          <?php if ($t['leader']): ?>
                            <small>Leitung: <?= e($t['leader']) ?></small>
                          <?php endif; ?>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if ($groupContacts): ?>
                  <div id="kontakt">
                    <h3>Ansprechpartner</h3>
                    <div class="contact-list-modern">
                      <?php foreach ($groupContacts as $c): ?>
                        <article>
                          <strong><?= e($c['name']) ?></strong>
                          <?php if ($c['role']): ?>
                            <span><?= e($c['role']) ?></span>
                          <?php endif; ?>
                          <?php if ($c['phone']): ?>
                            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>"><?= e($c['phone']) ?></a>
                          <?php endif; ?>
                          <?php if ($c['email']): ?>
                            <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
                          <?php endif; ?>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </section>
          <?php endif; ?>

          <?php if ($groupMedia): ?>
            <section class="sport-panel" id="bilder">
              <div class="section-title">
                <div>
                  <p class="eyebrow">GALERIE</p>
                  <h2>Bilder</h2>
                </div>
              </div>
              <div class="team-gallery">
                <?php foreach ($groupMedia as $m): ?>
                  <a class="team-gallery-item" href="/<?= e($m['storage_path']) ?>" target="_blank" rel="noopener">
                    <img src="/<?= e($m['storage_path']) ?>" alt="<?= e($m['alt_text'] ?: $group['name']) ?>" loading="lazy">
                  </a>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endif; ?>

          <?php if ($groupArticles): ?>
            <section class="sport-panel" id="berichte">
              <div class="section-title">
                <div>
                  <p class="eyebrow">AKTUELLES</p>
                  <h2>Berichte</h2>
                </div>
                <a class="button secondary" href="/news/">Alle News</a>
              </div>
              <div class="cards">
                <?php foreach ($groupArticles as $a): ?>
                  <a class="card" href="/news/<?= e($a['slug']) ?>/">
                    <?php if ($a['cover_path']): ?>
                      <img class="card-image" src="/<?= e($a['cover_path']) ?>" alt="">
                    <?php endif; ?>
                    <small><?= e(date('d.m.Y', strtotime($a['published_at']))) ?></small>
                    <h3><?= e($a['title']) ?></h3>
                    <p><?= e($a['excerpt']) ?></p>
                  </a>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endif; ?>

          <?php if ($groupEvents): ?>
            <section class="sport-panel" id="termine">
              <div class="section-title">
                <div>
                  <p class="eyebrow">KALENDER</p>
                  <h2>Kommende Termine</h2>
                </div>
                <a class="button secondary" href="/termine/">Alle Termine</a>
              </div>
              <div class="event-public-list">
                <?php foreach ($groupEvents as $ev): ?>
                  <article class="event-public">
                    <div class="event-date">
                      <strong><?= e(date('d', strtotime($ev['start_date']))) ?></strong>
                      <span><?= e(date('M', strtotime($ev['start_date']))) ?></span>
                    </div>
                    <div>
                      <span class="event-type"><?= e(eventLabel($ev['event_type'])) ?></span>
                      <h3><?= e($ev['title']) ?></h3>
                      <p>
                        <?= e(date('H:i', strtotime($ev['start_date']))) ?>
                        <?php if ($ev['location']): ?>
                          · <?= e($ev['location']) ?>
                        <?php endif; ?>
                      </p>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endif; ?>

          <?php if (!$children && !$groupArticles && !$groupEvents && !$widgets && !$squadPlayers && !$squadStaff && !$groupMedia): ?>
            <div class="empty-panel">
              <h2>Diese Seite ist bereit</h2>
              <p>Hier können künftig Berichte, Termine, Kader und weitere Informationen für diese Gruppe erscheinen.</p>
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