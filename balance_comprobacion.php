<?php
/**
 * ContaUBI — Balance de Comprobación de Sumas y Saldos
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$pageTitle  = 'Balance de Comprobación';
$pageIcon   = 'bi-table';
$activePage = 'balance_comp';

$desde = $_GET['desde'] ?? $EMPRESA['fecha_inicio_ejercicio'];
$hasta = $_GET['hasta'] ?? $EMPRESA['fecha_cierre_ejercicio'];
$fClase= $_GET['clase'] ?? '';

$where = "c.estado='APROBADO' AND c.fecha BETWEEN ? AND ?";
$types='ss'; $params=[$desde,$hasta];
if (ctype_digit((string)$fClase) && $fClase>=1 && $fClase<=5) {
    $where .= " AND cu.clase = ?"; $types .= 'i'; $params[] = (int)$fClase;
}

$sql = "SELECT cu.id, cu.codigo, cu.nombre, cu.naturaleza, cu.clase,
               COALESCE(SUM(m.debe),0)  AS debe,
               COALESCE(SUM(m.haber),0) AS haber
        FROM cuentas cu
        LEFT JOIN movimientos m  ON m.cuenta_id = cu.id
        LEFT JOIN comprobantes c ON c.id = m.comprobante_id AND $where
        WHERE cu.es_imputable = 1
        GROUP BY cu.id, cu.codigo, cu.nombre, cu.naturaleza, cu.clase
        HAVING debe > 0 OR haber > 0
        ORDER BY cu.codigo";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$tD=0; $tH=0; $tSD=0; $tSA=0;

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
  <div class="form-group">
    <label class="form-label">Clase</label>
    <select name="clase" class="form-control">
      <option value="">Todas</option>
      <?php foreach ([1=>'ACTIVO',2=>'PASIVO',3=>'PATRIMONIO',4=>'INGRESOS',5=>'EGRESOS'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= (string)$fClase===(string)$k?'selected':'' ?>><?= $k ?> · <?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filtros-actions">
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Generar</button>
    <button type="button" class="btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
  </div>
</form>

<div class="print-header">
  <h2><?= h($EMPRESA['nombre']) ?></h2>
  <p>NIT: <?= h($EMPRESA['nit']) ?> · <?= h($EMPRESA['ciudad']) ?></p>
  <p><strong>BALANCE DE COMPROBACIÓN DE SUMAS Y SALDOS</strong></p>
  <p>Del <?= fecha_es($desde) ?> al <?= fecha_es($hasta) ?> · <?= h($EMPRESA['moneda']) ?></p>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <?php if (empty($rows)): ?>
      <div class="empty-state"><i class="bi bi-inbox"></i><p>Sin movimientos aprobados en el rango.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Cuenta</th>
            <th>Clase</th>
            <th class="text-right">Sumas Debe</th>
            <th class="text-right">Sumas Haber</th>
            <th class="text-right">Saldo Deudor</th>
            <th class="text-right">Saldo Acreedor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $diff = $r['debe'] - $r['haber'];
            $sd = $sa = 0;
            if ($r['naturaleza']==='DEUDORA') {
                if ($diff >= 0) $sd = $diff; else $sa = -$diff;
            } else {
                if ($diff <= 0) $sa = -$diff; else $sd = $diff;
            }
            $tD += $r['debe']; $tH += $r['haber']; $tSD += $sd; $tSA += $sa;
          ?>
          <tr>
            <td><span class="chip"><?= h($r['codigo']) ?></span></td>
            <td><?= h($r['nombre']) ?></td>
            <td><span class="badge <?= clase_badge((int)$r['clase']) ?>"><?= nombre_clase((int)$r['clase']) ?></span></td>
            <td class="text-right num"><?= money($r['debe']) ?></td>
            <td class="text-right num"><?= money($r['haber']) ?></td>
            <td class="text-right num <?= $sd>0?'fw-600':'text-muted' ?>"><?= money($sd) ?></td>
            <td class="text-right num <?= $sa>0?'fw-600':'text-muted' ?>"><?= money($sa) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="text-right">TOTALES</td>
            <td class="text-right num"><?= money($tD) ?></td>
            <td class="text-right num"><?= money($tH) ?></td>
            <td class="text-right num"><?= money($tSD) ?></td>
            <td class="text-right num"><?= money($tSA) ?></td>
          </tr>
          <tr>
            <td colspan="3" class="text-right">VERIFICACIÓN</td>
            <td colspan="2" class="text-right num <?= abs($tD-$tH)<.005 ? 'cuadre-ok':'cuadre-mal' ?>">
              Sumas: <?= abs($tD-$tH)<.005 ? 'CUADRA ✓' : 'NO CUADRA ✗' ?>
            </td>
            <td colspan="2" class="text-right num <?= abs($tSD-$tSA)<.005 ? 'cuadre-ok':'cuadre-mal' ?>">
              Saldos: <?= abs($tSD-$tSA)<.005 ? 'CUADRA ✓' : 'NO CUADRA ✗' ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
