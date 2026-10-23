<?php
require __DIR__ . '/../../src/bootstrap-local.php';

$db = db();
$events = $db->query("SELECT e.*,d.name department_name,g.name group_name FROM events e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN groups g ON g.id=e.group_id WHERE e.active=1 ORDER BY datetime(e.start_date) ASC LIMIT 100")->fetchAll();
$crest = '/assets/svf-wappen.png';

function eventLabelPublic($t)
{
  return ['spiel' => 'Spiel', 'training' => 'Training', 'veranstaltung' => 'Veranstaltung', 'termin' => 'Termin'][$t] ?? 'Termin';
}
?>
<!doctype html>
<html lang="de">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Termine – SV Frohnstetten</title>
  <meta name="description" content="Spiele, Trainings, Veranstaltungen und weitere Termine des SV Frohnstetten.">
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
      <a class="mobile-menu" href="/sport/">
        <span class="menu-bars" aria-hidden="true"></span>
      </a>
    </div>
  </header>

  <main>
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow light">SV FROHNSTETTEN</p>
        <h1>Termine</h1>
        <p>Spiele, Trainings, Veranstaltungen und weitere Termine des Vereins.</p>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="calendar-page-list">
          <?php foreach ($events as $ev): ?>
            <article class="calendar-card">
              <div class="calendar-date">
                <strong><?= e(date('d', strtotime($ev['start_date']))) ?></strong>
                <span><?= e(strtoupper(date('M', strtotime($ev['start_date'])))) ?></span>
              </div>
              <div class="calendar-main">
                <span class="event-type"><?= e(eventLabelPublic($ev['event_type'])) ?></span>
                <h2><?= e($ev['title']) ?></h2>
                <p><?= e(date('d.m.Y · H:i', strtotime($ev['start_date']))) ?><?php if ($ev['location']): ?> ·
                    <?= e($ev['location']) ?>  <?php endif; ?></p>
                <?php if ($ev['group_id']): ?>
                  <small class="event-context"><?= e(group_label($db, (int) $ev['group_id'], true)) ?></small>
                <?php endif; ?>
                <?php if ($ev['description']): ?>
                  <div class="calendar-description"><?= nl2br(e($ev['description'])) ?></div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
          <?php if (!$events): ?>
            <div class="empty-panel">
              <h2>Noch keine Termine.</h2>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
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