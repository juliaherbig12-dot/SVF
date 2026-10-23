<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
require_admin_only();
$db = db();
$section = $_GET['section'] ?? '';
$allowed = ['vorstand', 'training', 'links', 'pages', 'documents'];
if (!in_array($section, $allowed, true))
    $section = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $section = $_POST['section'] ?? '';
    if ($action === 'page') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->prepare('UPDATE club_pages SET intro=?,content=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([trim($_POST['intro'] ?? ''), trim($_POST['content'] ?? ''), isset($_POST['active']) ? 1 : 0, $id]);
    }
    if ($action === 'document') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->prepare('UPDATE club_documents SET description=?,content=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([trim($_POST['description'] ?? ''), trim($_POST['content'] ?? ''), isset($_POST['active']) ? 1 : 0, $id]);
        if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $mime = detect_mime_type($_FILES['file']['tmp_name']);
            $allowedMime = ['application/pdf' => 'pdf', 'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav'];
            if (isset($allowedMime[$mime]) && $_FILES['file']['size'] <= 25 * 1024 * 1024) {
                $dir = __DIR__ . '/../../uploads/verein';
                if (!is_dir($dir))
                    mkdir($dir, 0755, true);
                $safe = bin2hex(random_bytes(12)) . '.' . $allowedMime[$mime];
                move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $safe);
                $old = $db->prepare('SELECT storage_path FROM club_documents WHERE id=?');
                $old->execute([$id]);
                $oldPath = $old->fetchColumn();
                if ($oldPath)
                    @unlink(__DIR__ . '/../../' . $oldPath);
                $db->prepare('UPDATE club_documents SET storage_path=?,original_filename=?,mime_type=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute(['uploads/verein/' . $safe, $_FILES['file']['name'], $mime, $id]);
            }
        }
    }
    if ($action === 'person_save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [trim($_POST['area'] ?? 'vorstand'), trim($_POST['role'] ?? ''), trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['address'] ?? ''), (int) ($_POST['sort_order'] ?? 0), isset($_POST['active']) ? 1 : 0];
        if ($id)
            $db->prepare('UPDATE club_people SET area=?,role=?,name=?,phone=?,email=?,address=?,sort_order=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([...$data, $id]);
        else
            $db->prepare('INSERT INTO club_people(area,role,name,phone,email,address,sort_order,active) VALUES(?,?,?,?,?,?,?,?)')->execute($data);
    }
    if ($action === 'person_delete')
        $db->prepare('DELETE FROM club_people WHERE id=?')->execute([(int) $_POST['id']]);
    if ($action === 'training_save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [trim($_POST['category'] ?? ''), trim($_POST['title'] ?? ''), trim($_POST['weekday'] ?? ''), trim($_POST['time'] ?? ''), trim($_POST['age_group'] ?? ''), trim($_POST['location'] ?? ''), trim($_POST['leader'] ?? ''), trim($_POST['note'] ?? ''), (int) ($_POST['sort_order'] ?? 0), isset($_POST['active']) ? 1 : 0];
        if ($id)
            $db->prepare('UPDATE club_training SET category=?,title=?,weekday=?,time=?,age_group=?,location=?,leader=?,note=?,sort_order=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([...$data, $id]);
        else
            $db->prepare('INSERT INTO club_training(category,title,weekday,time,age_group,location,leader,note,sort_order,active) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($data);
    }
    if ($action === 'training_delete')
        $db->prepare('DELETE FROM club_training WHERE id=?')->execute([(int) $_POST['id']]);
    if ($action === 'link_save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [trim($_POST['category'] ?? ''), trim($_POST['title'] ?? ''), trim($_POST['url'] ?? ''), (int) ($_POST['sort_order'] ?? 0), isset($_POST['active']) ? 1 : 0];
        if ($id)
            $db->prepare('UPDATE club_links SET category=?,title=?,url=?,sort_order=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([...$data, $id]);
        else
            $db->prepare('INSERT INTO club_links(category,title,url,sort_order,active) VALUES(?,?,?,?,?)')->execute($data);
    }
    if ($action === 'link_delete')
        $db->prepare('DELETE FROM club_links WHERE id=?')->execute([(int) $_POST['id']]);
    header('Location:/admin/verein/?section=' . urlencode($section) . '&saved=1');
    exit;
}
$pages = $db->query('SELECT * FROM club_pages ORDER BY sort_order,title')->fetchAll();
$docs = $db->query('SELECT * FROM club_documents ORDER BY sort_order,title')->fetchAll();
$people = $db->query('SELECT * FROM club_people ORDER BY area,sort_order,name')->fetchAll();
$training = $db->query('SELECT * FROM club_training ORDER BY sort_order,id')->fetchAll();
$links = $db->query('SELECT * FROM club_links ORDER BY sort_order,id')->fetchAll();
$cards = [['vorstand', 'Vorstand & Ansprechpartner', 'Personen, Funktionen, Telefon und E-Mail'], ['training', 'Übungsleiter & Übungszeiten', 'Trainingsangebote, Orte, Zeiten und Leitung'], ['links', 'Links', 'Gemeinde, Verbände und externe Partner'], ['pages', 'Vereinsseiten', 'Freie Inhalte wie Vereinsinformationen'], ['documents', 'Dokumente & Rechtliches', 'Satzung, Jugendordnung, Beitragsordnung und Dateien']];
?><!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Unser Verein – Redaktion</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
    <header>
        <div class="wrap nav">
            <div><b>SV Frohnstetten</b><span class="admin-subtitle">Redaktion</span></div>
            <nav><a href="/admin/">Dashboard</a><a href="/admin/sport/">Sportbereiche</a><a
                    href="/admin/media/">Medien</a><a href="/admin/sponsors/">Sponsoren</a><a
                    href="/logout.php">Abmelden</a></nav>
        </div>
    </header>
    <main class="wrap admin-verein-page">
        <p class="eyebrow">VEREIN</p>
        <h1>Unser Verein verwalten</h1>
        <p class="lead">Wähle zuerst den Bereich, den du bearbeiten möchtest. Danach wird nur dieser Bereich angezeigt.
        </p><?php if (isset($_GET['saved'])): ?>
            <p class="success">Änderung gespeichert.</p><?php endif; ?>
        <?php if (!$section): ?>
            <div class="club-admin-cards"><?php foreach ($cards as [$slug, $title, $desc]): ?><a class="club-admin-card"
                        href="?section=<?= e($slug) ?>"><span class="club-admin-number"></span>
                        <div>
                            <p class="eyebrow-small">UNSER VEREIN</p>
                            <h2><?= e($title) ?></h2>
                            <p><?= e($desc) ?></p>
                        </div><b>→</b>
                    </a><?php endforeach; ?></div><?php else: ?>
            <p><a href="/admin/verein/">← Unser Verein</a></p>
            <div class="admin-subnav"><a href="?section=vorstand"
                    class="<?= $section === 'vorstand' ? 'active' : '' ?>">Vorstand</a><a href="?section=training"
                    class="<?= $section === 'training' ? 'active' : '' ?>">Übungszeiten</a><a href="?section=links"
                    class="<?= $section === 'links' ? 'active' : '' ?>">Links</a><a href="?section=pages"
                    class="<?= $section === 'pages' ? 'active' : '' ?>">Vereinsseiten</a><a href="?section=documents"
                    class="<?= $section === 'documents' ? 'active' : '' ?>">Dokumente</a></div>
            <?php if ($section === 'vorstand'): ?>
                <section class="create">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">VEREIN</p>
                            <h2>Vorstand & Ansprechpartner</h2>
                        </div>
                    </div><?php foreach ($people as $p): ?>
                        <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                                type="hidden" name="action" value="person_save"><input type="hidden" name="section"
                                value="vorstand"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <div class="formgrid"><label>Bereich<select name="area">
                                        <option value="vorstand" <?= $p['area'] === 'vorstand' ? 'selected' : '' ?>>Vorstand</option>
                                        <option value="kinderschutz" <?= $p['area'] === 'kinderschutz' ? 'selected' : '' ?>>Prävention /
                                            Kinderschutz</option>
                                    </select></label><label>Funktion<input name="role"
                                        value="<?= e($p['role']) ?>"></label><label>Name<input name="name"
                                        value="<?= e($p['name']) ?>"></label><label>Telefon<input name="phone"
                                        value="<?= e($p['phone']) ?>"></label><label>E-Mail<input name="email"
                                        value="<?= e($p['email']) ?>"></label><label>Sortierung<input type="number" name="sort_order"
                                        value="<?= $p['sort_order'] ?>"></label></div><label>Adresse<textarea name="address"
                                    rows="2"><?= e($p['address']) ?></textarea></label><label class="check"><input type="checkbox"
                                    name="active" <?= $p['active'] ? 'checked' : '' ?>> Öffentlich anzeigen</label>
                            <div class="actions"><button>Speichern</button><button class="danger" name="action"
                                    value="person_delete" onclick="return confirm('Person wirklich entfernen?')">Entfernen</button>
                            </div>
                        </form><?php endforeach; ?>
                    <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                            type="hidden" name="action" value="person_save"><input type="hidden" name="section"
                            value="vorstand">
                        <h3>Neue Person</h3>
                        <div class="formgrid"><label>Bereich<select name="area">
                                    <option value="vorstand">Vorstand</option>
                                    <option value="kinderschutz">Prävention / Kinderschutz</option>
                                </select></label><label>Funktion<input name="role"></label><label>Name<input name="name"
                                    required></label><label>Telefon<input name="phone"></label><label>E-Mail<input
                                    name="email"></label><label>Sortierung<input type="number" name="sort_order"
                                    value="999"></label></div><label>Adresse<textarea name="address"
                                rows="2"></textarea></label><button>Person hinzufügen</button>
                    </form>
                </section>
            <?php elseif ($section === 'training'): ?>
                <section class="create">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">VEREIN</p>
                            <h2>Übungsleiter & Übungszeiten</h2>
                        </div>
                    </div><?php foreach ($training as $t): ?>
                        <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                                type="hidden" name="action" value="training_save"><input type="hidden" name="section"
                                value="training"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <div class="formgrid"><label>Kategorie<input name="category"
                                        value="<?= e($t['category']) ?>"></label><label>Angebot<input name="title"
                                        value="<?= e($t['title']) ?>"></label><label>Wochentag<input name="weekday"
                                        value="<?= e($t['weekday']) ?>"></label><label>Zeit<input name="time"
                                        value="<?= e($t['time']) ?>"></label><label>Altersgruppe<input name="age_group"
                                        value="<?= e($t['age_group']) ?>"></label><label>Ort<input name="location"
                                        value="<?= e($t['location']) ?>"></label><label>Übungsleiter<input name="leader"
                                        value="<?= e($t['leader']) ?>"></label><label>Sortierung<input type="number" name="sort_order"
                                        value="<?= $t['sort_order'] ?>"></label></div><label>Hinweis<textarea name="note"
                                    rows="2"><?= e($t['note']) ?></textarea></label><label class="check"><input type="checkbox"
                                    name="active" <?= $t['active'] ? 'checked' : '' ?>> Öffentlich anzeigen</label>
                            <div class="actions"><button>Speichern</button><button class="danger" name="action"
                                    value="training_delete" onclick="return confirm('Eintrag entfernen?')">Entfernen</button></div>
                        </form><?php endforeach; ?>
                    <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                            type="hidden" name="action" value="training_save"><input type="hidden" name="section"
                            value="training">
                        <h3>Neues Angebot</h3>
                        <div class="formgrid"><label>Kategorie<input name="category"
                                    value="Erwachsene"></label><label>Angebot<input name="title"
                                    required></label><label>Wochentag<input name="weekday"></label><label>Zeit<input
                                    name="time"></label><label>Altersgruppe<input name="age_group"></label><label>Ort<input
                                    name="location"></label><label>Übungsleiter<input
                                    name="leader"></label><label>Sortierung<input type="number" name="sort_order"
                                    value="999"></label></div><label>Hinweis<textarea name="note"
                                rows="2"></textarea></label><button>Angebot hinzufügen</button>
                    </form>
                </section>
            <?php elseif ($section === 'links'): ?>
                <section class="create">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">VEREIN</p>
                            <h2>Links</h2>
                        </div>
                    </div><?php foreach ($links as $l): ?>
                        <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                                type="hidden" name="action" value="link_save"><input type="hidden" name="section"
                                value="links"><input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <div class="formgrid"><label>Kategorie<input name="category"
                                        value="<?= e($l['category']) ?>"></label><label>Name<input name="title"
                                        value="<?= e($l['title']) ?>"></label><label>URL<input name="url"
                                        value="<?= e($l['url']) ?>"></label><label>Sortierung<input type="number" name="sort_order"
                                        value="<?= $l['sort_order'] ?>"></label></div><label class="check"><input type="checkbox"
                                    name="active" <?= $l['active'] ? 'checked' : '' ?>> Öffentlich anzeigen</label>
                            <div class="actions"><button>Speichern</button><button class="danger" name="action"
                                    value="link_delete">Entfernen</button></div>
                        </form><?php endforeach; ?>
                    <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                            type="hidden" name="action" value="link_save"><input type="hidden" name="section" value="links">
                        <h3>Neuer Link</h3>
                        <div class="formgrid"><label>Kategorie<input name="category"
                                    value="Weitere Links"></label><label>Name<input name="title"
                                    required></label><label>URL<input name="url"></label><label>Sortierung<input type="number"
                                    name="sort_order" value="999"></label></div><button>Link hinzufügen</button>
                    </form>
                </section>
            <?php elseif ($section === 'pages'): ?>
                <section class="create">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">VEREIN</p>
                            <h2>Vereinsseiten</h2>
                        </div>
                    </div><?php foreach ($pages as $pg): ?>
                        <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                                type="hidden" name="action" value="page"><input type="hidden" name="section" value="pages"><input
                                type="hidden" name="id" value="<?= $pg['id'] ?>">
                            <h3><?= e($pg['title']) ?></h3><label>Kurzbeschreibung<input name="intro"
                                    value="<?= e($pg['intro']) ?>"></label><label>Inhalt<textarea name="content"
                                    rows="12"><?= e($pg['content']) ?></textarea></label><label class="check"><input type="checkbox"
                                    name="active" <?= $pg['active'] ? 'checked' : '' ?>> Öffentlich
                                anzeigen</label><button>Speichern</button>
                        </form><?php endforeach; ?>
                </section>
            <?php elseif ($section === 'documents'): ?>
                <section class="create">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">VEREIN</p>
                            <h2>Dokumente & Rechtliches</h2>
                        </div>
                    </div><?php foreach ($docs as $d): ?>
                        <form method="post" enctype="multipart/form-data" class="admin-block"><input type="hidden" name="csrf"
                                value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="document"><input type="hidden"
                                name="section" value="documents"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <h3><?= e($d['title']) ?></h3>
                            <p class="hint"><?= e($d['original_filename'] ?: 'Noch keine Datei hinterlegt.') ?></p>
                            <label>Beschreibung<input name="description"
                                    value="<?= e($d['description']) ?>"></label><label>Inhalt<textarea name="content"
                                    rows="12"><?= e($d['content'] ?? '') ?></textarea></label><label>Neue Datei ersetzen<input
                                    type="file" name="file" accept="application/pdf,audio/mpeg,audio/wav"></label><label
                                class="check"><input type="checkbox" name="active" <?= $d['active'] ? 'checked' : '' ?>> Öffentlich
                                anzeigen</label><button>Speichern</button>
                        </form><?php endforeach; ?>
                </section><?php endif; ?><?php endif; ?>
    </main>
</body>

</html>