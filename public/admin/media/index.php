<?php
require __DIR__ . '/../../../src/bootstrap-local.php';
require __DIR__ . '/../../../src/media.php';
$u = require_admin();
$msg = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $groupId = (int) ($_POST['group_id'] ?? 0);
    if ($act === 'upload' && !empty($_FILES['images']['name'][0])) {
        $dir = __DIR__ . '/../../uploads';
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $f = $_FILES['images'];
        for ($i = 0; $i < count($f['name']); $i++) {
            if ($f['error'][$i] !== UPLOAD_ERR_OK) {
                $error = 'Mindestens eine Datei konnte nicht hochgeladen werden. Fehlercode: ' . (int) $f['error'][$i];
                continue;
            }
            if ($f['size'][$i] > 12 * 1024 * 1024) {
                $error = 'Eine Datei ist größer als 12 MB und wurde übersprungen.';
                continue;
            }
            $mime = detect_mime_type($f['tmp_name'][$i]);
            if (!isset($allowed[$mime])) {
                $error = 'Mindestens eine Datei wurde wegen eines nicht unterstützten Bildformats übersprungen.';
                continue;
            }
            $safe = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
            if (!optimize_image($f['tmp_name'][$i], $mime, $dir . '/' . $safe, 1800, 82)) {
                $error = 'Das Bild konnte nicht verarbeitet werden: ' . $f['name'][$i];
                continue;
            }
            $st = db()->prepare("INSERT INTO media(filename,storage_path,mime_type,size_bytes,alt_text,uploaded_by,group_id)VALUES(?,?,?,?,?,?,?)");
            $st->execute([$f['name'][$i], 'uploads/' . $safe, $mime, (int) $f['size'][$i], trim($_POST['alt_text'] ?? ''), $u['id'], $groupId ?: null]);
        }
        $msg = 'Upload abgeschlossen. Die Bilder sind jetzt unten in der Medienbibliothek sichtbar.';
    }
    if ($act === 'alt') {
        db()->prepare("UPDATE media SET alt_text=? WHERE id=?")->execute([trim($_POST['alt_text'] ?? ''), $id]);
        $msg = 'Alt-Text gespeichert.';
    }
    if ($act === 'delete') {
        $st = db()->prepare("SELECT storage_path FROM media WHERE id=?");
        $st->execute([$id]);
        $m = $st->fetch();
        if ($m) {
            @unlink(__DIR__ . '/../../' . $m['storage_path']);
            db()->prepare("DELETE FROM article_media WHERE media_id=?")->execute([$id]);
            db()->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
            $msg = 'Bild gelöscht.';
        }
    }
}
$filterGroup = (int) ($_GET['group'] ?? 0);
if ($filterGroup) {
    $st = db()->prepare("SELECT * FROM media WHERE group_id=? ORDER BY created_at DESC");
    $st->execute([$filterGroup]);
    $media = $st->fetchAll();
} else
    $media = db()->query("SELECT * FROM media ORDER BY created_at DESC")->fetchAll();
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Medien – SV Frohnstetten</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body>
    <header>
        <div class="wrap nav"><b>SV Frohnstetten</b>
            <nav><a href="/admin/">Dashboard</a><a href="/admin/articles/">Berichte</a><a href="/">Website</a><a
                    href="/admin/sponsors/">Sponsoren</a><a href="/admin/verein/">Unser Verein</a></nav>
        </div>
    </header>
    <main class="wrap">
        <p class="eyebrow">MEDIEN</p>
        <h1>Medienbibliothek</h1>
        <p class="media-count"><?= count($media ?? []) ?> Bild<?= count($media ?? []) === 1 ? '' : 'er' ?> gespeichert</p><?php if ($filterGroup): ?>
            <p class="context-filter">Bilder für einen ausgewählten Bereich · <a href="/admin/media/">Alle Medien
                    anzeigen</a></p><?php endif; ?>
        <?php if ($msg): ?>
            <p class="success"><?= e($msg) ?></p><?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p><?php endif; ?>
        <section class="create">
            <h2>Bilder hochladen</h2>
            <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf"
                    value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="upload"><input type="hidden"
                    name="group_id" value="<?= e((string) $filterGroup) ?>"><input type="file" name="images[]"
                    accept="image/jpeg,image/png,image/webp,image/gif" multiple required><label>Alt-Text<input
                        name="alt_text" placeholder="z. B. Mannschaft vor dem Spiel"></label><button>Hochladen</button>
            </form>
            <p class="hint">JPG, PNG, WebP oder GIF · max. 12 MB pro Datei.</p>
        </section>
        <section class="media-grid"><?php if (!$media): ?><div class="media-empty"><strong>Noch keine Bilder vorhanden.</strong><p>Lade oben ein Bild hoch. Danach erscheint es hier sofort als Vorschau und kann z. B. als Sponsorlogo ausgewählt werden.</p></div><?php endif; ?><?php foreach ($media as $m): ?>
                <div class="media-card"><a class="media-preview" href="/<?= e($m['storage_path']) ?>" target="_blank" rel="noopener"><img src="/<?= e($m['storage_path']) ?>" alt="<?= e($m['alt_text'] ?? $m['filename']) ?>"></a><strong><?= e($m['filename']) ?></strong><small><?= e($m['mime_type']) ?> · <?= number_format((int)$m['size_bytes']/1024, 0, ',', '.') ?> KB</small><a class="media-url" href="/<?= e($m['storage_path']) ?>" target="_blank" rel="noopener">Bild öffnen</a>
                    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden"
                            name="action" value="alt"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input
                            name="alt_text" value="<?= e($m['alt_text']) ?>" placeholder="Alt-Text"><button>Speichern</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Bild endgültig löschen?')"><input type="hidden"
                            name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="delete"><input
                            type="hidden" name="id" value="<?= $m['id'] ?>"><button class="danger">Löschen</button></form>
                </div><?php endforeach; ?>
        </section>
    </main>
</body>

</html>