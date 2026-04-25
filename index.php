<?php
/**
 * ContaUBI — Dashboard / Inicio
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Inicio';
$pageIcon   = 'bi-speedometer2';
$activePage = 'home';

$totalCuentas    = (int)$conn->query("SELECT COUNT(*) c FROM cuentas WHERE es_imputable=1")->fetch_assoc()['c'];
$totalGruposCab  = (int)$conn->query("SELECT COUNT(*) c FROM cuentas WHERE es_imputable=0")->fetch_assoc()['c'];
$totalCompr      = (int)$conn->query("SELECT COUNT(*) c FROM comprobantes WHERE estado<>'ANULADO'")->fetch_assoc()['c'];
$totalAprobados  = (int)$conn->query("SELECT COUNT(*) c FROM comprobantes WHERE estado='APROBADO'")->fetch_assoc()['c'];
$totalBorrador   = (int)$conn->query("SELECT COUNT(*) c FROM comprobantes WHERE estado='BORRADOR'")->fetch_assoc()['c'];

/* Sumas del ejercicio (sólo aprobados) */
$sumas = $conn->query("SELECT
    COALESCE(SUM(m.debe),0)  AS debe,
    COALESCE(SUM(m.haber),0) AS haber
  FROM movimientos m
  JOIN comprobantes c ON c.id = m.comprobante_id
  WHERE c.estado='APROBADO'")->fetch_assoc();

/* Saldo por clase (sólo aprobados) */
$saldosClase = $conn->query("SELECT cu.clase,
       SUM(CASE WHEN cu.naturaleza='DEUDORA'   THEN m.debe-m.haber ELSE m.haber-m.debe END) AS saldo
  FROM movimientos m
  JOIN cuentas cu ON cu.id = m.cuenta_id
  JOIN comprobantes c ON c.id = m.comprobante_id
  WHERE c.estado='APROBADO'
  GROUP BY cu.clase
  ORDER BY cu.clase")->fetch_all(MYSQLI_ASSOC);

$saldoPorClase = [1=>0,2=>0,3=>0,4=>0,5=>0];
foreach ($saldosClase as $r) { $saldoPorClase[(int)$r['clase']] = (float)$r['saldo']; }

$utilidad = ($saldoPorClase[4] ?? 0) - ($saldoPorClase[5] ?? 0);

/* Últimos comprobantes */
$ultimos = $conn->query("SELECT id, numero, fecha, tipo, glosa, estado, total_debe, total_haber
                         FROM comprobantes
                         ORDER BY id DESC
                         LIMIT 8")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/layout_top.php';
?>

<div class="anim">
  <div class="kpi-grid">
    <div class="kpi">
      <div class="kpi-label"><i class="bi bi-list-columns-reverse"></i> Cuentas Imputables</div>
      <div class="kpi-value"><?= $totalCuentas ?></div>
      <div class="kpi-sub"><?= $totalGruposCab ?> grupos / cabeceras</div>
    </div>
    <div class="kpi kpi-info">
      <div class="kpi-label"><i class="bi bi-receipt"></i> Comprobantes (vigentes)</div>
      <div class="kpi-value"><?= $totalCompr ?></div>
      <div class="kpi-sub"><?= $totalAprobados ?> aprobados · <?= $totalBorrador ?> en borrador</div>
    </div>
    <div class="kpi kpi-gold">
      <div class="kpi-label"><i class="bi bi-arrow-down-right-circle"></i> Total Debe (aprobado)</div>
      <div class="kpi-value"><?= money($sumas['debe']) ?></div>
      <div class="kpi-sub"><?= h($EMPRESA['moneda']) ?></div>
    </div>
    <div class="kpi kpi-gold">
      <div class="kpi-label"><i class="bi bi-arrow-up-right-circle"></i> Total Haber (aprobado)</div>
      <div class="kpi-value"><?= money($sumas['haber']) ?></div>
      <div class="kpi-sub"><?= h($EMPRESA['moneda']) ?></div>
    </div>
    <div class="kpi <?= $utilidad >= 0 ? '' : 'kpi-danger' ?>">
      <div class="kpi-label"><i class="bi bi-graph-up"></i> Resultado del Ejercicio</div>
      <div class="kpi-value"><?= money($utilidad) ?></div>
      <div class="kpi-sub"><?= $utilidad >= 0 ? 'Utilidad' : 'Pérdida' ?> · <?= h($EMPRESA['moneda']) ?></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span><i class="bi bi-pie-chart"></i> Saldos por clase contable</span>
      <a href="balance_comprobacion.php" class="btn btn-ghost btn-sm">Ver Balance</a>
    </div>
    <div class="card-body">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Clase</th>
              <th class="text-right">Saldo</th>
              <th>Composición</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $maxAbs = max(array_map('abs', $saldoPorClase)) ?: 1;
          for ($c=1; $c<=5; $c++):
            $s = (float)($saldoPorClase[$c] ?? 0);
            $pct = abs($s) / $maxAbs * 100;
            $color = ['1'=>'#14b86a','2'=>'#e0524d','3'=>'#4f8ef7','4'=>'#14b86a','5'=>'#e0a44d'][$c];
          ?>
            <tr>
              <td>
                <span class="badge <?= clase_badge($c) ?>"><?= nombre_clase($c) ?></span>
              </td>
              <td class="text-right num fw-700"><?= money($s) ?></td>
              <td>
                <div style="background: rgba(255,255,255,.05); border-radius: 4px; height: 8px; overflow: hidden;">
                  <div style="width: <?= number_format($pct,1) ?>%; height: 100%; background: <?= $color ?>;"></div>
                </div>
              </td>
            </tr>
          <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span><i class="bi bi-clock-history"></i> Últimos comprobantes</span>
      <a href="comprobantes.php" class="btn btn-ghost btn-sm">Ver todos <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($ultimos)): ?>
        <div class="empty-state">
          <i class="bi bi-inbox"></i>
          <p>Aún no hay comprobantes registrados.</p>
          <a href="comprobante_crear.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Registrar el primer comprobante</a>
        </div>
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
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ultimos as $c): ?>
              <tr>
                <td><span class="chip"><?= h($c['numero']) ?></span></td>
                <td><?= fecha_es($c['fecha']) ?></td>
                <td class="tipo-<?= h($c['tipo']) ?> fw-600"><?= h($c['tipo']) ?></td>
                <td class="text-muted"><?= h(mb_strimwidth($c['glosa'],0,55,'…')) ?></td>
                <td class="text-right num"><?= money($c['total_debe']) ?></td>
                <td class="text-right num"><?= money($c['total_haber']) ?></td>
                <td><span class="<?= estado_badge($c['estado']) ?>"><?= h($c['estado']) ?></span></td>
                <td><a class="btn btn-ghost btn-sm" href="comprobante_ver.php?id=<?= (int)$c['id'] ?>"><i class="bi bi-eye"></i></a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
