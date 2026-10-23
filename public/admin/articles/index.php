<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'publish')
        db()->prepare("UPDATE articles SET published_at=datetime('now'),active=1 WHERE id=?")->execute([$id]);
    if ($action === 'unpublish')
        db()->prepare("UPDATE articles SET published_at=NULL WHERE id=?")->execute([$id]);
    if ($action === 'delete')
        db()->prepare("UPDATE articles SET active=0 WHERE id=?")->execute([$id]);
    header('Location:/admin/articles/');
    exit;
}
$groupFilter = (int) ($_GET['group'] ?? 0);
$departmentFilter = (int) ($_GET['department'] ?? 0);
if ($groupFilter) {
    $st = db()->prepare("SELECT a.*,d.name department_name,g.name group_name FROM articles a LEFT JOIN departments d ON d.id=a.department_id LEFT JOIN groups g ON g.id=a.group_id WHERE a.group_id=? ORDER BY a.created_at DESC");
    $st->execute([$groupFilter]);
    $rows = $st->fetchAll();
} elseif ($departmentFilter) {
    $st = db()->prepare("SELECT a.*,d.name department_name,g.name group_name FROM articles a LEFT JOIN departments d ON d.id=a.department_id LEFT JOIN groups g ON g.id=a.group_id WHERE a.department_id=? ORDER BY a.created_at DESC");
    $st->execute([$departmentFilter]);
    $rows = $st->fetchAll();
} else
    $rows = db()->query("SELECT a.*,d.name department_name,g.name group_name FROM articles a LEFT JOIN departments d ON d.id=a.department_id LEFT JOIN groups g ON g.id=a.group_id ORDER BY a.created_at DESC")->fetchAll();
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Berichte</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
    <header>
        <div class="wrap nav"><b>SV Frohnstetten</b>
            <nav><a href="/admin/">Dashboard</a><a href="/">Website</a><a href="/admin/sponsors/">Sponsoren</a></nav>
        </div>
    </header>
    <main class="wrap">
        <div class="top">
            <div>
                <p class="eyebrow">REDAKTION</p>
                <h1>Berichte</h1><?php if ($groupFilter || $departmentFilter): ?>
                    <p class="context-filter">Gefilterter Bereich · <a href="/admin/articles/">Alle Berichte anzeigen</a>
                    </p><?php endif; ?>
            </div><a class="button" href="/admin/articles/edit.php">+ Neuer Bericht</a>
        </div>
        <?php foreach ($rows as $a): ?>
            <section class="dept">
                <div class="depthead">
                    <div>
                        <h2><?= e($a['title']) ?></h2>
                        <p><?= e($a['department_name'] ?? 'Verein allgemein') ?><?= $a['group_name'] ? ' · ' . e($a['group_name']) : '' ?>
                        </p>
                        <small><?= $a['published_at'] ? 'Veröffentlicht am ' . e(date('d.m.Y H:i', strtotime($a['published_at']))) : 'Entwurf' ?></small>
                    </div>
                    <div class="actions"><a href="/admin/articles/edit.php?id=<?= $a['id'] ?>">Bearbeiten</a>
                        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden"
                                name="id" value="<?= $a['id'] ?>"><button name="action"
                                value="<?= $a['published_at'] ? 'unpublish' : 'publish' ?>"><?= $a['published_at'] ? 'Deaktivieren' : 'Veröffentlichen' ?></button>
                        </form>
                        <form method="post" onsubmit="return confirm('Bericht löschen?')"><input type="hidden" name="csrf"
                                value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button
                                class="danger" name="action" value="delete">Löschen</button></form>
                    </div>
                </div>
            </section><?php endforeach; ?>
        <?php if (!$rows): ?>
            <section class="create">
                <p>Noch keine Berichte vorhanden.</p>
            </section><?php endif; ?>
    </main>
</body>

</html>