<?php
/**
 * ContaUBI — encabezado HTML compartido + sidebar.
 *
 * Variables esperadas: $pageTitle, $activePage, $EMPRESA (set por conexion.php).
 *
 * El menú lateral y los botones del topbar se filtran según el rol activo.
 * Cada item del menú declara qué permiso necesita (auth_can()) para verse.
 */
$pageTitle  = $pageTitle  ?? 'ContaUBI';
$activePage = $activePage ?? '';
$EMPRESA    = $EMPRESA    ?? ['nombre'=>'ContaUBI','ejercicio'=>(int)date('Y'),'logo_texto'=>'UBI','moneda'=>'Bs.'];

/* Items del menú: [titulo, archivo, slug-activo, icono, permiso].
 * Si archivo === '' => label de sección (sin link).
 * El permiso se valida con auth_can(); si no se cumple, se oculta el item.
 */
$nav = [
    ['Principal',            '',                          '',                  '',                                'dashboard.ver'],
    ['Inicio',               'index.php',                 'home',              'bi-grid-1x2',                     'dashboard.ver'],
    ['Plan de Cuentas',      'cuentas.php',               'cuentas',           'bi-list-columns-reverse',         'cuentas.ver'],
    ['Empresa',              'empresa.php',               'empresa',           'bi-building',                     'empresa.editar'],
    ['Usuarios',             'usuarios.php',              'usuarios',          'bi-people',                       'usuarios.gestionar'],

    ['Diario',               '',                          '',                  '',                                'comprobantes.ver'],
    ['Comprobantes',         'comprobantes.php',          'comprobantes',      'bi-receipt',                      'comprobantes.ver'],
    ['Nuevo Asiento',        'comprobante_crear.php',     'comprobante_crear', 'bi-plus-circle',                  'comprobantes.crear'],
    ['Libro Diario',         'libro_diario.php',          'libro_diario',      'bi-journal-text',                 'reportes.ver'],

    ['Reportes',             '',                          '',                  '',                                'reportes.ver'],
    ['Libro Mayor',          'libro_mayor.php',           'libro_mayor',       'bi-book',                         'reportes.ver'],
    ['Bal. Comprobación',    'balance_comprobacion.php',  'balance_comp',      'bi-table',                        'reportes.ver'],
    ['Estado de Resultados', 'estado_resultados.php',     'estado_resultados', 'bi-graph-up',                     'reportes.ver'],
    ['Balance General',      'balance_general.php',       'balance_general',   'bi-layout-text-sidebar-reverse',  'reportes.ver'],
];

/* Filtramos items por permisos del rol actual.
 * Una sección sólo se muestra si tiene al menos un item visible debajo. */
$navVisibles = [];
foreach ($nav as $item) {
    [$titulo, $archivo, $slug, $icono, $perm] = array_pad($item, 5, '');
    if (!function_exists('auth_can') || !auth_can($perm)) continue;
    $navVisibles[] = $item;
}
/* Quitar labels de sección que quedaron sin items debajo. */
$navFinal = [];
$n = count($navVisibles);
for ($i = 0; $i < $n; $i++) {
    [$_t, $arch, $_s, $_i, $_p] = array_pad($navVisibles[$i], 5, '');
    if ($arch === '') {
        $tieneHijos = false;
        for ($j = $i + 1; $j < $n; $j++) {
            if ($navVisibles[$j][1] === '') break;
            $tieneHijos = true; break;
        }
        if (!$tieneHijos) continue;
    }
    $navFinal[] = $navVisibles[$i];
}

$me = function_exists('auth_user') ? auth_user() : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> · ContaUBI</title>
<link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<div class="app-shell">

  <aside class="sidebar">
    <div class="brand">
      <img src="assets/img/logo.svg" alt="UBI">
      <div>
        <div class="title">ContaUBI</div>
        <div class="sub">Univ. Boliviana de Informática</div>
      </div>
    </div>

    <?php
    foreach ($navFinal as $item) {
        if ($item[1] === '') {
            echo "<div class='section-label'>" . h($item[0]) . "</div>";
        } else {
            $cls  = ($activePage === $item[2]) ? 'nav active' : 'nav';
            $icon = $item[3] ?? 'bi-circle';
            echo "<a href='" . h($item[1]) . "' class='" . $cls . "'>"
               . "<i class='bi " . h($icon) . "'></i> " . h($item[0]) . "</a>";
        }
    }
    ?>

    <div class="sidebar-footer">
      &copy; <?= date('Y') ?> ContaUBI<br>
      v1.0 · Académico
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <h1><i class="bi <?= h($pageIcon ?? 'bi-app') ?>"></i> <?= h($pageTitle) ?></h1>
      <div class="topbar-right">
        <span class="ejercicio-tag" title="Ejercicio contable activo">
          <i class="bi bi-calendar3"></i>
          Ejercicio <?= h($EMPRESA['ejercicio']) ?>
        </span>
        <?php if ($me): ?>
          <span class="user-tag" title="<?= h($me['nombre']) ?> (<?= h($me['rol']) ?>)">
            <i class="bi bi-person-circle"></i>
            <span class="user-name"><?= h($me['nombre']) ?></span>
            <span class="rol-chip rol-<?= h($me['rol']) ?>"><?= strtoupper(h($me['rol'])) ?></span>
          </span>
          <a href="logout.php" class="btn btn-ghost btn-sm" title="Cerrar sesión">
            <i class="bi bi-box-arrow-right"></i> Salir
          </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="body-pane">
      <?php
      $flash = flash_get();
      if ($flash):
        $klass = ['success'=>'alert-success','danger'=>'alert-danger','warn'=>'alert-warn','info'=>'alert-info'][$flash['tipo']] ?? 'alert-info';
        $icon  = ['success'=>'check-circle','danger'=>'exclamation-circle','warn'=>'exclamation-triangle','info'=>'info-circle'][$flash['tipo']] ?? 'info-circle';
      ?>
        <div class="alert <?= $klass ?> anim">
          <i class="bi bi-<?= $icon ?>"></i>
          <span><?= h($flash['msg']) ?></span>
        </div>
      <?php endif; ?>
