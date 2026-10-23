<?php
require __DIR__ . '/../../src/bootstrap-local.php';
if (user()) {
    header('Location:/admin/');
    exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $s = db()->prepare("SELECT id,name,email,password_hash,role,active FROM users WHERE email=? LIMIT 1");
    $s->execute([trim($_POST['email'] ?? '')]);
    $u = $s->fetch();
    if ($u && $u['active'] && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        unset($u['password_hash']);
        $_SESSION['user'] = $u;
        header('Location:/admin/');
        exit;
    }
    $error = 'E-Mail oder Passwort ist nicht korrekt.';
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login – SV Frohnstetten</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>

<body class="auth">
    <form method="post" class="authbox">
        <h1>SV Frohnstetten</h1>
        <h2>Redaktionsbereich</h2>
        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p><?php endif; ?>
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <label>E-Mail<input type="email" name="email" required></label>
        <label>Passwort<input type="password" name="password" required></label>
        <button type="submit">Einloggen</button>
    </form>
</body>

</html>