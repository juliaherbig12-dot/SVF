<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
$u = require_admin();
$db = db();
$departmentId = (int) ($_GET['department'] ?? 0);
$department = null;
if ($departmentId) {
    $q = $db->prepare('SELECT * FROM departments WHERE id=? AND active=1');
    $q->execute([$departmentId]);
    $department = $q->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    if ($action === 'save_department') {
        require_department_access($departmentId);
        $isSg = isset($_POST['is_spielgemeinschaft']) ? 1 : 0;
        $db->prepare('UPDATE departments SET is_spielgemeinschaft=?,sg_text=? WHERE id=?')->execute([$isSg, trim($_POST['sg_text'] ?? ''), $departmentId]);
        $department = $db->query('SELECT * FROM departments WHERE id=' . (int) $departmentId)->fetch();
    }
    if ($action === 'partner_save') {
        require_department_access($departmentId);
        $id = (int) ($_POST['id'] ?? 0);
        $data = [trim($_POST['name'] ?? ''), trim($_POST['url'] ?? ''), trim($_POST['instagram_url'] ?? ''), (int) ($_POST['sort_order'] ?? 0), isset($_POST['active']) ? 1 : 0];
        if ($id) {
            $db->prepare('UPDATE department_partner_links SET name=?,url=?,instagram_url=?,sort_order=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([...$data, $id]);
        } else {
            $db->prepare('INSERT INTO department_partner_links(department_id,name,url,instagram_url,sort_order,active) VALUES(?,?,?,?,?,?)')->execute([$departmentId, ...$data]);
        }
    }
    if ($action === 'partner_delete') {
        require_department_access($departmentId);
        $db->prepare('DELETE FROM department_partner_links WHERE id=?')->execute([(int) $_POST['id']]);
    }
    header('Location:/admin/sport/?department=' . $departmentId . '&saved=1');
    exit;
}
$departments = $db->query('SELECT d.*,COUNT(g.id) group_count FROM departments d LEFT JOIN groups g ON g.department_id=d.id AND g.active=1 WHERE d.active=1 GROUP BY d.id ORDER BY d.sort_order,d.name')->fetchAll();
$groups = [];
$partners = [];
if ($department) {
    require_department_access((int) $department['id']);
    $q = $db->prepare('SELECT g.*, (SELECT COUNT(*) FROM groups c WHERE c.parent_id=g.id AND c.active=1) child_count FROM groups g WHERE g.department_id=? AND g.active=1 ORDER BY g.parent_id IS NOT NULL,g.sort_order,g.name');
    $q->execute([(int) $department['id']]);
    $groups = $q->fetchAll();
    $q = $db->prepare('SELECT * FROM department_partner_links WHERE department_id=? ORDER BY sort_order,name');
    $q->execute([(int) $department['id']]);
    $partners = $q->fetchAll();
}
function icon($slug)
{
    return match ($slug) { 'fussball' => '⚽', 'volleyball' => '◉', 'turnen-gymnastik' => '✦', 'leichtathletik' => '◒', default => '◆'};
}
?><!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sportbereich – Redaktion</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
    <header>
        <div class="wrap nav">
            <div><b>SV Frohnstetten</b><span class="admin-subtitle">Redaktion</span></div>
            <nav><a href="/admin/">Dashboard</a><a href="/admin/sport/">Sportbereiche</a><a
                    href="/logout.php">Abmelden</a></nav>
        </div>
    </header>
    <main class="wrap admin-sport-page">
        <?php if (!$department): ?>
            <p class="eyebrow">REDAKTION</p>
            <h1>Sportbereiche</h1>
            <p class="lead">Wähle zuerst eine Sportart. Danach siehst du nur die Inhalte, die zu diesem Bereich gehören.</p>
            <div class="department-dashboard-grid sport-admin-grid"><?php foreach ($departments as $d): ?><a
                        class="department-dashboard-card dept-<?= e($d['slug']) ?>"
                        href="/admin/sport/?department=<?= (int) $d['id'] ?>">
                        <div class="department-card-top"><span class="dash-icon dash-icon-generic"
                                aria-hidden="true"></span><span class="department-count"><?= e((string) $d['group_count']) ?>
                                Gruppen</span></div>
                        <h3><?= e($d['name']) ?></h3>
                        <p><?= e($d['description'] ?: 'Sportbereich verwalten') ?></p><span class="button">Bereich öffnen →</span>
                    </a><?php endforeach; ?></div>
        <?php else: ?>
            <p><a href="/admin/sport/">← Sportbereiche</a></p>
            <div class="dashboard-hero">
                <div>
                    <p class="eyebrow">SPORTBEREICH</p>
                    <h1><?= e($department['name']) ?></h1>
                    <p class="lead"><?= e($department['description']) ?></p>
                </div><a class="button dashboard-site" href="/sport/?bereich=<?= e($department['slug']) ?>">Öffentliche Seite
                    →</a>
            </div>
            <div class="admin-subnav"><a class="active"
                    href="/admin/sport/?department=<?= $department['id'] ?>">Übersicht</a><a
                    href="/admin/groups/?department=<?= $department['id'] ?>">Gruppenstruktur</a><a
                    href="/admin/articles/?department=<?= $department['id'] ?>">Berichte</a></div>
            <section class="create">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">GRUPPEN & MANNSCHAFTEN</p>
                        <h2>Was möchtest du bearbeiten?</h2>
                    </div><a class="button" href="/admin/groups/?department=<?= $department['id'] ?>">+ Gruppe</a>
                </div>
                <div class="sport-admin-group-grid"><?php foreach ($groups as $g): ?>
                        <article class="sport-admin-group-card">
                            <div><span class="eyebrow-small"><?= e($department['name']) ?></span>
                                <h3><?= e($g['name']) ?></h3><?php if ($g['description']): ?>
                                    <p><?= e($g['description']) ?></p><?php endif; ?>
                            </div>
                            <div class="sport-admin-actions"><a
                                    href="/admin/sport/group.php?id=<?= $g['id'] ?>&section=overview">Übersicht</a><a
                                    href="/admin/sport/group.php?id=<?= $g['id'] ?>&section=squad">Kader</a><a
                                    href="/admin/sport/group.php?id=<?= $g['id'] ?>&section=training">Trainingszeiten</a><a
                                    href="/admin/sport/group.php?id=<?= $g['id'] ?>&section=contacts">Ansprechpartner</a><a
                                    href="/admin/articles/?group=<?= $g['id'] ?>">Berichte</a><a
                                    href="/admin/media/?group=<?= $g['id'] ?>">Bilder</a></div>
                        </article><?php endforeach; ?>
                </div>
            </section>
            <?php if ($department['slug'] === 'fussball'): ?>
                <section class="create">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">SPIELGEMEINSCHAFT</p>
                            <h2>SGM Heuberg</h2>
                        </div>
                    </div>
                    <form method="post" class="admin-block"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                            type="hidden" name="action" value="save_department"><input type="hidden" name="department_id"
                            value="<?= $department['id'] ?>"><label class="check"><input type="checkbox"
                                name="is_spielgemeinschaft" <?= $department['is_spielgemeinschaft'] ? 'checked' : '' ?>>
                            Spielgemeinschaft auf der Website kenntlich machen</label><label>Hinweistext<textarea name="sg_text"
                                rows="3"><?= e($department['sg_text'] ?? '') ?></textarea></label><button>Spielgemeinschaft
                            speichern</button></form>
                    <h3>Zugehörige Vereine</h3>
                    <div class="admin-block-list"><?php foreach ($partners as $partner): ?>
                            <form method="post" class="admin-block compact"><input type="hidden" name="csrf"
                                    value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="partner_save"><input
                                    type="hidden" name="department_id" value="<?= $department['id'] ?>"><input type="hidden" name="id"
                                    value="<?= $partner['id'] ?>">
                                <div class="formgrid"><label>Name<input name="name"
                                            value="<?= e($partner['name']) ?>"></label><label>Website<input name="url"
                                            value="<?= e($partner['url']) ?>"></label><label>Instagram<input name="instagram_url"
                                            value="<?= e($partner['instagram_url'] ?? '') ?>"
                                            placeholder="https://www.instagram.com/…"></label><label>Sortierung<input type="number"
                                            name="sort_order" value="<?= $partner['sort_order'] ?>"></label></div><label
                                    class="check"><input type="checkbox" name="active" <?= $partner['active'] ? 'checked' : '' ?>>
                                    Anzeigen</label>
                                <div class="actions"><button>Speichern</button><button class="danger" name="action"
                                        value="partner_delete"
                                        onclick="return confirm('Verein wirklich entfernen?')">Entfernen</button></div>
                            </form><?php endforeach; ?>
                        <form method="post" class="admin-block compact"><input type="hidden" name="csrf"
                                value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="partner_save"><input
                                type="hidden" name="department_id" value="<?= $department['id'] ?>">
                            <h3>Verein hinzufügen</h3>
                            <div class="formgrid"><label>Name<input name="name" required></label><label>Website<input name="url"
                                        placeholder="https://..."></label><label>Instagram<input name="instagram_url"
                                        placeholder="https://www.instagram.com/…"></label><label>Sortierung<input type="number"
                                        name="sort_order" value="999"></label></div><button>Verein hinzufügen</button>
                        </form>
                    </div>
                </section><?php endif; ?>
        <?php endif; ?>
    </main>
</body>

</html>