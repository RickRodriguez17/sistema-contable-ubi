<?php
/**
 * ContaUBI — Cerrar sesión y redirigir a login.
 */
$AUTH_PUBLIC = true;
require_once __DIR__ . '/auth.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600,
              $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
}

session_destroy();

header('Location: login.php');
exit;
