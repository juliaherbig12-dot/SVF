<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
$u = require_admin();
$db = db();
$id = (int) ($_GET['id'] ?? $_POST['group_id'] ?? 0);
$section = $_GET['section'] ?? 'overview';
$q = $db->prepare('SELECT g.*,d.name department_name,d.slug department_slug FROM groups g JOIN departments d ON d.id=g.department_id WHERE g.id=? AND g.active=1');
$q->execute([$id]);
$group = $q->fetch();
if (!$group) {
    http_response_code(404);
    exit('Gruppe nicht gefunden.');
}
require_department_access((int) $group['department_id']);
$isFootball = strtolower((string) $group['department_slug']) === 'fussball';
$widgetTypes = ['next-match' => 'Nächstes Spiel', 'table' => 'Tabelle', 'last-matches' => 'Letzte Spiele', 'schedule' => 'Spielplan'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $section = $_POST['section'] ?? 'overview';
    if ($action === 'training_save') {
        $tid = (int) ($_POST['id'] ?? 0);
        $data = [trim($_POST['title'] ?? ''), trim($_POST['weekday'] ?? ''), trim($_POST['time'] ?? ''), trim($_POST['age_group'] ?? ''), trim($_POST['location'] ?? ''), trim($_POST['leader'] ?? ''), trim($_POST['note'] ?? ''), (int) ($_POST['sort_order'] ?? 0), isset($_POST['active']) ? 1 : 0];
        if ($tid)
            $db->prepare('UPDATE group_training SET title=?,weekday=?,time=?,age_group=?,location=?,leader=?,note=?,sort_order=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND group_id=?')->execute([...$data, $tid, $id]);
        else
            $db->prepare('INSERT INTO group_training(group_id,title,weekday,time,age_group,location,leader,note,sort_order,active) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$id, ...$data]);
    }
    if ($action === 'training_delete')
        $db->prepare('DELETE FROM group_training WHERE id=? AND group_id=?')->execute([(int) $_POST['id'], $id]);
    if ($action === 'contact_save') {
        $cid = (int) ($_POST['id'] ?? 0);
        $data = [trim($_POST['role'] ?? ''), trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''), (int) ($_POST['sort_order'] ?? 0), isset($_POST['active']) ? 1 : 0];
        if ($cid)
            $db->prepare('UPDATE group_contacts SET role=?,name=?,phone=?,email=?,sort_order=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND group_id=?')->execute([...$data, $cid, $id]);
        else
            $db->prepare('INSERT INTO group_contacts(group_id,role,name,phone,email,sort_order,active) VALUES(?,?,?,?,?,?,?)')->execute([$id, ...$data]);
    }
    if ($action === 'contact_delete')
        $db->prepare('DELETE FROM group_contacts WHERE id=? AND group_id=?')->execute([(int) $_POST['id'], $id]);
    if ($action === 'football_widgets_save' && $isFootball) {
        $url = trim($_POST['football_url'] ?? '');
        foreach ($widgetTypes as $type => $label) {
            $code = trim($_POST['widget_code'][$type] ?? '');
            if ($code || $url) {
                $db->prepare("INSERT INTO football_widgets(group_id,widget_type,widget_code,football_url,active,updated_at) VALUES(?,?,?,?,1,CURRENT_TIMESTAMP) ON CONFLICT(group_id,widget_type) DO UPDATE SET widget_code=excluded.widget_code,football_url=excluded.football_url,active=1,updated_at=CURRENT_TIMESTAMP")->execute([$id,$type,$code,$url]);
            } else {
                $db->prepare("DELETE FROM football_widgets WHERE group_id=? AND widget_type=?")->execute([$id,$type]);
            }
        }
    }
    if ($action === 'group_update') {
        $db->prepare('UPDATE groups SET name=?,description=?,sort_order=? WHERE id=?')->execute([trim($_POST['name'] ?? ''), trim($_POST['description'] ?? ''), (int) ($_POST['sort_order'] ?? 0), $id]);
    }
    header('Location:/admin/sport/group.php?id=' . $id . '&section=' . urlencode($section) . '&saved=1');
    exit;
}
$training = $db->prepare('SELECT * FROM group_training WHERE group_id=? ORDER BY sort_order,title');
$training->execute([$id]);
$training = $training->fetchAll();
$contacts = $db->prepare('SELECT * FROM group_contacts WHERE group_id=? ORDER BY sort_order,name');
$contacts->execute([$id]);
$contacts = $contacts->fetchAll();
$articleCount = (int) $db->prepare('SELECT COUNT(*) FROM articles WHERE group_id=? AND active=1')->execute([$id]);
$articleCount = 0;
$st = $db->prepare('SELECT COUNT(*) FROM articles WHERE group_id=? AND active=1');
$st->execute([$id]);
$articleCount = (int) $st->fetchColumn();
$mediaCount = 0;
$st = $db->prepare('SELECT COUNT(*) FROM media WHERE group_id=?');
$st->execute([$id]);
$mediaCount = (int) $st->fetchColumn();
$childStmt = $db->prepare('SELECT * FROM groups WHERE parent_id=? AND active=1 ORDER BY sort_order,name');
$childStmt->execute([$id]);
$childGroups = $childStmt->fetchAll();
$widgets = [];
if ($isFootball) {
    $st = $db->prepare('SELECT * FROM football_widgets WHERE group_id=? AND active=1');
    $st->execute([$id]);
    foreach ($st->fetchAll() as $w) $widgets[$w['widget_type']] = $w;
}
?><!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($group['name']) ?> – Redaktion</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
    <header>
        <div class="wrap nav">
            <div><b>SV Frohnstetten</b><span class="admin-subtitle">Redaktion</span></div>
            <nav><a href="/admin/sport/?department=<?= $group['department_id'] ?>">←
                    <?= e($group['department_name']) ?></a><a href="/admin/">Dashboard</a></nav>
        </div>
    </header>
    <main class="wrap admin-group-page">
        <p class="eyebrow"><?= e($group['department_name']) ?></p>
        <h1><?= e($group['name']) ?></h1>
        <p class="lead">Hier bearbeitest du ausschließlich diesen Bereich.</p>
        <div class="actions" style="margin-bottom:1rem"><a class="button secondary" href="/sport/?bereich=<?= e($group['department_slug']) ?>&gruppe_id=<?= $id ?>">Öffentliche Seite ansehen →</a><?php if ($childGroups): ?><a class="button secondary" href="#untergruppen">Untergruppen ansehen</a><?php endif; ?></div>
        <div class="admin-subnav"><a class="<?= $section === 'overview' ? 'active' : '' ?>" href="?id=<?= $id ?>&section=overview">Übersicht</a><a class="<?= $section === 'squad' ? 'active' : '' ?>" href="?id=<?= $id ?>&section=squad">Kader</a><a class="<?= $section === 'training' ? 'active' : '' ?>" href="?id=<?= $id ?>&section=training">Trainingszeiten</a><a class="<?= $section === 'contacts' ? 'active' : '' ?>" href="?id=<?= $id ?>&section=contacts">Ansprechpartner</a><?php if ($isFootball): ?><a class="<?= $section === 'widgets' ? 'active' : '' ?>" href="?id=<?= $id ?>&section=widgets">FUSSBALL.DE</a><?php endif; ?><a href="/admin/articles/?group=<?= $id ?>">Berichte</a><a href="/admin/media/?group=<?= $id ?>">Bilder</a></div>
        <?php if (isset($_GET['saved'])): ?>
            <p class="success">Änderung gespeichert.</p><?php endif; ?>
        <?php if ($section === 'overview'): ?>
            <section class="create">
                <h2>Bereich</h2>
                <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                        type="hidden" name="action" value="group_update"><input type="hidden" name="section"
                        value="overview"><input type="hidden" name="group_id" value="<?= $id ?>"><label>Name<input name="name"
                            value="<?= e($group['name']) ?>" required></label><label>Beschreibung<textarea name="description"
                            rows="4"><?= e($group['description']) ?></textarea></label><label>Sortierung<input type="number"
                            name="sort_order" value="<?= $group['sort_order'] ?>"></label><button>Bereich speichern</button>
                </form>
            </section>
            <?php if ($childGroups): ?>
            <section class="create" id="untergruppen">
                <div class="section-heading"><div><p class="eyebrow">STRUKTUR</p><h2>Untergruppen</h2></div><a class="button secondary" href="/admin/groups/?id=<?= $id ?>">Struktur verwalten →</a></div>
                <div class="quick-dashboard-grid">
                    <?php foreach ($childGroups as $child): ?>
                        <a class="quick-card" href="/admin/sport/group.php?id=<?= (int) $child['id'] ?>&section=overview"><strong><?= e($child['name']) ?></strong><small><?= e($child['description'] ?: 'Untergruppe bearbeiten') ?></small></a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <div class="quick-dashboard-grid"><a class="quick-card"
                    href="?id=<?= $id ?>&section=squad"><strong>Kader</strong><small>Spieler, Fotos und
                        Trainer/Betreuer</small></a><a class="quick-card"
                    href="?id=<?= $id ?>&section=training"><strong>Trainingszeiten</strong><small>Zeiten, Orte und
                        Leitung</small></a><a class="quick-card"
                    href="?id=<?= $id ?>&section=contacts"><strong>Ansprechpartner</strong><small>Kontaktpersonen dieses
                        Bereichs</small></a><a class="quick-card"
                    href="/admin/articles/?group=<?= $id ?>"><strong>Berichte</strong><small><?= $articleCount ?> Beiträge für
                        diesen Bereich</small></a><a class="quick-card"
                    href="/admin/media/?group=<?= $id ?>"><strong>Bilder</strong><small><?= $mediaCount ?> Bilder zu diesem
                        Bereich</small></a></div>
        <?php elseif ($section === 'squad'): ?>
            <section class="create">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">KADER</p>
                        <h2>Spieler & Trainer</h2>
                    </div><a class="button" href="/admin/squad/?gruppe_id=<?= $id ?>">Kader öffnen →</a>
                </div>
                <p class="hint">Die vollständige Kaderbearbeitung öffnet sich nur für diesen Bereich.</p>
            </section>
        <?php elseif ($section === 'widgets' && $isFootball): ?>
            <section class="create">
                <div class="section-heading"><div><p class="eyebrow">FUSSBALL.DE</p><h2>Widgets dieser Mannschaft</h2></div></div>
                <p class="hint">Hier kannst du die offiziellen FUSSBALL.DE-Widgets direkt für diese Mannschaft hinterlegen. Der Kader bleibt davon getrennt und wird manuell gepflegt.</p>
                <form method="post" class="admin-block">
                    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="football_widgets_save"><input type="hidden" name="section" value="widgets"><input type="hidden" name="group_id" value="<?= $id ?>">
                    <label>FUSSBALL.DE-Seite<input name="football_url" value="<?= e($widgets[array_key_first($widgets)]['football_url'] ?? '') ?>" placeholder="https://www.fussball.de/..."></label>
                    <?php foreach ($widgetTypes as $type => $label): ?>
                        <label><?= e($label) ?><textarea name="widget_code[<?= e($type) ?>]" rows="7" placeholder="Kompletten offiziellen Widget-Code einfügen"><?= e($widgets[$type]['widget_code'] ?? '') ?></textarea></label>
                    <?php endforeach; ?>
                    <button>FUSSBALL.DE-Widgets speichern</button>
                </form>
            </section>
        <?php elseif ($section === 'training'): ?>
            <section class="create">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">TRAINING</p>
                        <h2>Trainingszeiten</h2>
                    </div>
                </div><?php foreach ($training as $t): ?>
                    <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                            type="hidden" name="action" value="training_save"><input type="hidden" name="section"
                            value="training"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden"
                            name="group_id" value="<?= $id ?>">
                        <div class="formgrid"><label>Angebot<input name="title" value="<?= e($t['title']) ?>"
                                    required></label><label>Wochentag<input name="weekday"
                                    value="<?= e($t['weekday']) ?>"></label><label>Zeit<input name="time"
                                    value="<?= e($t['time']) ?>"></label><label>Altersgruppe<input name="age_group"
                                    value="<?= e($t['age_group']) ?>"></label><label>Ort<input name="location"
                                    value="<?= e($t['location']) ?>"></label><label>Leitung<input name="leader"
                                    value="<?= e($t['leader']) ?>"></label><label>Sortierung<input type="number" name="sort_order"
                                    value="<?= $t['sort_order'] ?>"></label></div><label>Hinweis<textarea name="note"
                                rows="2"><?= e($t['note']) ?></textarea></label><label class="check"><input type="checkbox"
                                name="active" <?= $t['active'] ? 'checked' : '' ?>> Öffentlich anzeigen</label>
                        <div class="actions"><button>Speichern</button><button class="danger" name="action"
                                value="training_delete" onclick="return confirm('Eintrag entfernen?')">Entfernen</button></div>
                    </form><?php endforeach; ?>
                <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                        type="hidden" name="action" value="training_save"><input type="hidden" name="section"
                        value="training"><input type="hidden" name="group_id" value="<?= $id ?>">
                    <h3>Neue Trainingszeit</h3>
                    <div class="formgrid"><label>Angebot<input name="title" required></label><label>Wochentag<input
                                name="weekday"></label><label>Zeit<input name="time"></label><label>Altersgruppe<input
                                name="age_group"></label><label>Ort<input name="location"></label><label>Leitung<input
                                name="leader"></label><label>Sortierung<input type="number" name="sort_order"
                                value="999"></label></div><label>Hinweis<textarea name="note"
                            rows="2"></textarea></label><button>Trainingszeit hinzufügen</button>
                </form>
            </section>
        <?php elseif ($section === 'contacts'): ?>
            <section class="create">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">KONTAKT</p>
                        <h2>Ansprechpartner</h2>
                    </div>
                </div><?php foreach ($contacts as $c): ?>
                    <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                            type="hidden" name="action" value="contact_save"><input type="hidden" name="section"
                            value="contacts"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden"
                            name="group_id" value="<?= $id ?>">
                        <div class="formgrid"><label>Funktion<input name="role"
                                    value="<?= e($c['role']) ?>"></label><label>Name<input name="name" value="<?= e($c['name']) ?>"
                                    required></label><label>Telefon<input name="phone"
                                    value="<?= e($c['phone']) ?>"></label><label>E-Mail<input name="email"
                                    value="<?= e($c['email']) ?>"></label><label>Sortierung<input type="number" name="sort_order"
                                    value="<?= $c['sort_order'] ?>"></label></div><label class="check"><input type="checkbox"
                                name="active" <?= $c['active'] ? 'checked' : '' ?>> Öffentlich anzeigen</label>
                        <div class="actions"><button>Speichern</button><button class="danger" name="action"
                                value="contact_delete" onclick="return confirm('Ansprechpartner entfernen?')">Entfernen</button>
                        </div>
                    </form><?php endforeach; ?>
                <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                        type="hidden" name="action" value="contact_save"><input type="hidden" name="section"
                        value="contacts"><input type="hidden" name="group_id" value="<?= $id ?>">
                    <h3>Neuer Ansprechpartner</h3>
                    <div class="formgrid"><label>Funktion<input name="role"></label><label>Name<input name="name"
                                required></label><label>Telefon<input name="phone"></label><label>E-Mail<input
                                name="email"></label><label>Sortierung<input type="number" name="sort_order"
                                value="999"></label></div><button>Ansprechpartner hinzufügen</button>
                </form>
            </section><?php endif; ?>
    </main>
</body>

</html>