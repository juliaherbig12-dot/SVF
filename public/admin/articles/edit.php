<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
$availableDepartments = db()->query("SELECT * FROM departments WHERE active=1 ORDER BY sort_order,name")->fetchAll();
require __DIR__ . '/../../../src/media.php';
$u = require_admin();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$presetGroup = (int) ($_GET['group'] ?? $_POST['group_id'] ?? 0);
$article = null;
if ($id) {
    $s = db()->prepare("SELECT * FROM articles WHERE id=?");
    $s->execute([$id]);
    $article = $s->fetch();
}
$departments = db()->query("SELECT id,name FROM departments WHERE active=1 ORDER BY sort_order,name")->fetchAll();
$groups = db()->query("SELECT id,name,department_id,parent_id FROM groups WHERE active=1 ORDER BY department_id,parent_id,sort_order,name")->fetchAll();
$libraryMedia = db()->query("SELECT * FROM media ORDER BY created_at DESC")->fetchAll();
$media = [];
if ($id) {
    $s = db()->prepare("SELECT m.*,am.sort_order FROM media m JOIN article_media am ON am.media_id=m.id WHERE am.article_id=? ORDER BY am.sort_order,m.id");
    $s->execute([$id]);
    $media = $s->fetchAll();
}
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'attach_media') {
        $mid = (int) ($_POST['media_id'] ?? 0);
        if ($id && $mid) {
            $st = db()->prepare("SELECT department_id FROM articles WHERE id=?");
            $st->execute([$id]);
            $dep = (int) $st->fetchColumn();
            if ($dep && can_manage_department($dep)) {
                $st = db()->prepare("SELECT COALESCE(MAX(sort_order),0)+10 FROM article_media WHERE article_id=?");
                $st->execute([$id]);
                $ord = (int) $st->fetchColumn();
                db()->prepare("INSERT OR IGNORE INTO article_media(article_id,media_id,sort_order)VALUES(?,?,?)")->execute([$id, $mid, $ord]);
            }
        }
        header('Location:/admin/articles/edit.php?id=' . $id);
        exit;
    }
    if ($action === 'remove_media') {
        $mid = (int) ($_POST['media_id'] ?? 0);
        if ($id) {
            db()->prepare("DELETE FROM article_media WHERE article_id=? AND media_id=?")->execute([$id, $mid]);
        }
        header('Location:/admin/articles/edit.php?id=' . $id);
        exit;
    }
    if ($action === 'set_cover') {
        $mid = (int) ($_POST['media_id'] ?? 0);
        if ($id) {
            db()->prepare("UPDATE articles SET cover_image_id=? WHERE id=?")->execute([$mid, $id]);
        }
        header('Location:/admin/articles/edit.php?id=' . $id);
        exit;
    }
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $groupId = (int) ($_POST['group_id'] ?? 0);
    if ($departmentId && !can_manage_department($departmentId)) {
        http_response_code(403);
        exit('Keine Berechtigung für diesen Sportbereich.');
    }
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if (!$title || !$content) {
        $error = 'Bitte Titel und Berichtstext ausfüllen.';
    } else {
        $slug = slugify($title);
        if ($id) {
            $s = db()->prepare("UPDATE articles SET title=?,slug=?,excerpt=?,content=?,department_id=?,group_id=?,updated_at=datetime('now') WHERE id=?");
            $s->execute([$title, $slug, trim($_POST['excerpt'] ?? ''), $content, $departmentId ?: null, $groupId ?: null, $id]);
        } else {
            $s = db()->prepare("INSERT INTO articles(title,slug,excerpt,content,author_id,department_id,group_id) VALUES(?,?,?,?,?,?,?)");
            $s->execute([$title, $slug, trim($_POST['excerpt'] ?? ''), $content, $u['id'], $departmentId ?: null, $groupId ?: null]);
            $id = (int) db()->lastInsertId();
        }
        if (!empty($_FILES['images']['name'][0])) {
            $dir = __DIR__ . '/../../uploads';
            if (!is_dir($dir))
                mkdir($dir, 0755, true);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $f = $_FILES['images'];
            $count = count($f['name']);
            $st = db()->prepare("INSERT INTO media(filename,storage_path,mime_type,size_bytes,alt_text,uploaded_by,group_id) VALUES(?,?,?,?,?,?,?)");
            $link = db()->prepare("INSERT INTO article_media(article_id,media_id,sort_order) VALUES(?,?,?)");
            for ($i = 0; $i < $count; $i++) {
                if ($f['error'][$i] !== UPLOAD_ERR_OK || $f['size'][$i] > 12 * 1024 * 1024)
                    continue;
                $mime = detect_mime_type($f['tmp_name'][$i]);
                if (!isset($allowed[$mime]))
                    continue;
                $safe = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                if (!optimize_image($f['tmp_name'][$i], $mime, $dir . '/' . $safe, 1800, 82))
                    continue;
                $st->execute([$f['name'][$i], 'uploads/' . $safe, $mime, (int) $f['size'][$i], null, $u['id'], $groupId ?: null]);
                $mid = (int) db()->lastInsertId();
                $link->execute([$id, $mid, $i]);
            }
        }
        if (($_POST['publish'] ?? '') === '1')
            db()->prepare("UPDATE articles SET published_at=datetime('now'),active=1 WHERE id=?")->execute([$id]);
        header('Location:/admin/articles/edit.php?id=' . $id . '&saved=1');
        exit;
    }
}
if (isset($_GET['saved']))
    $success = 'Bericht gespeichert.';
if ($id) {
    $s = db()->prepare("SELECT * FROM articles WHERE id=?");
    $s->execute([$id]);
    $article = $s->fetch();
    $s = db()->prepare("SELECT m.*,am.sort_order FROM media m JOIN article_media am ON am.media_id=m.id WHERE am.article_id=? ORDER BY am.sort_order,m.id");
    $s->execute([$id]);
    $media = $s->fetchAll();
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bericht</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
    <header>
        <div class="wrap nav"><b>SV Frohnstetten</b>
            <nav><a href="/admin/articles/">← Berichte</a></nav>
        </div>
    </header>
    <main class="wrap">
        <p class="eyebrow">REDAKTION</p>
        <h1><?= $article ? 'Bericht bearbeiten' : 'Neuer Bericht' ?></h1>
        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p><?php endif; ?><?php if ($success): ?>
            <p class="success"><?= e($success) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <section class="create"><label>Titel<input name="title" required value="<?= e($article['title'] ?? '') ?>"
                        placeholder="Titel des Berichts"></label>
                <label>Kurzbeschreibung<textarea name="excerpt" rows="3"
                        placeholder="Kurztext für die Übersicht"><?= e($article['excerpt'] ?? '') ?></textarea></label>
                <div class="formgrid"><label>Sportangebot<select name="department_id">
                            <option value="">Verein allgemein</option><?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($article['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?></option><?php endforeach; ?>
                        </select></label>
                    <label>Gruppe<select name="group_id">
                            <option value="">Keine Gruppe</option><?php foreach ($groups as $g): ?>
                                <option value="<?= $g['id'] ?>"
                                    <?= ((($article['group_id'] ?? $presetGroup) == $g['id']) ? 'selected' : '') ?>>
                                    <?= e(group_option_label($db, $g)) ?></option><?php endforeach; ?>
                        </select></label>
                </div>
                <label>Berichtstext<div class="editor-toolbar"><button type="button"
                            onclick="fmt('strong')"><b>B</b></button><button type="button"
                            onclick="fmt('em')"><i>I</i></button><button type="button"
                            onclick="fmt('h2')">Überschrift</button><button type="button"
                            onclick="fmt('ul')">Liste</button></div><textarea id="content" name="content" rows="18"
                        required
                        placeholder="Hier den Bericht schreiben..."><?= e($article['content'] ?? '') ?></textarea></label>

                <h2>Bilder</h2>
                <p class="hint">Wähle Bilder aus deiner Medienbibliothek oder lade neue Bilder hoch.</p>
                <div class="media-picker">
                    <?php foreach ($libraryMedia as $lm):
                        $selected = false;
                        foreach ($media as $am) {
                            if ((int) $am['id'] === (int) $lm['id']) {
                                $selected = true;
                                break;
                            }
                        } ?>
                        <div class="picker-card <?= $selected ? 'selected' : '' ?>"><img src="/<?= e($lm['storage_path']) ?>"
                                alt="<?= e($lm['alt_text'] ?? $lm['filename']) ?>"><?php if ($selected): ?><span
                                    class="selected-label">✓ Im Bericht</span><?php else: ?>
                                <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden"
                                        name="action" value="attach_media"><input type="hidden" name="media_id"
                                        value="<?= $lm['id'] ?>"><button>+ Einfügen</button></form><?php endif; ?>
                        </div><?php endforeach; ?>
                </div>
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                <?php if ($media): ?>
                    <div class="media-grid"><?php foreach ($media as $m): ?>
                            <div class="media-card"><img src="/<?= e($m['storage_path']) ?>"
                                    alt="<?= e($m['alt_text'] ?? $m['filename']) ?>">
                                <div>
                                    <small><?= e($m['filename']) ?></small><?php if (($article['cover_image_id'] ?? 0) == $m['id']): ?><strong
                                            class="cover">Titelbild</strong><?php endif; ?></div>
                                <div class="actions">
                                    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                                            type="hidden" name="media_id" value="<?= $m['id'] ?>"><button name="action"
                                            value="set_cover">Als Titelbild</button></form>
                                    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                                            type="hidden" name="media_id" value="<?= $m['id'] ?>"><button class="danger"
                                            name="action" value="remove_media">Entfernen</button></form>
                                </div>
                            </div><?php endforeach; ?>
                    </div><?php endif; ?>
                <p class="hint">Automatische Größenoptimierung und Bildbearbeitung bauen wir im nächsten Medienmodul
                    aus.</p>
                <div class="actions"><button name="publish" value="0">Entwurf speichern</button><button name="publish"
                        value="1">Speichern & veröffentlichen</button></div>
            </section>
        </form>
    </main>
    <script>function fmt(tag) { const e = document.getElementById('content'), a = e.selectionStart, b = e.selectionEnd, t = e.value.slice(a, b) || 'Text'; let r = '<' + tag + '>' + t + '</' + tag + '>'; if (tag === 'ul') r = '<ul><li>' + t + '</li></ul>'; e.setRangeText(r, a, b, 'select'); e.focus(); }</script>
</body>

</html>