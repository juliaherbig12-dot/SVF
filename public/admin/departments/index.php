<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
require_admin_only();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $s = db()->prepare("SELECT COALESCE(MAX(sort_order),0)+10 FROM departments");
            $s->execute();
            $order = (int) $s->fetchColumn();
            $s = db()->prepare("INSERT INTO departments(name,slug,description,icon,sort_order) VALUES(?,?,?,?,?)");
            $s->execute([$name, slugify($name), trim($_POST['description'] ?? ''), trim($_POST['icon'] ?? ''), $order]);
        }
    }
    if ($action === 'update') {
        db()->prepare("UPDATE departments SET name=?,slug=?,description=?,icon=? WHERE id=?")->execute([trim($_POST['name']), slugify($_POST['name']), trim($_POST['description'] ?? ''), trim($_POST['icon'] ?? ''), $id]);
    }
    if ($action === 'archive')
        db()->prepare("UPDATE departments SET active=0 WHERE id=?")->execute([$id]);
    if ($action === 'restore')
        db()->prepare("UPDATE departments SET active=1 WHERE id=?")->execute([$id]);
    if ($action === 'delete')
        db()->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
    header('Location:/admin/departments/');
    exit;
}
$deps = db()->query("SELECT * FROM departments ORDER BY sort_order,name")->fetchAll();
function tree(int $d, ?int $p = null): void
{
    $sql = "SELECT * FROM groups WHERE department_id=? AND parent_id " . ($p === null ? "IS NULL" : "=?") . " ORDER BY sort_order,name";
    $s = db()->prepare($sql);
    $p === null ? $s->execute([$d]) : $s->execute([$d, $p]);
    foreach ($s as $g) {
        echo '<li class="' . (!$g['active'] ? 'archived' : '') . '">' . e($g['name']) . ' <span class="status ' . ($g['active'] ? 'active' : 'archived') . '">' . ($g['active'] ? 'Aktiv' : 'Archiviert') . '</span> <a href="/admin/groups/?id=' . (int) $g['id'] . '">Bearbeiten</a></li>';
        tree($d, (int) $g['id']);
    }
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sportstruktur</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
    <header>
        <div class="wrap nav"><b>SV Frohnstetten</b>
            <nav><a href="/admin/">Dashboard</a><a href="/">Website</a><a href="/admin/sponsors/">Sponsoren</a></nav>
        </div>
    </header>
    <main class="wrap">
        <p class="eyebrow">STRUKTUR</p>
        <h1>Sportangebote</h1>
        <section class="create">
            <h2>+ Sportangebot</h2>
            <form method="post" class="formgrid"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                    type="hidden" name="action" value="create"><input name="name" placeholder="z. B. Basketball"
                    required><input name="icon" placeholder="Icon"><input name="description"
                    placeholder="Beschreibung"><button>Anlegen</button></form>
        </section>
        <?php foreach ($deps as $d): ?>
            <section class="dept <?= !$d['active'] ? 'archived' : '' ?>">
                <div class="depthead">
                    <div>
                        <h2><?= e($d['icon']) ?>     <?= e($d['name']) ?></h2>
                        <p><?= e($d['description']) ?></p>
                    </div>
                    <div class="actions"><a href="/admin/groups/?department=<?= $d['id'] ?>">+ Gruppe</a>
                        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden"
                                name="id" value="<?= $d['id'] ?>"><button name="action"
                                value="<?= $d['active'] ? 'archive' : 'restore' ?>"><?= $d['active'] ? 'Archivieren' : 'Aktivieren' ?></button>
                        </form><?php if (!$d['active']): ?>
                            <form method="post" onsubmit="return confirm('Sportangebot endgültig löschen?')"><input
                                    type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id"
                                    value="<?= $d['id'] ?>"><button class="danger" name="action" value="delete">Löschen</button>
                            </form><?php endif; ?>
                    </div>
                </div>
                <ul class="tree"><?php tree((int) $d['id']); ?></ul>
            </section><?php endforeach; ?>
    </main>
</body>

</html>