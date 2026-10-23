<?php
require __DIR__ . '/../src/bootstrap-local.php';
$db = db();

$db->exec("CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE,
 password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'admin', active INTEGER NOT NULL DEFAULT 1
)");
$db->exec("CREATE TABLE IF NOT EXISTS departments (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
 description TEXT, icon TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1
)");
$db->exec("CREATE TABLE IF NOT EXISTS groups (
 id INTEGER PRIMARY KEY AUTOINCREMENT, department_id INTEGER NOT NULL, parent_id INTEGER,
 name TEXT NOT NULL, slug TEXT NOT NULL, description TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1
)");
$deptCols = [];
foreach ($db->query("PRAGMA table_info(departments)") as $col)
    $deptCols[$col['name']] = true;
if (!isset($deptCols['is_spielgemeinschaft']))
    $db->exec("ALTER TABLE departments ADD COLUMN is_spielgemeinschaft INTEGER NOT NULL DEFAULT 0");
if (!isset($deptCols['sg_text']))
    $db->exec("ALTER TABLE departments ADD COLUMN sg_text TEXT");

$db->exec("CREATE TABLE IF NOT EXISTS department_partner_links (
 id INTEGER PRIMARY KEY AUTOINCREMENT, department_id INTEGER NOT NULL, name TEXT NOT NULL, url TEXT, instagram_url TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
// Mannschaftskader und Trainer/Betreuer
$partnerCols = [];
foreach ($db->query("PRAGMA table_info(department_partner_links)") as $col)
    $partnerCols[$col['name']] = true;
if (!isset($partnerCols['instagram_url']))
    $db->exec("ALTER TABLE department_partner_links ADD COLUMN instagram_url TEXT");

$db->exec("CREATE TABLE IF NOT EXISTS squad_players (
 id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, first_name TEXT NOT NULL, last_name TEXT NOT NULL,
 jersey_number TEXT, position TEXT, birth_year TEXT, bio TEXT, photo_media_id INTEGER, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS squad_staff (
 id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, name TEXT NOT NULL, role TEXT NOT NULL, photo_media_id INTEGER, bio TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

if ((int) $db->query("SELECT COUNT(*) FROM departments")->fetchColumn() === 0) {
    $seedFile = __DIR__ . '/seed/structure.json';
    $seed = is_file($seedFile) ? json_decode(file_get_contents($seedFile), true) : null;
    if (!is_array($seed))
        throw new RuntimeException('Seed-Datei storage/seed/structure.json konnte nicht geladen werden.');
    $st = $db->prepare("INSERT INTO departments(name,slug,description,icon,sort_order) VALUES(?,?,?,?,?)");
    $ids = [];
    foreach (($seed['departments'] ?? []) as $d) {
        $st->execute([$d['name'], $d['slug'], $d['description'] ?? '', $d['icon'] ?? '', (int) ($d['sort_order'] ?? 0)]);
        $ids[$d['slug']] = (int) $db->lastInsertId();
    }
    $st = $db->prepare("INSERT INTO groups(department_id,parent_id,name,slug,sort_order) VALUES(?,?,?,?,?)");
    $top = [];
    foreach (($seed['groups'] ?? []) as $g) {
        if (($g['parent'] ?? null) === null) {
            $st->execute([$ids[$g['department']], null, $g['name'], $g['slug'], (int) ($g['sort_order'] ?? 0)]);
            $top[$g['department'] . ':' . $g['slug']] = (int) $db->lastInsertId();
        }
    }
    foreach (($seed['groups'] ?? []) as $g) {
        if (($g['parent'] ?? null) !== null) {
            $parentId = $top[$g['department'] . ':' . $g['parent']] ?? null;
            if ($parentId)
                $st->execute([$ids[$g['department']], $parentId, $g['name'], $g['slug'], (int) ($g['sort_order'] ?? 0)]);
        }
    }
}
// Spielgemeinschaft und Partner werden aus der externen Seed-Datei übernommen.
// Bestehende Daten werden dabei nur ergänzt bzw. leere Werte erhalten; redaktionelle Änderungen bleiben bestehen.
$footballDept = $db->query("SELECT id FROM departments WHERE slug='fussball' LIMIT 1")->fetchColumn();
if ($footballDept) {
    $footballSeed = null;
    foreach (($seed['departments'] ?? []) as $departmentSeed) {
        if (($departmentSeed['slug'] ?? '') === 'fussball') {
            $footballSeed = $departmentSeed;
            break;
        }
    }
    if (is_array($footballSeed)) {
        $db->prepare("UPDATE departments SET is_spielgemeinschaft=?, sg_text=COALESCE(NULLIF(sg_text,''),?) WHERE id=?")
            ->execute([(int) ($footballSeed['is_spielgemeinschaft'] ?? 0), (string) ($footballSeed['sg_text'] ?? ''), (int) $footballDept]);
    }
    foreach (($seed['sgm_partners'] ?? []) as $partner) {
        $exists = $db->prepare("SELECT COUNT(*) FROM department_partner_links WHERE department_id=? AND (name=? OR url=?)");
        $exists->execute([(int) $footballDept, (string) ($partner['name'] ?? ''), (string) ($partner['url'] ?? '')]);
        if (!(int) $exists->fetchColumn()) {
            $db->prepare("INSERT INTO department_partner_links(department_id,name,url,sort_order,active) VALUES(?,?,?,?,1)")
                ->execute([(int) $footballDept, (string) ($partner['name'] ?? ''), (string) ($partner['url'] ?? ''), (int) ($partner['sort_order'] ?? 0)]);
        }
    }
}

$db->exec("CREATE TABLE IF NOT EXISTS group_training (
 id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, title TEXT NOT NULL, weekday TEXT, time TEXT, age_group TEXT, location TEXT, leader TEXT, note TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS group_contacts (
 id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, role TEXT, name TEXT NOT NULL, phone TEXT, email TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS media (
 id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT NOT NULL, storage_path TEXT NOT NULL,
 mime_type TEXT NOT NULL, size_bytes INTEGER NOT NULL, alt_text TEXT, uploaded_by INTEGER NOT NULL,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$mediaCols = [];
foreach ($db->query("PRAGMA table_info(media)") as $col)
    $mediaCols[$col['name']] = true;
if (!isset($mediaCols['group_id']))
    $db->exec("ALTER TABLE media ADD COLUMN group_id INTEGER");

$db->exec("CREATE TABLE IF NOT EXISTS articles (
 id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
 excerpt TEXT, content TEXT NOT NULL, author_id INTEGER NOT NULL, department_id INTEGER,
 group_id INTEGER, cover_image_id INTEGER, published_at TEXT, active INTEGER DEFAULT 1,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS article_media (
 article_id INTEGER NOT NULL, media_id INTEGER NOT NULL, sort_order INTEGER DEFAULT 0,
 PRIMARY KEY(article_id, media_id)
)");
$db->exec("CREATE TABLE IF NOT EXISTS events (
 id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT,
 start_date TEXT NOT NULL, end_date TEXT, location TEXT, department_id INTEGER, group_id INTEGER,
 created_by INTEGER NOT NULL, active INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
// Erweiterungen für Termine/Spiele (auch bei bestehenden lokalen Datenbanken nachrüstbar).
$eventCols = [];
foreach ($db->query("PRAGMA table_info(events)") as $col)
    $eventCols[$col['name']] = true;
if (!isset($eventCols['event_type']))
    $db->exec("ALTER TABLE events ADD COLUMN event_type TEXT NOT NULL DEFAULT 'termin'");
if (!isset($eventCols['home_team']))
    $db->exec("ALTER TABLE events ADD COLUMN home_team TEXT");
if (!isset($eventCols['away_team']))
    $db->exec("ALTER TABLE events ADD COLUMN away_team TEXT");
if (!isset($eventCols['result']))
    $db->exec("ALTER TABLE events ADD COLUMN result TEXT");

$db->exec("CREATE TABLE IF NOT EXISTS user_departments (
 user_id INTEGER NOT NULL, department_id INTEGER NOT NULL,
 PRIMARY KEY(user_id, department_id)
)");
$footballTableExists = (int) $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='football_widgets'")->fetchColumn();
if (!$footballTableExists) {
    $db->exec("CREATE TABLE football_widgets (
        id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL,
        widget_type TEXT NOT NULL DEFAULT 'next-match', widget_code TEXT, football_url TEXT,
        active INTEGER NOT NULL DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(group_id, widget_type)
    )");
} else {
    $hasOldUnique = false;
    foreach ($db->query("PRAGMA index_list(football_widgets)") as $idx) {
        if ((int) $idx['unique'] === 1) {
            $info = $db->query("PRAGMA index_info('" . str_replace("'", "''", $idx['name']) . "')")->fetchAll();
            if (count($info) === 1 && ($info[0]['name'] ?? '') === 'group_id') {
                $hasOldUnique = true;
                break;
            }
        }
    }
    if ($hasOldUnique) {
        $db->exec("ALTER TABLE football_widgets RENAME TO football_widgets_old");
        $db->exec("CREATE TABLE football_widgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL,
            widget_type TEXT NOT NULL DEFAULT 'next-match', widget_code TEXT, football_url TEXT,
            active INTEGER NOT NULL DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(group_id, widget_type)
        )");
        $oldRows = $db->query("SELECT * FROM football_widgets_old")->fetchAll();
        $ins = $db->prepare("INSERT OR IGNORE INTO football_widgets(id,group_id,widget_type,widget_code,football_url,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)");
        foreach ($oldRows as $r) {
            $type = ($r['widget_type'] ?? '');
            if ($type === 'tabelle')
                $type = 'table';
            elseif ($type === 'spiele')
                $type = 'last-matches';
            elseif ($type === 'mannschaft')
                $type = 'next-match';
            elseif ($type === 'verein')
                $type = 'next-match';
            elseif ($type === 'wettbewerb')
                $type = 'schedule';
            $ins->execute([(int) $r['id'], (int) $r['group_id'], $type ?: 'next-match', $r['widget_code'] ?? null, $r['football_url'] ?? null, (int) ($r['active'] ?? 1), $r['created_at'] ?? date('Y-m-d H:i:s'), $r['updated_at'] ?? date('Y-m-d H:i:s')]);
        }
        $db->exec("DROP TABLE football_widgets_old");
    }
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS football_widgets_group_type ON football_widgets(group_id, widget_type)");
}

// Sponsoren
$db->exec("CREATE TABLE IF NOT EXISTS sponsors (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, website TEXT, logo_media_id INTEGER, description TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1, featured INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

$sponsorCols = [];
foreach ($db->query("PRAGMA table_info(sponsors)") as $col)
    $sponsorCols[$col['name']] = true;
if (!isset($sponsorCols['featured']))
    $db->exec("ALTER TABLE sponsors ADD COLUMN featured INTEGER DEFAULT 0");
// Die beiden geforderten Hauptsponsoren werden standardmäßig als prominent markiert.
$db->exec("UPDATE sponsors SET featured=1 WHERE active=1 AND (lower(name) LIKE '%mabitz%' OR lower(name) LIKE '%volksbank zollernalb%')");
if ((int) $db->query("SELECT COUNT(*) FROM sponsors WHERE lower(name) LIKE '%volksbank zollernalb%'")->fetchColumn() === 0) {
    $db->prepare("INSERT INTO sponsors(name,website,description,sort_order,active,featured) VALUES(?,?,?,?,?,1)")->execute(['Volksbank Zollernalb eG', 'https://www.volksbank-zollernalb.de/', 'Hauptsponsor', 10, 1]);
}
if ((int) $db->query("SELECT COUNT(*) FROM sponsors WHERE lower(name) LIKE '%mabitz%'")->fetchColumn() === 0) {
    $db->prepare("INSERT INTO sponsors(name,website,description,sort_order,active,featured) VALUES(?,?,?,?,?,1)")->execute(['Ski + Sport Mabitz', 'https://sport-mabitz.de/', 'Hauptsponsor', 20, 1]);
}

// Strukturierte Vereinsdaten
$db->exec("CREATE TABLE IF NOT EXISTS club_people (
 id INTEGER PRIMARY KEY AUTOINCREMENT, area TEXT NOT NULL, role TEXT NOT NULL, name TEXT NOT NULL, phone TEXT, email TEXT, address TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS club_training (
 id INTEGER PRIMARY KEY AUTOINCREMENT, category TEXT NOT NULL, title TEXT NOT NULL, weekday TEXT, time TEXT, age_group TEXT, location TEXT, leader TEXT, note TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS club_links (
 id INTEGER PRIMARY KEY AUTOINCREMENT, category TEXT NOT NULL, title TEXT NOT NULL, url TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

$clubSeedFile = __DIR__ . '/seed/club.json';
$clubSeed = is_file($clubSeedFile) ? json_decode(file_get_contents($clubSeedFile), true) : null;
if (!is_array($clubSeed))
    throw new RuntimeException('Seed-Datei storage/seed/club.json konnte nicht geladen werden.');
if ((int) $db->query("SELECT COUNT(*) FROM club_people WHERE area='vorstand'")->fetchColumn() === 0) {
    $st = $db->prepare("INSERT INTO club_people(area,role,name,phone,email,address,sort_order) VALUES(?,?,?,?,?,?,?)");
    foreach (($clubSeed['people'] ?? []) as $x)
        $st->execute($x);
}
if ((int) $db->query("SELECT COUNT(*) FROM club_people WHERE area='kinderschutz'")->fetchColumn() === 0) {
    $st = $db->prepare("INSERT INTO club_people(area,role,name,phone,email,address,sort_order) VALUES(?,?,?,?,?,?,?)");
    foreach (($clubSeed['child_protection'] ?? []) as $x)
        $st->execute($x);
}
if ((int) $db->query("SELECT COUNT(*) FROM club_training")->fetchColumn() === 0) {
    $st = $db->prepare("INSERT INTO club_training(category,title,weekday,time,age_group,location,leader,note,sort_order) VALUES(?,?,?,?,?,?,?,?,?)");
    foreach (($clubSeed['training'] ?? []) as $x)
        $st->execute($x);
}
if ((int) $db->query("SELECT COUNT(*) FROM club_links")->fetchColumn() === 0) {
    $st = $db->prepare("INSERT INTO club_links(category,title,url,sort_order) VALUES(?,?,?,?)");
    foreach (($clubSeed['links'] ?? []) as $x)
        $st->execute($x);
}
// Inhalte "Unser Verein"
$db->exec("CREATE TABLE IF NOT EXISTS club_pages (
 id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, intro TEXT, content TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS club_documents (
 id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, description TEXT, storage_path TEXT, original_filename TEXT, mime_type TEXT, content TEXT, sort_order INTEGER DEFAULT 0, active INTEGER DEFAULT 1, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$docCols = [];
foreach ($db->query("PRAGMA table_info(club_documents)") as $col)
    $docCols[$col['name']] = true;
if (!isset($docCols['content']))
    $db->exec("ALTER TABLE club_documents ADD COLUMN content TEXT");
// Vereinsseiten und Vereinsunterlagen kommen ausschließlich aus der Seed-Datei.
// Nach der Erstbefüllung liegen die Inhalte in der Datenbank und werden nicht mehr aus dem Code überschrieben.
if (!is_array($clubSeed))
    throw new RuntimeException('Seed-Datei storage/seed/club.json konnte nicht geladen werden.');
if ((int) $db->query("SELECT COUNT(*) FROM club_pages")->fetchColumn() === 0) {
    $st = $db->prepare("INSERT INTO club_pages(title,slug,intro,content,sort_order,active) VALUES(?,?,?,?,?,?)");
    foreach (($clubSeed['pages'] ?? []) as $x) {
        $st->execute([
            (string) ($x['title'] ?? ''),
            (string) ($x['slug'] ?? ''),
            (string) ($x['intro'] ?? ''),
            (string) ($x['content'] ?? ''),
            (int) ($x['sort_order'] ?? 0),
            isset($x['active']) ? (int) $x['active'] : 1,
        ]);
    }
}
if ((int) $db->query("SELECT COUNT(*) FROM club_documents")->fetchColumn() === 0) {
    $st = $db->prepare("INSERT INTO club_documents(title,slug,description,content,sort_order,active) VALUES(?,?,?,?,?,?)");
    foreach (($clubSeed['documents'] ?? []) as $x) {
        $st->execute([
            (string) ($x['title'] ?? ''),
            (string) ($x['slug'] ?? ''),
            (string) ($x['description'] ?? ''),
            (string) ($x['content'] ?? ''),
            (int) ($x['sort_order'] ?? 0),
            isset($x['active']) ? (int) $x['active'] : 1,
        ]);
    }
}

if ((int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0) {
    $st = $db->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)");
    $st->execute(['Lokaler Admin', 'admin@svfrohnstetten.local', password_hash('SVF-Test-2026!', PASSWORD_DEFAULT), 'admin']);
}

// Administratoren erhalten automatisch Zugriff auf alle aktiven Sportbereiche.
$adminId = (int) ($db->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
if ($adminId) {
    $st = $db->prepare("INSERT OR IGNORE INTO user_departments(user_id,department_id) VALUES(?,?)");
    foreach ($db->query("SELECT id FROM departments WHERE active=1") as $d)
        $st->execute([$adminId, (int) $d['id']]);
}
