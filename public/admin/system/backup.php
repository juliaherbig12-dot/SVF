<?php
declare(strict_types=1);
require __DIR__ . '/../../../src/bootstrap-local.php';
$u = require_admin_only();

$path = db()->query("PRAGMA database_list")->fetchAll();
$dbPath = null;
foreach ($path as $row) {
    if (($row['name'] ?? '') === 'main') {
        $dbPath = $row['file'] ?? null;
        break;
    }
}
if (!$dbPath || !is_file($dbPath)) {
    http_response_code(500);
    exit('Datenbankdatei konnte nicht gefunden werden.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $stamp = date('Y-m-d_H-i-s');
    $download = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sv-frohnstetten-backup-' . $stamp . '.sqlite';
    if (!copy($dbPath, $download)) {
        http_response_code(500);
        exit('Backup konnte nicht erstellt werden.');
    }
    header('Content-Type: application/vnd.sqlite3');
    header('Content-Disposition: attachment; filename="sv-frohnstetten-backup-' . $stamp . '.sqlite"');
    header('Content-Length: ' . filesize($download));
    header('Cache-Control: private, no-store');
    readfile($download);
    @unlink($download);
    exit;
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Backup – SV Frohnstetten</title>
  <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
<header><div class="wrap nav"><div><b>SV Frohnstetten</b><span class="admin-subtitle">Redaktion</span></div><nav><a href="/admin/">Dashboard</a><a href="/admin/verein/">Unser Verein</a><a href="/logout.php">Abmelden</a></nav></div></header>
<main class="wrap">
  <div class="top"><div><p class="eyebrow">SYSTEM</p><h1>Backup</h1><p class="lead">Sichere die aktuelle Datenbank vor größeren Änderungen oder vor dem Umzug auf IONOS.</p></div></div>
  <section class="create">
    <h2>Datenbank sichern</h2>
    <p>Das Backup enthält die komplette SQLite-Datenbank mit Vereinsstruktur, Benutzern, Berichten, Terminen und Einstellungen. Die hochgeladenen Mediendateien sind nicht Bestandteil dieser Datei.</p>
    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button type="submit">Datenbank-Backup herunterladen</button></form>
  </section>
  <p class="hint">Tipp: Vor einem größeren Umbau zuerst ein Backup herunterladen und an einem sicheren Ort aufbewahren.</p>
</main>
</body>
</html>
