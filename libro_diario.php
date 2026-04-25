<?php
/**
 * ContaUBI — Libro Diario
 * Lista cronológica de comprobantes con sus líneas; filtros por rango y tipo.
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Libro Diario';
$pageIcon   = 'bi-journal-text';
$activePage = 'libro_diario';

$desde = $_GET['desde'] ?? $EMPRESA['fecha_inicio_ejercicio'];
$hasta = $_GET['hasta'] ?? $EMPRESA['fecha_cierre_ejercicio'];
$tipo  = $_GET['tipo']  ?? '';
$estado= $_GET['estado']?? 'APROBADO';

$where = ['c.fecha BETWEEN ? AND ?']; $types='ss'; $params=[$desde,$hasta];
if (in_array($tipo, ['INGRESO','EGRESO','TRASPASO','APERTURA','CIERRE','AJUSTE'], true)) {
    $where[]='c.tipo=?'; $types.='s'; $params[]=$tipo;
}
if (in_array($estado, ['BORRADOR','APROBADO','ANULADO','TODOS'], true) && $estado !== 'TODOS') {
    $where[]='c.estado=?'; $types.='s'; $params[]=$estado;
}

$sql = "SELECT c.id, c.numero, c.fecha, c.tipo, c.glosa, c.estado, c.total_debe, c.total_haber
        FROM comprobantes c
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.fecha ASC, c.id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$comprs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Pre-cargar movimientos */
$movs = [];
if ($comprs) {
    $ids = implode(',', array_column($comprs,'id'));
    $rs = $conn->query("SELECT m.*, cu.codigo, cu.nombre
                        FROM movimientos m JOIN cuentas cu ON cu.id=m.cuenta_id
                        WHERE m.comprobante_id IN ($ids)
                        ORDER BY m.comprobante_id, m.orden, m.id");
    foreach ($rs->fetch_all(MYSQLI_ASSOC) as $m) { $movs[$m['comprobante_id']][] = $m; }
}

$totalD = array_sum(array_column($comprs,'total_debe'));
$totalH = array_sum(array_column($comprs,'total_haber'));

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
    <label class="form-label">Tipo</label>
    <select name="tipo" class="form-control">
      <option value="">Todos</option>
      <?php foreach (['INGRESO','EGRESO','TRASPASO','APERTURA','CIERRE','AJUSTE'] as $t): ?>
        <option value="<?= $t ?>" <?= $tipo===$t?'selected':'' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Estado</label>
    <select name="estado" class="form-control">
      <option value="APROBADO" <?= $estado==='APROBADO'?'selected':'' ?>>Aprobado</option>
      <option value="BORRADOR" <?= $estado==='BORRADOR'?'selected':'' ?>>Borrador</option>
      <option value="ANULADO"  <?= $estado==='ANULADO'?'selected':'' ?>>Anulado</option>
      <option value="TODOS"    <?= $estado==='TODOS'?'selected':'' ?>>Todos</option>
    </select>
  </div>
  <div class="filtros-actions">
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
    <button type="button" class="btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
  </div>
</form>

<div class="print-header">
  <h2><?= h($EMPRESA['nombre']) ?></h2>
  <p>NIT: <?= h($EMPRESA['nit']) ?> · <?= h($EMPRESA['ciudad']) ?></p>
  <p><strong>LIBRO DIARIO</strong></p>
  <p>Del <?= fecha_es($desde) ?> al <?= fecha_es($hasta) ?> · Estado: <?= h($estado) ?> · Emitido: <?= date('d/m/Y H:i') ?></p>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <?php if (empty($comprs)): ?>
      <div class="empty-state"><i class="bi bi-inbox"></i><p>Sin comprobantes en el rango.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Comprobante</th>
            <th>Cuenta</th>
            <th>Glosa</th>
            <th class="text-right">Debe</th>
            <th class="text-right">Haber</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($comprs as $c): ?>
            <tr class="fila-grupo">
              <td><?= fecha_es($c['fecha']) ?></td>
              <td><span class="chip"><?= h($c['numero']) ?></span> <span class="tipo-<?= h($c['tipo']) ?>"><?= h($c['tipo']) ?></span></td>
              <td colspan="2"><strong><?= h($c['glosa']) ?></strong> <span class="<?= estado_badge($c['estado']) ?>" style="margin-left:.5rem"><?= h($c['estado']) ?></span></td>
              <td class="text-right num"><?= money($c['total_debe']) ?></td>
              <td class="text-right num"><?= money($c['total_haber']) ?></td>
            </tr>
            <?php foreach (($movs[$c['id']] ?? []) as $m): ?>
              <tr>
                <td></td>
                <td></td>
                <td><span class="chip"><?= h($m['codigo']) ?></span> <?= h($m['nombre']) ?></td>
                <td class="text-muted"><?= h($m['glosa_linea']) ?></td>
                <td class="text-right num <?= $m['debe']>0?'fw-600':'text-muted' ?>"><?= money($m['debe']) ?></td>
                <td class="text-right num <?= $m['haber']>0?'fw-600':'text-muted' ?>"><?= money($m['haber']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="4" class="text-right">TOTALES DEL PERÍODO</td>
            <td class="text-right num"><?= money($totalD) ?></td>
            <td class="text-right num"><?= money($totalH) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
