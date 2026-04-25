<?php
/**
 * ContaUBI — Libro Mayor (cuenta T) con saldo acumulado
 * Filtros: cuenta, rango fechas
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Libro Mayor';
$pageIcon   = 'bi-book';
$activePage = 'libro_mayor';

$cuentaId = (int)($_GET['cuenta_id'] ?? 0);
$desde = $_GET['desde'] ?? $EMPRESA['fecha_inicio_ejercicio'];
$hasta = $_GET['hasta'] ?? $EMPRESA['fecha_cierre_ejercicio'];

$cuentas = cuentas_imputables($conn, false);

$cuenta = null; $movs = []; $saldoIni = 0.0;
if ($cuentaId > 0) {
    $stmt = $conn->prepare("SELECT * FROM cuentas WHERE id=?");
    $stmt->bind_param('i',$cuentaId); $stmt->execute();
    $cuenta = $stmt->get_result()->fetch_assoc();

    if ($cuenta) {
        /* saldo inicial = movimientos aprobados antes de $desde */
        $stmt = $conn->prepare("SELECT
                COALESCE(SUM(m.debe),0) d, COALESCE(SUM(m.haber),0) h
              FROM movimientos m
              JOIN comprobantes c ON c.id=m.comprobante_id
              WHERE m.cuenta_id=? AND c.estado='APROBADO' AND c.fecha < ?");
        $stmt->bind_param('is',$cuentaId,$desde); $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $saldoIni = ($cuenta['naturaleza']==='DEUDORA') ? ($r['d']-$r['h']) : ($r['h']-$r['d']);

        $stmt = $conn->prepare("SELECT m.*, c.numero, c.fecha, c.glosa, c.estado, c.tipo
                                FROM movimientos m JOIN comprobantes c ON c.id=m.comprobante_id
                                WHERE m.cuenta_id=? AND c.estado='APROBADO'
                                  AND c.fecha BETWEEN ? AND ?
                                ORDER BY c.fecha, c.id, m.orden");
        $stmt->bind_param('iss',$cuentaId,$desde,$hasta);
        $stmt->execute();
        $movs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

include __DIR__ . '/layout_top.php';
?>

<form method="GET" class="filtros no-print">
  <div class="form-group" style="grid-column: span 2">
    <label class="form-label">Cuenta</label>
    <select name="cuenta_id" class="form-control" required>
      <option value="">— seleccione una cuenta —</option>
      <?php foreach ($cuentas as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $cuentaId===(int)$c['id']?'selected':'' ?>>
          <?= h($c['codigo']) ?> · <?= h($c['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Desde</label>
    <input type="date" name="desde" class="form-control" value="<?= h($desde) ?>">
  </div>
  <div class="form-group">
    <label class="form-label">Hasta</label>
    <input type="date" name="hasta" class="form-control" value="<?= h($hasta) ?>">
  </div>
  <div class="filtros-actions">
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Ver mayor</button>
    <button type="button" class="btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
  </div>
</form>

<?php if (!$cuenta): ?>
  <div class="empty-state"><i class="bi bi-book"></i><p>Seleccioná una cuenta y un rango de fechas para ver su Libro Mayor.</p></div>
<?php else:
    $saldo = $saldoIni;
    $sumD = 0; $sumH = 0;
?>

<div class="print-header">
  <h2><?= h($EMPRESA['nombre']) ?></h2>
  <p>NIT: <?= h($EMPRESA['nit']) ?> · <?= h($EMPRESA['ciudad']) ?></p>
  <p><strong>LIBRO MAYOR — <?= h($cuenta['codigo']) ?> · <?= h($cuenta['nombre']) ?></strong></p>
  <p>Del <?= fecha_es($desde) ?> al <?= fecha_es($hasta) ?></p>
</div>

<div class="card">
  <div class="card-header">
    <span><span class="chip"><?= h($cuenta['codigo']) ?></span> · <strong><?= h($cuenta['nombre']) ?></strong> ·
          <span class="text-muted">Naturaleza: <?= h($cuenta['naturaleza']) ?></span></span>
    <span class="text-muted">Saldo inicial: <span class="num fw-700"><?= money($saldoIni) ?></span></span>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($movs)): ?>
      <div class="empty-state"><i class="bi bi-inbox"></i><p>Sin movimientos aprobados en el rango.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Comprobante</th>
            <th>Glosa</th>
            <th class="text-right">Debe</th>
            <th class="text-right">Haber</th>
            <th class="text-right">Saldo</th>
          </tr>
        </thead>
        <tbody>
          <tr class="fila-grupo">
            <td colspan="5" class="text-right">SALDO INICIAL</td>
            <td class="text-right num fw-700"><?= money($saldoIni) ?></td>
          </tr>
          <?php foreach ($movs as $m):
            $sumD += $m['debe']; $sumH += $m['haber'];
            $saldo += ($cuenta['naturaleza']==='DEUDORA') ? ($m['debe']-$m['haber']) : ($m['haber']-$m['debe']);
          ?>
          <tr>
            <td><?= fecha_es($m['fecha']) ?></td>
            <td><span class="chip"><?= h($m['numero']) ?></span></td>
            <td class="text-muted"><?= h($m['glosa_linea'] ?: $m['glosa']) ?></td>
            <td class="text-right num <?= $m['debe']>0?'fw-600':'text-muted' ?>"><?= money($m['debe']) ?></td>
            <td class="text-right num <?= $m['haber']>0?'fw-600':'text-muted' ?>"><?= money($m['haber']) ?></td>
            <td class="text-right num fw-700"><?= money($saldo) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="text-right">TOTALES</td>
            <td class="text-right num"><?= money($sumD) ?></td>
            <td class="text-right num"><?= money($sumH) ?></td>
            <td class="text-right num fw-700">SALDO FINAL: <?= money($saldo) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/layout_bottom.php'; ?>
