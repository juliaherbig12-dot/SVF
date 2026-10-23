<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
require_admin();
$u = require_login();
$department = (int) ($_GET['department'] ?? $_POST['department'] ?? 0);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$current = null;
$widgets = [];
$departmentRow = null;
$widgetTypes = ['next-match' => 'Nächstes Spiel', 'table' => 'Tabelle', 'last-matches' => 'Letzte Spiele', 'schedule' => 'Spielplan'];
if ($id) {
  $s = db()->prepare("SELECT * FROM groups WHERE id=?");
  $s->execute([$id]);
  $current = $s->fetch();
  if ($current) {
    $department = (int) $current['department_id'];
    $s = db()->prepare("SELECT * FROM football_widgets WHERE group_id=? AND active=1");
    $s->execute([$id]);
    foreach ($s->fetchAll() as $w)
      $widgets[$w['widget_type']] = $w;
  }
}
if ($department && !can_manage_department($department)) {
  http_response_code(403);
  exit('Keine Berechtigung für diesen Sportbereich.');
}
if ($department) {
  $s = db()->prepare("SELECT * FROM departments WHERE id=?");
  $s->execute([$department]);
  $departmentRow = $s->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $action = $_POST['action'] ?? '';
  $id = (int) ($_POST['id'] ?? 0);
  $department = (int) ($_POST['department'] ?? 0);
  if ($department && !can_manage_department($department)) {
    http_response_code(403);
    exit('Keine Berechtigung für diesen Sportbereich.');
  }
  if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $parent = (int) ($_POST['parent_id'] ?? 0);
    $parent = $parent ?: null;
    if ($name) {
      $sql = "SELECT COALESCE(MAX(sort_order),0)+10 FROM groups WHERE department_id=? AND " . ($parent === null ? "parent_id IS NULL" : "parent_id=?");
      $s = db()->prepare($sql);
      $parent === null ? $s->execute([$department]) : $s->execute([$department, $parent]);
      $order = (int) $s->fetchColumn();
      db()->prepare("INSERT INTO groups(department_id,parent_id,name,slug,description,sort_order) VALUES(?,?,?,?,?,?)")->execute([$department, $parent, $name, slugify($name), trim($_POST['description'] ?? ''), $order]);
    }
  }
  if ($action === 'update')
    db()->prepare("UPDATE groups SET name=?,slug=?,description=? WHERE id=?")->execute([trim($_POST['name']), slugify($_POST['name']), trim($_POST['description'] ?? ''), $id]);
  if ($action === 'save_football_widgets' && $id) {
    $url = trim($_POST['football_url'] ?? '');
    foreach ($widgetTypes as $type => $label) {
      $code = trim($_POST['widget_code'][$type] ?? '');
      if ($code || $url) {
        db()->prepare("INSERT INTO football_widgets(group_id,widget_type,widget_code,football_url,active,updated_at) VALUES(?,?,?,?,1,CURRENT_TIMESTAMP) ON CONFLICT(group_id,widget_type) DO UPDATE SET widget_code=excluded.widget_code,football_url=excluded.football_url,active=1,updated_at=CURRENT_TIMESTAMP")->execute([$id, $type, $code, $url]);
      } else
        db()->prepare("DELETE FROM football_widgets WHERE group_id=? AND widget_type=?")->execute([$id, $type]);
    }
  }
  if ($action === 'archive')
    db()->prepare("UPDATE groups SET active=0 WHERE id=?")->execute([$id]);
  if ($action === 'restore')
    db()->prepare("UPDATE groups SET active=1 WHERE id=?")->execute([$id]);
  if ($action === 'delete') {
    $s = db()->prepare("SELECT COUNT(*) FROM groups WHERE parent_id=?");
    $s->execute([$id]);
    if ((int) $s->fetchColumn() === 0) {
      db()->prepare("DELETE FROM football_widgets WHERE group_id=?")->execute([$id]);
      db()->prepare("DELETE FROM groups WHERE id=?")->execute([$id]);
    }
  }
  header('Location:/admin/departments/');
  exit;
}
$deps = db()->query("SELECT id,name FROM departments WHERE active=1 ORDER BY sort_order,name")->fetchAll();
$groups = [];
if ($department) {
  $s = db()->prepare("SELECT * FROM groups WHERE department_id=? ORDER BY sort_order,name");
  $s->execute([$department]);
  $groups = $s->fetchAll();
}
function tree($items, $parent = null, $level = 0)
{
  foreach ($items as $g) {
    if (($g['parent_id'] ?? null) === $parent) {
      echo '<li style="margin-left:' . ($level * 20) . 'px"><strong>' . e($g['name']) . '</strong> <small>' . ($g['active'] ? 'aktiv' : 'archiviert') . '</small> · <a href="/admin/groups/?id=' . $g['id'] . '">Bearbeiten</a></li>';
      tree($items, $g['id'], $level + 1);
    }
  }
}
?>
<!doctype html>
<html lang="de">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Gruppen – SV Frohnstetten</title>
  <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
  <main class="wrap">
    <p><a href="/admin/">← Dashboard</a></p>
    <h1>Sportgruppen</h1>
    <section class="create">
      <form method="get"><label>Abteilung<select name="department" onchange="this.form.submit()">
            <option value="0">Bitte wählen</option><?php foreach ($deps as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $department === $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select></label></form>
    </section>
    <?php if ($department && !$current): ?>
      <section class="create">
        <h2>Neue Gruppe</h2>
        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action"
            value="create"><input type="hidden" name="department" value="<?= $department ?>"><label>Name<input name="name"
              required></label><label>Übergeordnete Gruppe<select name="parent_id">
              <option value="0">Keine – oberste Ebene</option><?php foreach ($groups as $g):
                if ($g['active']): ?>
                  <option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option><?php endif; endforeach; ?>
            </select></label><label>Beschreibung<input name="description"></label><button>Anlegen</button></form>
      </section><?php endif; ?>
    <?php if ($current): ?>
      <p><a href="/admin/groups/?department=<?= $current['department_id'] ?>">← Zur Gruppenübersicht</a></p>
      <section class="create">
        <h2>„<?= e($current['name']) ?>“ bearbeiten</h2>
        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action"
            value="update"><input type="hidden" name="id" value="<?= $current['id'] ?>"><input type="hidden"
            name="department" value="<?= $current['department_id'] ?>"><label>Name<input name="name"
              value="<?= e($current['name']) ?>" required></label><label>Beschreibung<input name="description"
              value="<?= e($current['description']) ?>"></label><button>Speichern</button></form>
      </section>
      <p><a class="button" href="/admin/squad/?gruppe_id=<?= $current['id'] ?>">Kader & Trainer/Betreuer verwalten</a></p>
      <?php if (($departmentRow['slug'] ?? '') === 'fussball'): ?>
        <section class="create">
          <h2>FUSSBALL.DE-Widgets</h2>
          <p class="hint">Für diese Mannschaft kannst du jedes Widget separat hinterlegen. Den jeweils kompletten Code aus
            „Meine Widgets“ → „Code anzeigen“ von FUSSBALL.DE einfügen. Kader bleibt bewusst außen vor und wird später
            manuell gepflegt.</p>
          <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action"
              value="save_football_widgets"><input type="hidden" name="id" value="<?= $current['id'] ?>"><input type="hidden"
              name="department" value="<?= $current['department_id'] ?>"><label>FUSSBALL.DE-Seite<input name="football_url"
                value="<?= e(($widgets[array_key_first($widgets)]['football_url'] ?? '')) ?>"
                placeholder="https://www.fussball.de/..."></label>
            <?php foreach ($widgetTypes as $type => $label): ?><label><?= e($label) ?><textarea name="widget_code[<?= e($type) ?>]"
                  rows="7"
                  placeholder="Kompletten FUSSBALL.DE-Code für „<?= e($label) ?>“ hier einfügen"><?= e($widgets[$type]['widget_code'] ?? '') ?></textarea></label><?php endforeach; ?><button>FUSSBALL.DE-Widgets
              speichern</button></form>
        </section><?php endif; ?><?php endif; ?>
    <section class="dept">
      <h2>Status & Hierarchie</h2>
      <p>Archivieren wirkt immer nur auf die ausgewählte Gruppe.</p>
      <ul class="tree"><?php tree($groups); ?></ul>
    </section>
  </main>
</body>

</html>