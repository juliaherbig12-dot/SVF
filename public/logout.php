<?php

require __DIR__ . '/../src/bootstrap-local.php';

$_SESSION = [];
session_destroy();
header('Location:/login/');
exit;
