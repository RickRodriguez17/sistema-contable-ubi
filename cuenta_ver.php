<?php
/**
 * ContaUBI — Ver detalle de cuenta
 *
 * Muestra metadatos completos, ruta jerárquica (padres), hijas directas
 * y cantidad de movimientos asociados. Disponible para cualquier rol
 * con permiso `cuentas.ver`.
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
auth_require('cuentas.ver');

$pageTitle  = 'Detalle de Cuenta';
$pageIcon   = 'bi-eye';
$activePage = 'cuentas';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM cuentas WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { flash_set('Cuenta no encontrada.','danger'); header('Location: cuentas.php'); exit; }

$nivel = (int)$row['nivel'];

/* Ruta de padres (de nivel 1 al actual-1) */
$ruta = [];
$cols = ['clase','grupo','subgrupo','cuenta_principal','cuenta_analitica'];
for ($n = 1; $n < $nivel; $n++) {
    $where = [];
    $params = []; $types='';
    for ($i = 0; $i < $n; $i++) {
        $where[] = $cols[$i] . '=?';
        $types  .= 'i';
        $params[] = (int)$row[$cols[$i]];
    }
    /* Las columnas más altas que $n deben ser 0 */
    for ($i = $n; $i < 5; $i++) {
        $where[] = $cols[$i] . '=0';
    }
    $where[] = 'nivel=?'; $types .= 'i'; $params[] = $n;
    $sql = "SELECT id, codigo, nombre FROM cuentas WHERE " . implode(' AND ', $where) . " LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    if ($p) $ruta[] = $p;
}

/* Hijas directas */
$hijas = [];
if ($nivel < 5) {
    $where = ['nivel=?'];
    $types = 'i';
    $params = [$nivel + 1];
    for ($i = 0; $i < $nivel; $i++) {
        $where[] = $cols[$i] . '=?';
        $types  .= 'i';
        $params[] = (int)$row[$cols[$i]];
    }
    $sql = "SELECT id, codigo, nombre, activa FROM cuentas WHERE " . implode(' AND ', $where) . " ORDER BY codigo";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $hijas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* Conteo de movimientos */
$stmt = $conn->prepare("SELECT COUNT(*) c, COALESCE(SUM(debe),0) sd, COALESCE(SUM(haber),0) sh
                        FROM movimientos WHERE cuenta_id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$mov = $stmt->get_result()->fetch_assoc();

include __DIR__ . '/layout_top.php';
?>

<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
  <a href="cuentas.php" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-left"></i> Volver al Plan de Cuentas</a>
  <div style="display:flex;gap:.5rem">
    <button class="btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    <?php if (auth_can('cuentas.gestionar')): ?>
      <a class="btn btn-primary" href="cuenta_editar.php?id=<?= $id ?>"><i class="bi bi-pencil"></i> Editar</a>
    <?php endif; ?>
  </div>
</div>

<div class="print-header">
  <h2><?= h($EMPRESA['nombre']) ?></h2>
  <p>NIT: <?= h($EMPRESA['nit']) ?> · <?= h($EMPRESA['ciudad']) ?></p>
  <p><strong>FICHA DE CUENTA — <?= h($row['codigo']) ?></strong></p>
</div>

<div class="card anim" style="max-width:900px">
  <div class="card-header">
    <span>
      <i class="bi bi-eye"></i>
      <span class="chip"><?= h($row['codigo']) ?></span>
      <strong style="margin-left:.5rem"><?= h($row['nombre']) ?></strong>
    </span>
    <span>
      <span class="badge <?= clase_badge((int)$row['clase']) ?>"><?= nombre_clase((int)$row['clase']) ?></span>
      <span class="badge badge-nivel"><?= h(nombre_nivel($nivel)) ?></span>
      <?php if ((int)$row['es_puct'] === 1): ?>
        <span class="badge badge-puct">PUCT</span>
      <?php else: ?>
        <span class="badge badge-propia">Propia</span>
      <?php endif; ?>
    </span>
  </div>
  <div class="card-body">

    <?php if ($ruta): ?>
    <div class="ruta-jerarquia">
      <i class="bi bi-diagram-3"></i>
      <span class="text-muted">Jerarquía:</span>
      <?php foreach ($ruta as $i => $p): ?>
        <span class="chip"><?= h($p['codigo']) ?></span>
        <span><?= h($p['nombre']) ?></span>
        <?php if ($i < count($ruta) - 1): ?><i class="bi bi-chevron-right"></i><?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="form-grid form-grid-3">
      <div><div class="form-label">Naturaleza</div><div class="fw-600"><?= h($row['naturaleza']) ?></div></div>
      <div><div class="form-label">Nivel</div><div class="fw-600">N<?= $nivel ?> · <?= h(nombre_nivel($nivel)) ?></div></div>
      <div><div class="form-label">Estado</div>
        <div class="fw-600">
          <?php if ($row['activa']): ?><span class="badge badge-activo">ACTIVA</span><?php else: ?><span class="badge badge-default">INACTIVA</span><?php endif; ?>
        </div>
      </div>
      <div><div class="form-label">Imputable</div><div class="fw-600"><?= ((int)$row['es_imputable']===1?'Sí — acepta movimientos':'No — sólo agrupación') ?></div></div>
      <div><div class="form-label">Origen</div><div class="fw-600"><?= ((int)$row['es_puct']===1?'PUCT oficial (SIN)':'Cuenta propia') ?></div></div>
      <div><div class="form-label">Creada</div><div class="fw-600"><?= h($row['creado_en']) ?></div></div>
    </div>

    <?php if (!empty($row['descripcion'])): ?>
    <div class="form-group" style="margin-top:1rem">
      <div class="form-label">Descripción</div>
      <div><?= nl2br(h($row['descripcion'])) ?></div>
    </div>
    <?php endif; ?>

    <hr style="border:0;border-top:1px solid var(--border);margin:1.25rem 0">

    <div class="form-grid form-grid-3">
      <div><div class="form-label">Movimientos</div><div class="fw-600 num"><?= (int)$mov['c'] ?></div></div>
      <div><div class="form-label">Total Debe</div><div class="fw-600 num"><?= money($mov['sd']) ?></div></div>
      <div><div class="form-label">Total Haber</div><div class="fw-600 num"><?= money($mov['sh']) ?></div></div>
    </div>

    <?php if ($hijas): ?>
      <hr style="border:0;border-top:1px solid var(--border);margin:1.25rem 0">
      <div class="form-label" style="margin-bottom:.5rem">Cuentas hijas directas (<?= count($hijas) ?>)</div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Código</th><th>Nombre</th><th class="text-center">Activa</th><th class="no-print"></th></tr></thead>
          <tbody>
            <?php foreach ($hijas as $hh): ?>
              <tr>
                <td><span class="chip"><?= h($hh['codigo']) ?></span></td>
                <td><?= h($hh['nombre']) ?></td>
                <td class="text-center"><?= $hh['activa']?'<i class="bi bi-circle-fill text-success"></i>':'<i class="bi bi-circle text-muted"></i>' ?></td>
                <td class="no-print"><a href="cuenta_ver.php?id=<?= (int)$hh['id'] ?>" class="btn btn-ghost btn-sm"><i class="bi bi-eye"></i></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
