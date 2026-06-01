<?php
/**
 * ContaUBI — Balance General (Estado de Situación Patrimonial)
 *  Activo = Pasivo + Patrimonio + Resultado del ejercicio
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$pageTitle  = 'Balance General';
$pageIcon   = 'bi-layout-text-sidebar-reverse';
$activePage = 'balance_general';

$alCorte = $_GET['al'] ?? $EMPRESA['fecha_cierre_ejercicio'];

$sql = "SELECT cu.id, cu.codigo, cu.nombre, cu.clase, cu.naturaleza,
               COALESCE(SUM(m.debe),0)  AS debe,
               COALESCE(SUM(m.haber),0) AS haber
        FROM cuentas cu
        LEFT JOIN movimientos m  ON m.cuenta_id = cu.id
        LEFT JOIN comprobantes c ON c.id = m.comprobante_id AND c.estado='APROBADO' AND c.fecha <= ?
        WHERE cu.es_imputable = 1
        GROUP BY cu.id, cu.codigo, cu.nombre, cu.clase, cu.naturaleza
        HAVING debe > 0 OR haber > 0
        ORDER BY cu.codigo";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s',$alCorte);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$activos = []; $pasivos = []; $patrimonio = [];
$totA = 0.0; $totP = 0.0; $totPN = 0.0;
$totIng = 0.0; $totEgr = 0.0;

foreach ($rows as $r) {
    $diff = $r['debe'] - $r['haber'];
    $saldo = ($r['naturaleza']==='DEUDORA') ? $diff : -$diff;
    switch ((int)$r['clase']) {
        case 1: $activos[]=$r+['saldo'=>$saldo];    $totA  += $saldo; break;
        case 2: $pasivos[]=$r+['saldo'=>$saldo];    $totP  += $saldo; break;
        case 3: $patrimonio[]=$r+['saldo'=>$saldo]; $totPN += $saldo; break;
        case 4: $totIng += $saldo; break;
        case 5: $totEgr += $saldo; break;
    }
}
$resultado = $totIng - $totEgr;
$totPasPat = $totP + $totPN + $resultado;
$cuadra = abs($totA - $totPasPat) < 0.005;

include __DIR__ . '/layout_top.php';
?>

<form method="GET" class="filtros no-print">
  <div class="form-group">
    <label class="form-label">Al corte</label>
    <input type="date" name="al" class="form-control" value="<?= h($alCorte) ?>">
  </div>
  <div class="filtros-actions">
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Generar</button>
    <button type="button" class="btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
  </div>
</form>

<div class="print-header">
  <h2><?= h($EMPRESA['nombre']) ?></h2>
  <p>NIT: <?= h($EMPRESA['nit']) ?> · <?= h($EMPRESA['ciudad']) ?></p>
  <p><strong>BALANCE GENERAL</strong></p>
  <p>Al <?= fecha_es($alCorte) ?> · <?= h($EMPRESA['moneda']) ?></p>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
  <!-- ACTIVOS -->
  <div class="card">
    <div class="card-header"><span class="badge badge-activo">ACTIVO</span></div>
    <div class="card-body" style="padding:0">
      <table class="table">
        <tbody>
        <?php if ($activos): foreach ($activos as $r): ?>
          <tr>
            <td><span class="chip"><?= h($r['codigo']) ?></span> <?= h($r['nombre']) ?></td>
            <td class="text-right num"><?= money($r['saldo']) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td class="text-muted text-center" colspan="2">Sin activos</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot><tr><td>TOTAL ACTIVO</td><td class="text-right num"><?= money($totA) ?></td></tr></tfoot>
      </table>
    </div>
  </div>

  <!-- PASIVO + PATRIMONIO -->
  <div class="card">
    <div class="card-header"><span class="badge badge-pasivo">PASIVO + PATRIMONIO</span></div>
    <div class="card-body" style="padding:0">
      <table class="table">
        <tbody>
        <?php if ($pasivos): ?>
          <tr class="fila-grupo"><td colspan="2"><span class="badge badge-pasivo">PASIVO</span></td></tr>
          <?php foreach ($pasivos as $r): ?>
            <tr>
              <td><span class="chip"><?= h($r['codigo']) ?></span> <?= h($r['nombre']) ?></td>
              <td class="text-right num"><?= money($r['saldo']) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr><td class="text-right fw-700">Subtotal Pasivo</td><td class="text-right num fw-700"><?= money($totP) ?></td></tr>
        <?php endif; ?>

        <tr class="fila-grupo"><td colspan="2"><span class="badge badge-patrimonio">PATRIMONIO</span></td></tr>
        <?php foreach ($patrimonio as $r): ?>
          <tr>
            <td><span class="chip"><?= h($r['codigo']) ?></span> <?= h($r['nombre']) ?></td>
            <td class="text-right num"><?= money($r['saldo']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td><span class="chip">—</span> Resultado del ejercicio</td>
          <td class="text-right num <?= $resultado>=0?'':'cuadre-mal' ?>"><?= money($resultado) ?></td>
        </tr>
        <tr><td class="text-right fw-700">Subtotal Patrimonio</td><td class="text-right num fw-700"><?= money($totPN + $resultado) ?></td></tr>
        </tbody>
        <tfoot><tr><td>TOTAL PASIVO + PATRIMONIO</td><td class="text-right num"><?= money($totPasPat) ?></td></tr></tfoot>
      </table>
    </div>
  </div>
</div>

<div class="card" style="border-color: <?= $cuadra?'rgba(20,184,106,.5)':'rgba(224,82,77,.5)' ?>">
  <div class="card-body" style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <div class="form-label">Ecuación contable</div>
      <div class="text-muted"><strong>Activo</strong> = Pasivo + Patrimonio + Resultado del ejercicio</div>
    </div>
    <div style="text-align:right" class="num fw-700">
      <?= money($totA) ?>  =  <?= money($totPasPat) ?><br>
      <span class="<?= $cuadra?'cuadre-ok':'cuadre-mal' ?>"><?= $cuadra?'CUADRA ✓':'NO CUADRA ✗' ?></span>
    </div>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
