<?php
require __DIR__ . '/../../src/bootstrap-local.php';
$u = require_login();
$db = db();

$dept = (int) $db->query("SELECT COUNT(*) FROM departments WHERE active=1")->fetchColumn();
$groups = (int) $db->query("SELECT COUNT(*) FROM groups WHERE active=1")->fetchColumn();
$articles = (int) $db->query("SELECT COUNT(*) FROM articles WHERE active=1")->fetchColumn();
$events = (int) $db->query("SELECT COUNT(*) FROM events WHERE active=1 AND start_date>=datetime('now')")->fetchColumn();
$players = (int) $db->query("SELECT COUNT(*) FROM squad_players WHERE active=1")->fetchColumn();
$media = (int) $db->query("SELECT COUNT(*) FROM media")->fetchColumn();
$sponsors = (int) $db->query("SELECT COUNT(*) FROM sponsors WHERE active=1")->fetchColumn();

if (($u['role'] ?? '') === 'admin') {
  $departments = $db->query("SELECT d.*, COUNT(g.id) AS group_count FROM departments d LEFT JOIN groups g ON g.department_id=d.id AND g.active=1 WHERE d.active=1 GROUP BY d.id ORDER BY d.sort_order,d.name")->fetchAll();
} else {
  $st = $db->prepare("SELECT d.*, COUNT(g.id) AS group_count FROM departments d JOIN user_departments ud ON ud.department_id=d.id AND ud.user_id=? LEFT JOIN groups g ON g.department_id=d.id AND g.active=1 WHERE d.active=1 GROUP BY d.id ORDER BY d.sort_order,d.name");
  $st->execute([(int) $u['id']]);
  $departments = $st->fetchAll();
}
function dashboard_icon(string $type): string
{
  $icons = [
    'fussball' => '<span class="dash-icon dash-icon-ball" aria-hidden="true"></span>',
    'volleyball' => '<span class="dash-icon dash-icon-volley" aria-hidden="true"></span>',
    'turnen' => '<span class="dash-icon dash-icon-gym" aria-hidden="true"></span>',
    'leichtathletik' => '<span class="dash-icon dash-icon-run" aria-hidden="true"></span>',
    'default' => '<span class="dash-icon dash-icon-generic" aria-hidden="true"></span>'
  ];
  return $icons[$type] ?? $icons['default'];
}
function dept_key(string $slug): string
{
  return strtolower(trim($slug));
}
?>
<!doctype html>
<html lang="de">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Redaktion – SV Frohnstetten</title>
  <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
  <header>
    <div class="wrap nav">
      <div class="brand"><b>SV Frohnstetten</b><span>Redaktionsbereich</span></div>
      <nav><a href="/">Website</a><a class="nav-primary" href="/logout.php">Abmelden</a></nav>
    </div>
  </header>
  <main class="wrap dashboard">
    <div class="dashboard-hero">
      <div>
        <p class="eyebrow">REDAKTIONSBEREICH</p>
        <h1>Hallo, <?= e($u['name']) ?>!</h1>
        <p class="lead">Hier findest du alle Bereiche der Website an einem Ort. Wähle direkt eine Sportart oder springe
          zu den wichtigsten Verwaltungsbereichen.</p>
      </div>
      <a class="button dashboard-site" href="/">Website ansehen <span>⟶</span></a>
    </div>

    <section class="stats dashboard-stats">
      <div><span class="stat-label">Sportarten</span><b><?= $dept ?></b><small>aktiv</small></div>
      <div><span class="stat-label">Gruppen</span><b><?= $groups ?></b><small>aktiv</small></div>
      <div><span class="stat-label">Berichte</span><b><?= $articles ?></b><small>veröffentlicht</small></div>
      <div><span class="stat-label">Termine</span><b><?= $events ?></b><small>kommend</small></div>
      <div><span class="stat-label">Kader</span><b><?= $players ?></b><small>aktive Personen</small></div>
      <div><span class="stat-label">Medien</span><b><?= $media ?></b><small>Bilder & Dateien</small></div>
    </section>

    <section class="dashboard-section">
      <div class="section-heading">
        <div>
          <p class="eyebrow">DIREKT ZU DEN SPORTARTEN</p>
          <h2>Sportbereiche</h2>
        </div><a href="/admin/departments/">Alle Sportarten verwalten ⟶</a>
      </div>
      <div class="department-dashboard-grid">
        <?php foreach ($departments as $d):
          $key = dept_key((string) $d['slug']); ?>
          <article class="department-dashboard-card dept-<?= e($key) ?>">
            <div class="department-card-top">
              <div><?= dashboard_icon($key) ?></div><span class="department-count"><?= e((string) $d['group_count']) ?>
                Gruppen</span>
            </div>
            <h3><?= e($d['name']) ?></h3>
            <p><?= e($d['description'] ?: 'Gruppen, Mannschaften und Inhalte verwalten.') ?></p>
            <div class="department-actions">
              <a class="button" href="/admin/sport/?department=<?= (int) $d['id'] ?>">Bereich öffnen</a>
              <a class="text-link" href="/sport/?bereich=<?= e($key) ?>">Öffentliche Seite ⟶</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="dashboard-section">
      <div class="section-heading">
        <div>
          <p class="eyebrow">SCHNELLZUGRIFF</p>
          <h2>Website-Inhalte</h2>
        </div>
      </div>
      <div class="quick-dashboard-grid">
        <a class="quick-card" href="/admin/articles/"><span
            class="quick-number">01</span><strong>Berichte</strong><small>Neuigkeiten schreiben und
            bearbeiten</small></a>
        <a class="quick-card" href="/admin/events/"><span class="quick-number">02</span><strong>Veranstaltungen &
            Termine</strong><small>Veranstaltungen und Termine pflegen</small></a>
        <a class="quick-card" href="/admin/squad/"><span class="quick-number">03</span><strong>Kader &
            Betreuer</strong><small>Spieler, Fotos, Trainer und Betreuer</small></a>
        <a class="quick-card" href="/admin/media/"><span
            class="quick-number">04</span><strong>Medien</strong><small>Bilder zentral hochladen und
            verwalten</small></a>
        <a class="quick-card" href="/admin/sponsors/"><span
            class="quick-number">05</span><strong>Sponsoren</strong><small>Hauptsponsoren und Partner
            verwalten</small></a>
        <a class="quick-card" href="/admin/verein/"><span class="quick-number">06</span><strong>Unser
            Verein</strong><small>Vorstand, Zeiten, Dokumente und Links</small></a>
        <?php if (($u['role'] ?? '') === 'admin'): ?><a class="quick-card" href="/admin/users/"><span
              class="quick-number">07</span><strong>Benutzer & Rechte</strong><small>Redakteure und Zugriffe
              verwalten</small></a><?php endif; ?>
        <?php if (($u['role'] ?? '') === 'admin'): ?><a class="quick-card" href="/admin/departments/"><span
              class="quick-number">08</span><strong>Sportstruktur</strong><small>Sportarten, Gruppen und
              Untergruppen</small></a><?php endif; ?>
        <?php if (($u['role'] ?? '') === 'admin'): ?><a class="quick-card" href="/admin/system/backup.php"><span
              class="quick-number">09</span><strong>Backup</strong><small>Datenbank vor Änderungen sichern</small></a><?php endif; ?>
      </div>
    </section>

    <section class="dashboard-section dashboard-focus">
      <div class="section-heading">
        <div>
          <p class="eyebrow">UNSER VEREIN</p>
          <h2>Vereinsverwaltung</h2>
        </div><a class="button secondary" href="/admin/verein/">Zur Vereinsverwaltung ⟶</a>
      </div>
      <div class="focus-grid">
        <div><strong>Vorstand & Ansprechpartner</strong>
          <p>Personen, Funktionen, Telefon und E-Mail direkt pflegen.</p>
        </div>
        <div><strong>Übungszeiten</strong>
          <p>Trainingsangebote mit Ort, Zeit, Altersgruppe und Leitung verwalten.</p>
        </div>
        <div><strong>Dokumente</strong>
          <p>Satzung, Jugendordnung, Beitragsordnung und weitere Unterlagen aktuell halten.</p>
        </div>
        <div><strong>Links</strong>
          <p>Gemeinde, Verbände und Partner zentral bearbeiten.</p>
        </div>
      </div>
    </section>

    <section class="dashboard-section dashboard-footer-tools">
      <div><strong><?= $sponsors ?> aktive Sponsoren</strong><span> · </span><a href="/admin/sponsors/">Sponsoren
          verwalten</a></div>
      <div><a href="/admin/media/">Medien öffnen</a><span> · </span><a href="/">Website ansehen</a></div>
    </section>
  </main>
</body>

</html>