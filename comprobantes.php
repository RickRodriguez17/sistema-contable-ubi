<?php
/**
 * ContaUBI — Listado de comprobantes con filtros
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Comprobantes';
$pageIcon   = 'bi-receipt';
$activePage = 'comprobantes';

$fDesde  = $_GET['desde']  ?? '';
$fHasta  = $_GET['hasta']  ?? '';
$fTipo   = $_GET['tipo']   ?? '';
$fEstado = $_GET['estado'] ?? '';
$fQ      = trim($_GET['q'] ?? '');

$where = []; $types=''; $params=[];
if ($fDesde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDesde)) { $where[]='c.fecha >= ?'; $types.='s'; $params[]=$fDesde; }
if ($fHasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fHasta)) { $where[]='c.fecha <= ?'; $types.='s'; $params[]=$fHasta; }
if (in_array($fTipo, ['INGRESO','EGRESO','TRASPASO','APERTURA','CIERRE','AJUSTE'], true)) { $where[]='c.tipo=?'; $types.='s'; $params[]=$fTipo; }
if (in_array($fEstado, ['BORRADOR','APROBADO','ANULADO'], true)) { $where[]='c.estado=?'; $types.='s'; $params[]=$fEstado; }
if ($fQ !== '') { $where[]='(c.numero LIKE ? OR LOWER(c.glosa) LIKE ?)'; $types.='ss'; $like='%'.mb_strtolower($fQ).'%'; $params[]=$like; $params[]=$like; }

$sql = "SELECT c.* FROM comprobantes c " . ($where ? 'WHERE '.implode(' AND ',$where) : '') . " ORDER BY c.fecha DESC, c.id DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/layout_top.php';
?>

<form method="GET" class="filtros no-print">
  <div class="form-group">
    <label class="form-label">Desde</label>
    <input type="date" name="desde" class="form-control" value="<?= h($fDesde) ?>">
  </div>
  <div class="form-group">
    <label class="form-label">Hasta</label>
    <input type="date" name="hasta" class="form-control" value="<?= h($fHasta) ?>">
  </div>
  <div class="form-group">
    <label class="form-label">Tipo</label>
    <select name="tipo" class="form-control">
      <option value="">Todos</option>
      <?php foreach (['INGRESO','EGRESO','TRASPASO','APERTURA','CIERRE','AJUSTE'] as $t): ?>
        <option value="<?= $t ?>" <?= $fTipo===$t?'selected':'' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Estado</label>
    <select name="estado" class="form-control">
      <option value="">Todos</option>
      <?php foreach (['BORRADOR','APROBADO','ANULADO'] as $e): ?>
        <option value="<?= $e ?>" <?= $fEstado===$e?'selected':'' ?>><?= $e ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" style="grid-column: span 2">
    <label class="form-label">Buscar</label>
    <input type="search" name="q" class="form-control" placeholder="Número o glosa…" value="<?= h($fQ) ?>">
  </div>
  <div class="filtros-actions">
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn" href="comprobantes.php"><i class="bi bi-x-circle"></i> Limpiar</a>
  </div>
</form>

<div class="card-header" style="background:transparent;border:0;padding:0 0 .75rem 0">
  <span class="text-muted"><?= count($rows) ?> comprobante(s)</span>
  <a class="btn btn-primary" href="comprobante_crear.php"><i class="bi bi-plus-circle"></i> Nuevo Asiento</a>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <?php if (empty($rows)): ?>
      <div class="empty-state"><i class="bi bi-inbox"></i><p>No hay comprobantes con esos filtros.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Número</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Glosa</th>
            <th class="text-right">Debe</th>
            <th class="text-right">Haber</th>
            <th>Estado</th>
            <th class="text-center"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $c): ?>
          <tr>
            <td><span class="chip"><?= h($c['numero']) ?></span></td>
            <td><?= fecha_es($c['fecha']) ?></td>
            <td class="tipo-<?= h($c['tipo']) ?> fw-600"><?= h($c['tipo']) ?></td>
            <td class="text-muted"><?= h(mb_strimwidth($c['glosa'],0,80,'…')) ?></td>
            <td class="text-right num"><?= money($c['total_debe']) ?></td>
            <td class="text-right num"><?= money($c['total_haber']) ?></td>
            <td><span class="<?= estado_badge($c['estado']) ?>"><?= h($c['estado']) ?></span></td>
            <td class="text-center">
              <a class="btn btn-ghost btn-sm" title="Ver" href="comprobante_ver.php?id=<?= (int)$c['id'] ?>"><i class="bi bi-eye"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
