<?php
/**
 * ContaUBI — encabezado HTML compartido + sidebar.
 * Variables esperadas: $pageTitle, $activePage, $EMPRESA (set por conexion.php).
 */
$pageTitle  = $pageTitle  ?? 'ContaUBI';
$activePage = $activePage ?? '';
$EMPRESA    = $EMPRESA    ?? ['nombre'=>'ContaUBI','ejercicio'=>(int)date('Y'),'logo_texto'=>'UBI','moneda'=>'Bs.'];

$nav = [
    ['Principal',   '',                                 ''],
    ['Inicio',      'index.php',                'home',           'bi-grid-1x2'],
    ['Plan de Cuentas','cuentas.php',           'cuentas',        'bi-list-columns-reverse'],
    ['Empresa',     'empresa.php',              'empresa',        'bi-building'],

    ['Diario',      '',                                 ''],
    ['Comprobantes','comprobantes.php',         'comprobantes',   'bi-receipt'],
    ['Nuevo Asiento','comprobante_crear.php',   'comprobante_crear','bi-plus-circle'],
    ['Libro Diario','libro_diario.php',         'libro_diario',   'bi-journal-text'],

    ['Reportes',    '',                                 ''],
    ['Libro Mayor', 'libro_mayor.php',          'libro_mayor',    'bi-book'],
    ['Bal. Comprobación','balance_comprobacion.php','balance_comp', 'bi-table'],
    ['Estado de Resultados','estado_resultados.php','estado_resultados','bi-graph-up'],
    ['Balance General','balance_general.php',   'balance_general','bi-layout-text-sidebar-reverse'],
];
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
    foreach ($nav as $item) {
        if ($item[1] === '') {
            echo "<div class='section-label'>" . h($item[0]) . "</div>";
        } else {
            $cls = ($activePage === $item[2]) ? 'nav active' : 'nav';
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
