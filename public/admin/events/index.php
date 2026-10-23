<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
$u = require_admin();
$db = db();
function redirect_events(): void
{
    header('Location:/admin/events/');
    exit;
}
function event_label(string $t): string
{
    return ['termin' => 'Termin', 'training' => 'Training', 'spiel' => 'Spiel', 'veranstaltung' => 'Veranstaltung'][$t] ?? 'Termin';
}
function fmt_date(?string $v): string
{
    return $v ? date('d.m.Y H:i', strtotime($v)) : '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'archive' || $action === 'restore') {
        $db->prepare('UPDATE events SET active=? WHERE id=?')->execute([$action === 'restore' ? 1 : 0, $id]);
        redirect_events();
    }
    if ($action === 'delete') {
        $db->prepare('DELETE FROM events WHERE id=?')->execute([$id]);
        redirect_events();
    }
    if ($action === 'save') {
        $title = trim($_POST['title'] ?? '');
        $start = trim($_POST['start_date'] ?? '');
        $type = $_POST['event_type'] ?? 'termin';
        if (!in_array($type, ['termin', 'training', 'spiel', 'veranstaltung'], true))
            $type = 'termin';
        $department = (int) ($_POST['department_id'] ?? 0) ?: null;
        $group = (int) ($_POST['group_id'] ?? 0) ?: null;
        if ($title && $start) {
            $data = [$title, trim($_POST['description'] ?? ''), $start, trim($_POST['end_date'] ?? '') ?: null, trim($_POST['location'] ?? ''), $department, $group, $type, trim($_POST['home_team'] ?? ''), trim($_POST['away_team'] ?? ''), trim($_POST['result'] ?? '')];
            if ($id) {
                $db->prepare('UPDATE events SET title=?,description=?,start_date=?,end_date=?,location=?,department_id=?,group_id=?,event_type=?,home_team=?,away_team=?,result=? WHERE id=?')->execute([...$data, $id]);
            } else {
                $db->prepare('INSERT INTO events(title,description,start_date,end_date,location,department_id,group_id,event_type,home_team,away_team,result,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$data, (int) $u['id']]);
            }
        }
        redirect_events();
    }
}
$edit = null;
if (!empty($_GET['id'])) {
    $s = $db->prepare('SELECT * FROM events WHERE id=?');
    $s->execute([(int) $_GET['id']]);
    $edit = $s->fetch();
}
$deps = $db->query('SELECT id,name FROM departments WHERE active=1 ORDER BY sort_order,name')->fetchAll();
$groups = $db->query('SELECT id,department_id,parent_id,name,active FROM groups WHERE active=1 ORDER BY department_id,parent_id,sort_order,name')->fetchAll();
$events = $db->query('SELECT e.*,d.name department_name,g.name group_name FROM events e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN groups g ON g.id=e.group_id ORDER BY e.start_date DESC LIMIT 100')->fetchAll();
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Termine & Spiele – SV Frohnstetten</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
    <header>
        <div class="wrap nav"><b>SV Frohnstetten</b>
            <nav><a href="/admin/">Dashboard</a><a href="/">Website</a><a href="/admin/sponsors/">Sponsoren</a></nav>
        </div>
    </header>
    <main class="wrap">
        <p class="eyebrow">TERMINE & SPIELE</p>
        <h1><?= $edit ? 'Termin bearbeiten' : 'Neuer Termin' ?></h1>
        <section class="create">
            <form method="post" class="formgrid"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input
                    type="hidden" name="action" value="save"><input type="hidden" name="id"
                    value="<?= e((string) ($edit['id'] ?? 0)) ?>">
                <label>Titel<input name="title" value="<?= e($edit['title'] ?? '') ?>"
                        placeholder="z. B. Heimspiel gegen TSV ..." required></label>
                <label>Art<select name="event_type" id="event_type">
                        <option value="termin" <?= ($edit['event_type'] ?? 'termin') === 'termin' ? 'selected' : '' ?>>Termin
                        </option>
                        <option value="training" <?= ($edit['event_type'] ?? '') === 'training' ? 'selected' : '' ?>>Training
                        </option>
                        <option value="spiel" <?= ($edit['event_type'] ?? '') === 'spiel' ? 'selected' : '' ?>>Spiel</option>
                        <option value="veranstaltung" <?= ($edit['event_type'] ?? '') === 'veranstaltung' ? 'selected' : '' ?>>
                            Veranstaltung</option>
                    </select></label>
                <label>Beginn<input type="datetime-local" name="start_date"
                        value="<?= e(isset($edit['start_date']) ? date('Y-m-d\TH:i', strtotime($edit['start_date'])) : '') ?>"
                        required></label>
                <label>Ende<input type="datetime-local" name="end_date"
                        value="<?= e(!empty($edit['end_date']) ? date('Y-m-d\TH:i', strtotime($edit['end_date'])) : '') ?>"></label>
                <label>Sportangebot<select name="department_id">
                        <option value="0">– kein Bereich –</option><?php foreach ($deps as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ((int) ($edit['department_id'] ?? 0) === $d['id']) ? 'selected' : '' ?>>
                                <?= e($d['name']) ?></option><?php endforeach; ?>
                    </select></label>
                <label>Gruppe<select name="group_id">
                        <option value="0">– keine Gruppe –</option><?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>" data-dept="<?= $g['department_id'] ?>"
                                <?= ((int) ($edit['group_id'] ?? 0) === $g['id']) ? 'selected' : '' ?>>
                                <?= e(group_option_label($db, $g)) ?></option><?php endforeach; ?>
                    </select></label>
                <label>Ort<input name="location" value="<?= e($edit['location'] ?? '') ?>"
                        placeholder="z. B. Sportplatz Frohnstetten"></label>
                <label>Beschreibung<input name="description" value="<?= e($edit['description'] ?? '') ?>"></label>
                <div id="match-fields" class="match-fields"><label>Heimteam<input name="home_team"
                            value="<?= e($edit['home_team'] ?? '') ?>"></label><label>Gastteam<input name="away_team"
                            value="<?= e($edit['away_team'] ?? '') ?>"></label><label>Ergebnis<input name="result"
                            value="<?= e($edit['result'] ?? '') ?>" placeholder="z. B. 3:1"></label></div>
                <button><?= $edit ? 'Speichern' : 'Termin anlegen' ?></button><?php if ($edit): ?><a class="button secondary"
                        href="/admin/events/">Abbrechen</a><?php endif; ?>
            </form>
        </section>
        <section class="dept">
            <h2>Alle Termine</h2><?php if (!$events): ?>
                <p class="hint">Noch keine Termine angelegt.</p><?php endif; ?>
            <div class="event-list"><?php foreach ($events as $ev): ?>
                    <article class="event-row <?= !$ev['active'] ? 'archived' : '' ?>">
                        <div><strong><?= e($ev['title']) ?></strong> <span
                                class="status <?= ($ev['active'] ? 'active' : 'archived') ?>"><?= e(event_label($ev['event_type'] ?? 'termin')) ?></span><small><?= e(fmt_date($ev['start_date'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?><?= $ev['department_name'] ? ' · ' . e($ev['department_name']) : '' ?><?= $ev['group_name'] ? ' / ' . e($ev['group_name']) : '' ?></small><?php if (($ev['event_type'] ?? '') === 'spiel' && ($ev['home_team'] || $ev['away_team'])): ?><small><?= e($ev['home_team']) ?>
                                    –
                                    <?= e($ev['away_team']) ?>        <?= $ev['result'] ? ' · ' . e($ev['result']) : '' ?></small><?php endif; ?>
                        </div>
                        <div class="actions"><a href="/admin/events/?id=<?= $ev['id'] ?>">Bearbeiten</a>
                            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden"
                                    name="id" value="<?= $ev['id'] ?>"><button name="action"
                                    value="<?= $ev['active'] ? 'archive' : 'restore' ?>"><?= $ev['active'] ? 'Archivieren' : 'Aktivieren' ?></button>
                            </form><?php if (!$ev['active']): ?>
                                <form method="post" onsubmit="return confirm('Termin endgültig löschen?')"><input type="hidden"
                                        name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id"
                                        value="<?= $ev['id'] ?>"><button class="danger" name="action"
                                        value="delete">Löschen</button></form><?php endif; ?>
                        </div>
                    </article><?php endforeach; ?>
            </div>
        </section>
    </main>
    <script>const type = document.getElementById('event_type'), match = document.getElementById('match-fields'); function sync() { match.style.display = type.value === 'spiel' ? 'grid' : 'none' } type.addEventListener('change', sync); sync();</script>
</body>

</html>