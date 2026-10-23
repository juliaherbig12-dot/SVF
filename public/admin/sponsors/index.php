<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
$u = require_admin_only();
$db = db();
$msg = null;
$edit = null;
if (isset($_GET['edit'])) {
    $q = $db->prepare('SELECT * FROM sponsors WHERE id=?');
    $q->execute([(int) $_GET['edit']]);
    $edit = $q->fetch() ?: null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $msg = 'Name ist Pflicht.';
        } else {
            $vals = [$name, trim($_POST['website'] ?? ''), ((int) ($_POST['logo_media_id'] ?? 0) ?: null), trim($_POST['description'] ?? ''), (int) ($_POST['sort_order'] ?? 0), (int) !empty($_POST['active']), (int) !empty($_POST['featured'])];
            if ($id) {
                $st = $db->prepare("UPDATE sponsors SET name=?,website=?,logo_media_id=?,description=?,sort_order=?,active=?,featured=?,updated_at=datetime('now') WHERE id=?");
                $st->execute([...$vals, $id]);
            } else {
                $st = $db->prepare("INSERT INTO sponsors(name,website,logo_media_id,description,sort_order,active,featured) VALUES(?,?,?,?,?,?,?)");
                $st->execute($vals);
            }
            $msg = 'Sponsor gespeichert.';
            $edit = null;
        }
    }
    if ($action === 'delete') {
        $db->prepare('UPDATE sponsors SET active=0,updated_at=datetime(\'now\') WHERE id=?')->execute([(int) $_POST['id']]);
        $msg = 'Sponsor archiviert.';
    }
    if ($action === 'restore') {
        $db->prepare('UPDATE sponsors SET active=1,updated_at=datetime(\'now\') WHERE id=?')->execute([(int) $_POST['id']]);
        $msg = 'Sponsor aktiviert.';
    }
}
$sponsors = $db->query("SELECT s.*,m.storage_path logo_path FROM sponsors s LEFT JOIN media m ON m.id=s.logo_media_id ORDER BY s.active DESC,s.featured DESC,s.sort_order,s.name")->fetchAll();
$media = $db->query("SELECT id,filename,storage_path,alt_text FROM media ORDER BY created_at DESC")->fetchAll();
?><!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sponsoren – SV Frohnstetten</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
    <header>
        <div class="wrap nav"><b>SV Frohnstetten</b>
            <nav><a href="/admin/">Dashboard</a><a href="/">Website</a></nav>
        </div>
    </header>
    <main class="wrap">
        <p class="eyebrow">PARTNER</p>
        <h1>Sponsoren & Partner</h1>
        <p>Logos, Namen, Websites und kurze Beschreibungen für den Sponsorbereich der Startseite.</p><?php if ($msg): ?>
            <p class="success"><?= e($msg) ?></p><?php endif; ?>
        <section class="create">
            <h2><?= $edit ? 'Sponsor bearbeiten' : 'Sponsor hinzufügen' ?></h2>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden"
                    name="action" value="save"><input type="hidden" name="id" value="<?= e((string) ($edit['id'] ?? 0)) ?>">
                <div class="formgrid"><label>Name<input name="name" required
                            value="<?= e($edit['name'] ?? '') ?>"></label><label>Website<input name="website" type="url"
                            placeholder="https://..."
                            value="<?= e($edit['website'] ?? '') ?>"></label><label>Reihenfolge<input name="sort_order"
                            type="number" value="<?= e((string) ($edit['sort_order'] ?? 0)) ?>"></label></div>
                <label>Logo
                    <select name="logo_media_id">
                        <option value="">Kein Logo – Buchstaben-Platzhalter</option><?php foreach ($media as $m): ?>
                            <option value="<?= $m['id'] ?>"
                                <?= ((int) ($edit['logo_media_id'] ?? 0) === (int) $m['id']) ? 'selected' : '' ?>><?= e($m['filename']) ?>
                            </option><?php endforeach; ?>
                    </select>
                </label>
                <div class="sponsor-logo-picker">
                    <?php foreach ($media as $m): ?>
                        <label class="sponsor-logo-option <?= ((int) ($edit['logo_media_id'] ?? 0) === (int) $m['id']) ? 'selected' : '' ?>">
                            <input type="radio" name="logo_media_preview" value="<?= $m['id'] ?>" <?= ((int) ($edit['logo_media_id'] ?? 0) === (int) $m['id']) ? 'checked' : '' ?> onchange="document.querySelector('select[name=logo_media_id]').value=this.value;document.querySelectorAll('.sponsor-logo-option').forEach(x=>x.classList.remove('selected'));this.closest('.sponsor-logo-option').classList.add('selected')">
                            <img src="/<?= e($m['storage_path']) ?>" alt="<?= e($m['alt_text'] ?: $m['filename']) ?>">
                            <span><?= e($m['filename']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="hint">Logo noch nicht vorhanden? <a href="/admin/media/">Jetzt in der Medienbibliothek hochladen</a>. Danach erscheint es hier automatisch.</p><label>Beschreibung<textarea name="description"
                        rows="3"><?= e($edit['description'] ?? '') ?></textarea></label><label><input type="checkbox"
                        name="active" <?= (!$edit || (int) $edit['active'] === 1) ? 'checked' : '' ?>> Auf Website
                    anzeigen</label><label><input type="checkbox" name="featured" <?= ($edit && (int) ($edit['featured'] ?? 0) === 1) ? 'checked' : '' ?>> Oben als Hauptsponsor
                    anzeigen</label><button>Speichern</button>
            </form>
        </section>
        <section class="create">
            <h2>Alle Sponsoren</h2><?php foreach ($sponsors as $sp): ?>
                <div class="admin-row">
                    <div><strong><?= e($sp['name']) ?></strong><span><?= $sp['active'] ? 'Aktiv' : 'Archiviert' ?><?= !empty($sp['featured']) ? ' · Hauptsponsor' : '' ?><?php if ($sp['website']): ?>
                                · <?= e($sp['website']) ?><?php endif; ?></span></div><a
                        href="/admin/sponsors/?edit=<?= $sp['id'] ?>">Bearbeiten</a><?php if ($sp['active']): ?>
                        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden"
                                name="action" value="delete"><input type="hidden" name="id" value="<?= $sp['id'] ?>"><button
                                class="danger">Archivieren</button></form><?php else: ?>
                        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden"
                                name="action" value="restore"><input type="hidden" name="id"
                                value="<?= $sp['id'] ?>"><button>Aktivieren</button></form><?php endif; ?>
                </div><?php endforeach; ?><?php if (!$sponsors): ?>
                <p>Noch keine Sponsoren angelegt.</p><?php endif; ?>
        </section>
    </main>
</body>

</html>