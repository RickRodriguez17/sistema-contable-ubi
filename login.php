<?php
/**
 * ContaUBI — Pantalla de login.
 * Página pública: no requiere sesión iniciada.
 */
$AUTH_PUBLIC = true;
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

if (auth_logged_in()) {
    header('Location: index.php');
    exit;
}

$error   = $_SESSION['login_error']   ?? '';
$usuario = $_SESSION['login_usuario'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_usuario']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión · ContaUBI</title>
<link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<div class="login-shell">
  <div class="login-card anim">

    <div class="login-brand">
      <img src="assets/img/logo.svg" alt="UBI">
      <div>
        <div class="title">ContaUBI</div>
        <div class="sub">Univ. Boliviana de Informática</div>
      </div>
    </div>

    <h2><i class="bi bi-shield-lock"></i> Iniciar sesión</h2>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle"></i>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="validar_login.php" autocomplete="off">
      <div class="form-group">
        <label class="form-label" for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" class="form-control"
               placeholder="ej. admin" required autofocus
               value="<?= h($usuario) ?>" maxlength="50">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Contraseña</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="••••••" required maxlength="100">
      </div>

      <button type="submit" class="btn btn-primary btn-block">
        <i class="bi bi-box-arrow-in-right"></i> Entrar
      </button>
    </form>

    <div class="login-foot">
      <i class="bi bi-info-circle"></i>
      Sistema contable · ejercicio académico
    </div>

  </div>
</div>

</body>
</html>
