<?php
/**
 * ContaUBI — Valida usuario/contraseña enviados desde login.php
 * y crea la sesión si las credenciales son correctas.
 */
$AUTH_PUBLIC = true;
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$usuario  = trim($_POST['usuario']  ?? '');
$password = (string)($_POST['password'] ?? '');

if ($usuario === '' || $password === '') {
    $_SESSION['login_error']   = 'Usuario y contraseña son obligatorios.';
    $_SESSION['login_usuario'] = $usuario;
    header('Location: login.php');
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre, usuario, password, rol, estado
                        FROM usuarios
                        WHERE usuario = ?
                        LIMIT 1");
$stmt->bind_param('s', $usuario);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_error']   = 'Usuario o contraseña incorrectos.';
    $_SESSION['login_usuario'] = $usuario;
    header('Location: login.php');
    exit;
}

if ($user['estado'] !== 'activo') {
    $_SESSION['login_error']   = 'El usuario está inactivo. Contactá al administrador.';
    $_SESSION['login_usuario'] = $usuario;
    header('Location: login.php');
    exit;
}

unset($user['password']);
$_SESSION['usuario'] = [
    'id'      => (int)$user['id'],
    'nombre'  => $user['nombre'],
    'usuario' => $user['usuario'],
    'rol'     => $user['rol'],
    'estado'  => $user['estado'],
];

flash_set('Bienvenido/a, ' . $user['nombre'] . '.', 'success');
header('Location: index.php');
exit;
