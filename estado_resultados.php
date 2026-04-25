<?php
/**
 * ContaUBI — Estado de Resultados (Ingresos – Egresos)
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Estado de Resultados';
$pageIcon   = 'bi-graph-up';
$activePage = 'estado_resultados';

$desde = $_GET['desde'] ?? $EMPRESA['fecha_inicio_ejercicio'];
$hasta = $_GET['hasta'] ?? $EMPRESA['fecha_cierre_ejercicio'];

$sql = "SELECT cu.id, cu.codigo, cu.nombre, cu.clase, cu.naturaleza,
               COALESCE(SUM(m.debe),0)  AS debe,
               COALESCE(SUM(m.haber),0) AS haber
        FROM cuentas cu
        LEFT JOIN movimientos m  ON m.cuenta_id = cu.id
        LEFT JOIN comprobantes c ON c.id = m.comprobante_id
                                AND c.estado='APROBADO'
                                AND c.fecha BETWEEN ? AND ?
        WHERE cu.es_imputable = 1 AND cu.clase IN (4,5)
        GROUP BY cu.id, cu.codigo, cu.nombre, cu.clase, cu.naturaleza
        HAVING debe > 0 OR haber > 0
        ORDER BY cu.codigo";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss',$desde,$hasta);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$ingresos = []; $egresos = [];
$totIngresos = 0.0; $totEgresos = 0.0;

foreach ($rows as $r) {
    $diff = $r['debe'] - $r['haber'];
    $saldo = ($r['naturaleza']==='DEUDORA') ? $diff : -$diff;
    if ((int)$r['clase'] === 4) {
        $ingresos[] = $r + ['saldo'=>$saldo];
        $totIngresos += $saldo;
    } else {
        $egresos[]  = $r + ['saldo'=>$saldo];
        $totEgresos += $saldo;
    }
}

$utilidad = $totIngresos - $totEgresos;

include __DIR__ . '/layout_top.php';
?>

<form method="GET" class="filtros no-print">
  <div class="form-group">
    <label class="form-label">Desde</label>
    <input type="date" name="desde" class="form-control" value="<?= h($desde) ?>">
  </div>
  <div class="form-group">
    <label class="form-label">Hasta</label>
    <input type="date" name="hasta" class="form-control" value="<?= h($hasta) ?>">
  </div>
  <div class="filtros-actions">
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Generar</button>
    <button type="button" class="btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
  </div>
</form>

<div class="print-header">
  <h2><?= h($EMPRESA['nombre']) ?></h2>
  <p>NIT: <?= h($EMPRESA['nit']) ?> · <?= h($EMPRESA['ciudad']) ?></p>
  <p><strong>ESTADO DE RESULTADOS</strong></p>
  <p>Del <?= fecha_es($desde) ?> al <?= fecha_es($hasta) ?> · <?= h($EMPRESA['moneda']) ?></p>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-arrow-up-right-circle"></i> Ingresos</div>
  <div class="card-body" style="padding:0">
    <table class="table">
      <thead><tr><th>Código</th><th>Cuenta</th><th class="text-right">Saldo</th></tr></thead>
      <tbody>
      <?php if ($ingresos): foreach ($ingresos as $r): ?>
        <tr>
          <td><span class="chip"><?= h($r['codigo']) ?></span></td>
          <td><?= h($r['nombre']) ?></td>
          <td class="text-right num fw-600"><?= money($r['saldo']) ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="3" class="text-muted text-center">Sin ingresos en el período</td></tr>
      <?php endif; ?>
      </tbody>
      <tfoot><tr><td colspan="2" class="text-right">TOTAL INGRESOS</td><td class="text-right num"><?= money($totIngresos) ?></td></tr></tfoot>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-arrow-down-right-circle"></i> Egresos / Gastos</div>
  <div class="card-body" style="padding:0">
    <table class="table">
      <thead><tr><th>Código</th><th>Cuenta</th><th class="text-right">Saldo</th></tr></thead>
      <tbody>
      <?php if ($egresos): foreach ($egresos as $r): ?>
        <tr>
          <td><span class="chip"><?= h($r['codigo']) ?></span></td>
          <td><?= h($r['nombre']) ?></td>
          <td class="text-right num fw-600"><?= money($r['saldo']) ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="3" class="text-muted text-center">Sin egresos en el período</td></tr>
      <?php endif; ?>
      </tbody>
      <tfoot><tr><td colspan="2" class="text-right">TOTAL EGRESOS</td><td class="text-right num"><?= money($totEgresos) ?></td></tr></tfoot>
    </table>
  </div>
</div>

<div class="card" style="border-color: <?= $utilidad>=0?'rgba(20,184,106,.5)':'rgba(224,82,77,.5)' ?>">
  <div class="card-body" style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <div class="form-label">Resultado del Ejercicio</div>
      <div class="text-muted">Ingresos − Egresos</div>
    </div>
    <div style="text-align:right">
      <div class="num" style="font-size:1.6rem;font-weight:700;color:<?= $utilidad>=0?'#6ee9a6':'#ff8e8a' ?>">
        <?= money($utilidad) ?> <?= h($EMPRESA['moneda']) ?>
      </div>
      <div class="text-muted"><?= $utilidad>=0 ? 'UTILIDAD del ejercicio' : 'PÉRDIDA del ejercicio' ?></div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
