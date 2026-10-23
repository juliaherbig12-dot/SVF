<?php
declare(strict_types=1);
$config = require __DIR__ . '/../config/local.php';
date_default_timezone_set($config['app']['timezone']);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['security']['session_name']);
    session_start();
}

function db(): PDO
{
    static $pdo;
    global $config;
    if ($pdo instanceof PDO)
        return $pdo;
    $pdo = new PDO('sqlite:' . $config['db']['path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text_lower(string $s): string
{
    if (function_exists('mb_strtolower'))
        return mb_strtolower($s, 'UTF-8');
    return strtolower($s);
}

function detect_mime_type(string $path, string $fallback = 'application/octet-stream'): string
{
    if (function_exists('finfo_open')) {
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $mime = @finfo_file($f, $path);
            @finfo_close($f);
            if (is_string($mime) && $mime !== '')
                return $mime;
        }
    }
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($path);
        if (is_string($mime) && $mime !== '')
            return $mime;
    }
    if (function_exists('getimagesize')) {
        $info = @getimagesize($path);
        if (is_array($info) && !empty($info['mime']))
            return (string) $info['mime'];
    }
    return $fallback;
}

function text_initial(string $s): string
{
    $s = trim($s);
    if ($s === '')
        return '';
    if (function_exists('mb_substr') && function_exists('mb_strtoupper'))
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8');
    return strtoupper(substr($s, 0, 1));
}

function slugify(string $s): string
{
    $s = trim($s);
    $s = strtr($s, ['Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $s = text_lower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

function csrf(): string
{
    if (empty($_SESSION['csrf']))
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Ungültige Anfrage.');
    }
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $u = user();
    if (!$u || !in_array(($u['role'] ?? ''), ['admin', 'editor'], true)) {
        header('Location: /login/');
        exit;
    }
    return $u;
}

function require_admin(): array
{
    return require_login();
}

function require_admin_only(): array
{
    $u = user();
    if (!$u || ($u['role'] ?? '') !== 'admin') {
        header('Location: /admin/');
        exit;
    }
    return $u;
}

function can_manage_department(int $departmentId): bool
{
    $u = user();
    if (!$u)
        return false;
    if (($u['role'] ?? '') === 'admin')
        return true;
    if (($u['role'] ?? '') !== 'editor')
        return false;
    $s = db()->prepare("SELECT 1 FROM user_departments WHERE user_id=? AND department_id=?");
    $s->execute([(int) $u['id'], $departmentId]);
    return (bool) $s->fetchColumn();
}

function can_manage_group(?int $groupId): bool
{
    if (!$groupId)
        return user() && user()['role'] === 'admin';
    $s = db()->prepare("SELECT department_id FROM groups WHERE id=?");
    $s->execute([$groupId]);
    $d = $s->fetchColumn();
    return $d !== false && can_manage_department((int) $d);
}

function group_path(PDO $db, int $groupId): array
{
    $path = [];
    $current = $groupId;
    $guard = 0;
    while ($current > 0 && $guard++ < 30) {
        $st = $db->prepare("SELECT id,name,parent_id,department_id FROM groups WHERE id=?");
        $st->execute([$current]);
        $g = $st->fetch();
        if (!$g)
            break;
        array_unshift($path, $g);
        $current = (int) ($g['parent_id'] ?? 0);
    }
    return $path;
}

function group_label(PDO $db, int $groupId, bool $includeDepartment = true): string
{
    $path = group_path($db, $groupId);
    if (!$path)
        return '';
    $parts = array_map(fn($g) => $g['name'], $path);
    if ($includeDepartment) {
        $st = $db->prepare("SELECT name FROM departments WHERE id=?");
        $st->execute([(int) $path[0]['department_id']]);
        $d = $st->fetchColumn();
        if ($d)
            array_unshift($parts, $d);
    }
    return implode(' › ', $parts);
}

function group_option_label(PDO $db, array $group): string
{
    return group_label($db, (int) $group['id'], true);
}

function require_department_access(int $departmentId): array
{
    $u = require_admin();
    if (!can_manage_department($departmentId)) {
        http_response_code(403);
        exit('Keine Berechtigung für diesen Bereich.');
    }
    return $u;
}
