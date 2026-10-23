<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
$u = require_admin_only();
if ($u['role'] !== 'admin') {
  http_response_code(403);
  exit('Nur Administratoren können Benutzer verwalten.');
}
$msg = null;
$err = null;
$edit = null;
$deps = db()->query("SELECT * FROM departments WHERE active=1 ORDER BY sort_order,name")->fetchAll();
if (isset($_GET['edit'])) {
  $id = (int) $_GET['edit'];
  $st = db()->prepare("SELECT * FROM users WHERE id=?");
  $st->execute([$id]);
  $edit = $st->fetch();
  if ($edit) {
    $st = db()->prepare("SELECT department_id FROM user_departments WHERE user_id=?");
    $st->execute([$id]);
    $edit['departments'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $action = $_POST['action'] ?? '';
  if ($action === 'save') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $role = $_POST['role'] ?? 'editor';
    $pw = $_POST['password'] ?? '';
    $assigned = array_map('intval', $_POST['departments'] ?? []);
    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['admin', 'editor'], true) || (!$id && !$pw))
      $err = 'Bitte alle Pflichtfelder ausfüllen. Bei neuen Benutzern ist ein Passwort erforderlich.';
    elseif ($id === (int) $u['id'] && $role !== 'admin')
      $err = 'Der eigene Administrator-Account kann hier nicht zu einem Redakteur herabgestuft werden.';
    else {
      try {
        if ($id) {
          if ($pw)
            db()->prepare("UPDATE users SET name=?,email=?,role=?,password_hash=? WHERE id=?")->execute([$name, $email, $role, password_hash($pw, PASSWORD_DEFAULT), $id]);
          else
            db()->prepare("UPDATE users SET name=?,email=?,role=? WHERE id=?")->execute([$name, $email, $role, $id]);
        } else {
          $st = db()->prepare("INSERT INTO users(name,email,password_hash,role)VALUES(?,?,?,?)");
          $st->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role]);
          $id = (int) db()->lastInsertId();
        }
        db()->prepare("DELETE FROM user_departments WHERE user_id=?")->execute([$id]);
        if ($role === 'admin')
          $assigned = array_column($deps, 'id');
        $st = db()->prepare("INSERT INTO user_departments(user_id,department_id)VALUES(?,?)");
        foreach ($assigned as $d)
          $st->execute([$id, $d]);
        $msg = 'Benutzer gespeichert.';
        $edit = null;
      } catch (Throwable $e) {
        $err = 'Benutzer konnte nicht gespeichert werden. Die E-Mail-Adresse ist möglicherweise bereits vergeben.';
      }
    }
  }
  if ($action === 'toggle') {
    $id = (int) $_POST['id'];
    if ($id !== (int) $u['id']) {
      db()->prepare("UPDATE users SET active=CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
      $msg = 'Status geändert.';
    } else
      $err = 'Der eigene Account kann nicht deaktiviert werden.';
  }
}
$users = db()->query("SELECT * FROM users ORDER BY name")->fetchAll();
?>
<!doctype html>
<html lang="de">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Benutzer & Rechte</title>
  <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
  <header>
    <div class="wrap nav"><b>SV Frohnstetten</b>
      <nav><a href="/admin/">Dashboard</a><a href="/admin/departments/">Sport</a><a
          href="/admin/articles/">Berichte</a><a href="/admin/media/">Medien</a><a href="/">Website</a><a
          href="/logout.php">Abmelden</a></nav>
    </div>
  </header>
  <main class="wrap">
    <div class="top">
      <div>
        <p class="eyebrow">VERWALTUNG</p>
        <h1>Benutzer & Rechte</h1>
      </div><?php if ($edit): ?><a class="button" href="/admin/users/">+ Neuer Benutzer</a><?php endif; ?>
    </div>
    <?php if ($msg): ?>
      <p class="success"><?= e($msg) ?></p><?php endif; ?><?php if ($err): ?>
      <p class="error"><?= e($err) ?></p><?php endif; ?>
    <section class="create">
      <h2><?= $edit ? 'Benutzer bearbeiten' : 'Neuen Benutzer anlegen' ?></h2>
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action"
          value="save"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
        <div class="formgrid">
          <label>Name<input name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="Max Mustermann"></label>
          <label>E-Mail<input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"
              placeholder="max@svfrohnstetten.de"></label>
          <label>Passwort<input type="password" name="password" <?= $edit ? '' : 'required' ?>
              placeholder="<?= $edit ? 'Leer lassen = unverändert' : '' ?>"></label>
          <label>Rolle<select name="role">
              <option value="editor" <?= ($edit['role'] ?? 'editor') === 'editor' ? 'selected' : '' ?>>Redakteur</option>
              <option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
            </select></label>
        </div>
        <fieldset>
          <legend>Sportbereiche</legend>
          <p class="hint">Redakteure sehen und bearbeiten nur die ausgewählten Bereiche. Administratoren haben
            automatisch Vollzugriff.</p><?php $chosen = $edit['departments'] ?? [];
            foreach ($deps as $d): ?><label
              class="check"><input type="checkbox" name="departments[]" value="<?= $d['id'] ?>"
                <?= in_array((int) $d['id'], $chosen, true) ? 'checked' : '' ?>> <?= e($d['icon']) ?>
              <?= e($d['name']) ?></label><?php endforeach; ?>
        </fieldset>
        <div class="actions"><button><?= $edit ? 'Änderungen speichern' : 'Benutzer anlegen' ?></button><?php if ($edit): ?><a
              class="button secondary" href="/admin/users/">Abbrechen</a><?php endif; ?></div>
      </form>
    </section>
    <section class="create">
      <h2>Benutzer</h2><?php foreach ($users as $x): ?>
        <div class="user-row">
          <div><strong><?= e($x['name']) ?></strong><small><?= e($x['email']) ?> ·
              <?= e($x['role'] === 'admin' ? 'Administrator' : 'Redakteur') ?> · <?= $x['active'] ? 'aktiv' : 'deaktiviert' ?></small>
          </div>
          <div class="actions"><a href="/admin/users/?edit=<?= $x['id'] ?>">Bearbeiten</a>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action"
                value="toggle"><input type="hidden" name="id"
                value="<?= $x['id'] ?>"><button><?= $x['active'] ? 'Deaktivieren' : 'Aktivieren' ?></button></form>
          </div>
        </div><?php endforeach; ?>
    </section>
  </main>
</body>

</html>