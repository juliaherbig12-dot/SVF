<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
$u = require_admin();
$db = db();
$groupId = (int) ($_GET['gruppe_id'] ?? $_POST['group_id'] ?? 0);
if (!$groupId) {
  $departments = $db->query("SELECT d.*, COUNT(g.id) AS group_count FROM departments d LEFT JOIN groups g ON g.department_id=d.id AND g.active=1 WHERE d.active=1 GROUP BY d.id ORDER BY d.sort_order,d.name")->fetchAll();
  $allGroups = $db->query("SELECT g.id,g.name,g.parent_id,g.department_id,d.name department_name FROM groups g JOIN departments d ON d.id=g.department_id WHERE g.active=1 AND d.active=1 ORDER BY d.sort_order,g.sort_order,g.name")->fetchAll();
  ?>
  <!doctype html>
  <html lang="de">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Kaderverwaltung – SV Frohnstetten</title>
    <link rel="stylesheet" href="/assets/admin.css">
  </head>

  <body>
    <header>
      <div class="wrap nav"><b>SV Frohnstetten</b>
        <nav><a href="/admin/">Dashboard</a><a href="/">Website</a></nav>
      </div>
    </header>
    <main class="wrap dashboard">
      <p class="eyebrow">KADER & BETREUER</p>
      <h1>Mannschaft auswählen</h1>
      <p class="lead">Wähle eine Gruppe oder Mannschaft aus. Danach kannst du Kader, Fotos, Positionen sowie Trainer und
        Betreuer verwalten.</p>
      <div class="department-dashboard-grid" style="margin-top:26px">
        <?php foreach ($departments as $d): ?>
          <article class="department-dashboard-card">
            <div class="department-card-top"><span class="department-count"><?= e((string) $d['group_count']) ?> Gruppen</span>
            </div>
            <h3><?= e($d['name']) ?></h3>
            <div class="actions">
              <?php foreach ($allGroups as $g):
                if ((int) $g['department_id'] !== (int) $d['id'])
                  continue; ?><a
                  class="button secondary"
                  href="/admin/squad/?gruppe_id=<?= (int) $g['id'] ?>"><?= e($g['name']) ?></a><?php endforeach; ?></div>
          </article>
        <?php endforeach; ?>
      </div>
    </main>
  </body>

  </html>
  <?php exit;
}
$s = $db->prepare("SELECT g.*,d.name department_name FROM groups g JOIN departments d ON d.id=g.department_id WHERE g.id=? AND g.active=1");
$s->execute([$groupId]);
$group = $s->fetch();
if (!$group) {
  http_response_code(404);
  exit('Mannschaft nicht gefunden.');
}
require_department_access((int) $group['department_id']);
$msg = null;
$editPlayer = null;
$editStaff = null;
if (isset($_GET['edit_player'])) {
  $q = $db->prepare('SELECT * FROM squad_players WHERE id=? AND group_id=?');
  $q->execute([(int) $_GET['edit_player'], $groupId]);
  $editPlayer = $q->fetch() ?: null;
}
if (isset($_GET['edit_staff'])) {
  $q = $db->prepare('SELECT * FROM squad_staff WHERE id=? AND group_id=?');
  $q->execute([(int) $_GET['edit_staff'], $groupId]);
  $editStaff = $q->fetch() ?: null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $action = $_POST['action'] ?? '';
  if ($action === 'player_save') {
    $id = (int) ($_POST['id'] ?? 0);
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    if (!$first || !$last) {
      $msg = 'Vor- und Nachname sind Pflichtfelder.';
    } else {
      $vals = [$first, $last, trim($_POST['jersey_number'] ?? ''), trim($_POST['position'] ?? ''), trim($_POST['birth_year'] ?? ''), trim($_POST['bio'] ?? ''), (int) ($_POST['photo_media_id'] ?? 0) ?: null, (int) ($_POST['sort_order'] ?? 0), (int) !empty($_POST['active'])];
      if ($id) {
        $st = $db->prepare("UPDATE squad_players SET first_name=?,last_name=?,jersey_number=?,position=?,birth_year=?,bio=?,photo_media_id=?,sort_order=?,active=?,updated_at=datetime('now') WHERE id=? AND group_id=?");
        $st->execute([...$vals, $id, $groupId]);
      } else {
        $st = $db->prepare("INSERT INTO squad_players(group_id,first_name,last_name,jersey_number,position,birth_year,bio,photo_media_id,sort_order,active) VALUES(?,?,?,?,?,?,?,?,?,?)");
        $st->execute([$groupId, ...$vals]);
      }
      $msg = 'Spieler gespeichert.';
    }
  }
  if ($action === 'player_delete') {
    $db->prepare("UPDATE squad_players SET active=0,updated_at=datetime('now') WHERE id=? AND group_id=?")->execute([(int) $_POST['id'], $groupId]);
    $msg = 'Spieler archiviert.';
  }
  if ($action === 'staff_save') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    if (!$name || !$role) {
      $msg = 'Name und Funktion sind Pflichtfelder.';
    } else {
      $vals = [$name, $role, (int) ($_POST['photo_media_id'] ?? 0) ?: null, trim($_POST['bio'] ?? ''), (int) ($_POST['sort_order'] ?? 0), (int) !empty($_POST['active'])];
      if ($id) {
        $st = $db->prepare("UPDATE squad_staff SET name=?,role=?,photo_media_id=?,bio=?,sort_order=?,active=?,updated_at=datetime('now') WHERE id=? AND group_id=?");
        $st->execute([...$vals, $id, $groupId]);
      } else {
        $st = $db->prepare("INSERT INTO squad_staff(group_id,name,role,photo_media_id,bio,sort_order,active) VALUES(?,?,?,?,?,?,?)");
        $st->execute([$groupId, ...$vals]);
      }
      $msg = 'Trainer/Betreuer gespeichert.';
    }
  }
  if ($action === 'staff_delete') {
    $db->prepare("UPDATE squad_staff SET active=0,updated_at=datetime('now') WHERE id=? AND group_id=?")->execute([(int) $_POST['id'], $groupId]);
    $msg = 'Trainer/Betreuer archiviert.';
  }
}
$players = $db->prepare("SELECT p.*,m.storage_path photo_path FROM squad_players p LEFT JOIN media m ON m.id=p.photo_media_id WHERE p.group_id=? AND p.active=1 ORDER BY p.sort_order,p.last_name,p.first_name");
$players->execute([$groupId]);
$players = $players->fetchAll();
$staff = $db->prepare("SELECT s.*,m.storage_path photo_path FROM squad_staff s LEFT JOIN media m ON m.id=s.photo_media_id WHERE s.group_id=? AND s.active=1 ORDER BY s.sort_order,s.name");
$staff->execute([$groupId]);
$staff = $staff->fetchAll();
$media = $db->query("SELECT id,filename,storage_path FROM media ORDER BY created_at DESC")->fetchAll();
$positionOptions = str_contains(text_lower($group['department_name']), 'volleyball') ? ['Zuspiel', 'Außenangriff', 'Mittelblock', 'Diagonal', 'Libero', 'Universal'] : ['Torwart', 'Abwehr', 'Mittelfeld', 'Angriff', 'Allrounder'];
function photoSelect($media, $selected)
{
  echo '<div class="photo-picker"><select name="photo_media_id"><option value="">Kein Foto – Platzhalter verwenden</option>';
  foreach ($media as $m) {
    $sel = (int) $selected === (int) $m['id'] ? ' selected' : '';
    echo '<option value="' . e((string) $m['id']) . '"' . $sel . '>' . e($m['filename']) . '</option>';
  }
  echo '</select><a class="photo-upload-hint" href="/admin/media/">Foto in der Medienverwaltung hochladen →</a></div>';
}
?>
<!doctype html>
<html lang="de">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kader – <?= e(group_label($db, $groupId, true)) ?></title>
  <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
  <header>
    <div class="wrap nav"><b>SV Frohnstetten</b>
      <nav><a href="/admin/">Dashboard</a><a href="/sport/?bereich=fussball&gruppe_id=<?= $groupId ?>">Öffentliche
          Seite</a><a href="/admin/sponsors/">Sponsoren</a></nav>
    </div>
  </header>
  <main class="wrap">
    <p class="eyebrow">MANNSCHAFT</p>
    <h1><?= e($group['name']) ?></h1>
    <p><?= e(group_label($db, $groupId, true)) ?> · Kader und Trainer/Betreuer</p><?php if ($msg): ?>
      <p class="success"><?= e($msg) ?></p><?php endif; ?>
    <section class="create">
      <h2><?= $editPlayer ? "Spieler bearbeiten" : "Spieler hinzufügen" ?></h2>
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action"
          value="player_save"><input type="hidden" name="id" value="<?= e((string) ($editPlayer['id'] ?? 0)) ?>">
        <div class="formgrid"><label>Vorname<input name="first_name" required
              value="<?= e($editPlayer['first_name'] ?? '') ?>"></label><label>Nachname<input name="last_name" required
              value="<?= e($editPlayer['last_name'] ?? '') ?>"></label><label>Rückennummer<input name="jersey_number"
              value="<?= e($editPlayer['jersey_number'] ?? '') ?>"></label><label>Position<select name="position">
              <option value="">Bitte wählen</option><?php foreach ($positionOptions as $pos): ?>
                <option <?= ($editPlayer['position'] ?? '') === $pos ? 'selected' : '' ?>><?= e($pos) ?></option><?php endforeach; ?>
            </select></label><label>Jahrgang<input name="birth_year" placeholder="z. B. 2001"
              value="<?= e($editPlayer['birth_year'] ?? '') ?>"></label><label>Reihenfolge<input type="number"
              name="sort_order" value="<?= e((string) ($editPlayer['sort_order'] ?? 0)) ?>"></label></div><label>Spielerfoto
          <?php photoSelect($media, $editPlayer['photo_media_id'] ?? 0); ?></label><label>Steckbrief<textarea name="bio"
            rows="3"><?= e($editPlayer['bio'] ?? '') ?></textarea></label><label><input type="checkbox" name="active"
            <?= (!$editPlayer || (int) $editPlayer['active'] === 1) ? 'checked' : '' ?>> Aktiv</label><button>Spieler
          speichern</button>
      </form>
    </section>
    <section class="create">
      <h2>Aktive Spieler</h2><?php if (!$players): ?>
        <p>Noch keine Spieler angelegt.</p><?php endif; ?><?php foreach ($players as $p): ?>
        <div class="admin-row">
          <div>
            <strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong><span><?= e($p['position'] ?: 'Keine Position') ?><?php if ($p['jersey_number']): ?>
                · #<?= e($p['jersey_number']) ?><?php endif; ?></span></div><a
            href="/admin/squad/?gruppe_id=<?= $groupId ?>&edit_player=<?= $p['id'] ?>">Bearbeiten</a>
          <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action"
              value="player_delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button
              class="danger">Archivieren</button></form>
        </div><?php endforeach; ?>
    </section>
    <section class="create">
      <h2><?= $editStaff ? "Trainer/Betreuer bearbeiten" : "Trainer & Betreuer hinzufügen" ?></h2>
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action"
          value="staff_save"><input type="hidden" name="id" value="<?= e((string) ($editStaff['id'] ?? 0)) ?>">
        <div class="formgrid"><label>Name<input name="name" required
              value="<?= e($editStaff['name'] ?? '') ?>"></label><label>Funktion<input name="role" required
              placeholder="z. B. Trainer" value="<?= e($editStaff['role'] ?? '') ?>"></label><label>Reihenfolge<input
              type="number" name="sort_order" value="<?= e((string) ($editStaff['sort_order'] ?? 0)) ?>"></label></div>
        <label>Foto <?php photoSelect($media, $editStaff['photo_media_id'] ?? 0); ?></label><label>Kurzprofil<textarea
            name="bio" rows="3"><?= e($editStaff['bio'] ?? '') ?></textarea></label><label><input type="checkbox"
            name="active" <?= (!$editStaff || (int) $editStaff['active'] === 1) ? 'checked' : '' ?>>
          Aktiv</label><button>Speichern</button>
      </form>
    </section>
    <section class="create">
      <h2>Trainer & Betreuer</h2><?php if (!$staff): ?>
        <p>Noch niemand angelegt.</p><?php endif; ?><?php foreach ($staff as $s): ?>
        <div class="admin-row">
          <div><strong><?= e($s['name']) ?></strong><span><?= e($s['role']) ?></span></div><a
            href="/admin/squad/?gruppe_id=<?= $groupId ?>&edit_staff=<?= $s['id'] ?>">Bearbeiten</a>
          <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action"
              value="staff_delete"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button
              class="danger">Archivieren</button></form>
        </div><?php endforeach; ?>
    </section>
  </main>
</body>

</html>