<?php
/**
 * ContaUBI — Ver comprobante (con acciones aprobar/anular)
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$pageTitle  = 'Comprobante';
$pageIcon   = 'bi-receipt';
$activePage = 'comprobantes';

$id = (int)($_GET['id'] ?? 0);

/* Acción POST: aprobar / anular (sólo admin) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_can('comprobantes.aprobar')) {
        flash_set('No tenés permiso para aprobar o anular comprobantes.', 'danger');
        header('Location: comprobantes.php'); exit;
    }
    $accion = $_POST['accion'] ?? '';
    $cidPost = (int)($_POST['id'] ?? 0);
    $cur = $conn->query("SELECT * FROM comprobantes WHERE id=$cidPost")->fetch_assoc();
    if (!$cur) { flash_set('Comprobante no encontrado.','danger'); header('Location: comprobantes.php'); exit; }

    if ($accion === 'aprobar' && $cur['estado'] === 'BORRADOR') {
        if (!comprobante_cuadra((float)$cur['total_debe'], (float)$cur['total_haber'])) {
            flash_set('No se puede aprobar: el asiento no cuadra.', 'danger');
        } else {
            $conn->query("UPDATE comprobantes SET estado='APROBADO' WHERE id=$cidPost");
            flash_set("Comprobante {$cur['numero']} aprobado.", 'success');
        }
    } elseif ($accion === 'anular' && in_array($cur['estado'], ['BORRADOR','APROBADO'], true)) {
        $conn->query("UPDATE comprobantes SET estado='ANULADO' WHERE id=$cidPost");
        flash_set("Comprobante {$cur['numero']} anulado.", 'warn');
    } elseif ($accion === 'reactivar' && $cur['estado'] === 'ANULADO') {
        $conn->query("UPDATE comprobantes SET estado='BORRADOR' WHERE id=$cidPost");
        flash_set("Comprobante {$cur['numero']} reactivado en BORRADOR.", 'info');
    }
    header('Location: comprobante_ver.php?id=' . $cidPost); exit;
}

$cur = $conn->query("SELECT * FROM comprobantes WHERE id=$id")->fetch_assoc();
if (!$cur) { flash_set('Comprobante no encontrado.','danger'); header('Location: comprobantes.php'); exit; }

$rs = $conn->query("SELECT m.*, cu.codigo, cu.nombre, cu.naturaleza
                    FROM movimientos m
                    JOIN cuentas cu ON cu.id = m.cuenta_id
                    WHERE m.comprobante_id={$id}
                    ORDER BY m.orden, m.id");
$lineas = $rs->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Comprobante ' . $cur['numero'];

include __DIR__ . '/layout_top.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem" class="no-print">
  <a href="comprobantes.php" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
  <div style="display:flex;gap:.5rem">
    <button class="btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    <?php if (auth_can('comprobantes.aprobar')): ?>
    <?php if ($cur['estado'] === 'BORRADOR'): ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('¿Aprobar el comprobante? Una vez aprobado afecta a los reportes.')">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="accion" value="aprobar">
        <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Aprobar</button>
      </form>
      <form method="POST" style="display:inline" onsubmit="return confirm('¿Anular el comprobante?')">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="accion" value="anular">
        <button class="btn btn-danger"><i class="bi bi-x-octagon"></i> Anular</button>
      </form>
    <?php elseif ($cur['estado'] === 'APROBADO'): ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('¿Anular un comprobante aprobado?')">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="accion" value="anular">
        <button class="btn btn-danger"><i class="bi bi-x-octagon"></i> Anular</button>
      </form>
    <?php elseif ($cur['estado'] === 'ANULADO'): ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('¿Reactivar el comprobante en estado BORRADOR?')">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="accion" value="reactivar">
        <button class="btn"><i class="bi bi-arrow-counterclockwise"></i> Reactivar</button>
      </form>
    <?php endif; ?>
    <?php endif; /* auth_can comprobantes.aprobar */ ?>
  </div>
</div>

<div class="print-header">
  <h2><?= h($EMPRESA['nombre']) ?></h2>
  <p>NIT: <?= h($EMPRESA['nit']) ?> · <?= h($EMPRESA['ciudad']) ?></p>
  <p><strong>COMPROBANTE CONTABLE N° <?= h($cur['numero']) ?></strong></p>
</div>

<div class="card anim">
  <div class="card-header">
    <span><i class="bi bi-receipt-cutoff"></i> <span class="chip"><?= h($cur['numero']) ?></span></span>
    <span class="<?= estado_badge($cur['estado']) ?>"><?= h($cur['estado']) ?></span>
  </div>
  <div class="card-body">
    <div class="form-grid form-grid-3" style="margin-bottom:1rem">
      <div><div class="form-label">Fecha</div><div class="fw-600"><?= fecha_es($cur['fecha']) ?></div></div>
      <div><div class="form-label">Tipo</div><div class="fw-600 tipo-<?= h($cur['tipo']) ?>"><?= h($cur['tipo']) ?></div></div>
      <div><div class="form-label">Moneda</div><div class="fw-600"><?= h($cur['moneda']) ?></div></div>
    </div>
    <div class="form-group">
      <div class="form-label">Glosa</div>
      <div class="fw-600" style="font-size:1rem"><?= h($cur['glosa']) ?></div>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Cuenta</th>
            <th>Glosa línea</th>
            <th class="text-right">Debe</th>
            <th class="text-right">Haber</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lineas as $i => $l): ?>
          <tr>
            <td class="text-muted">#<?= $i+1 ?></td>
            <td><span class="chip"><?= h($l['codigo']) ?></span> <?= h($l['nombre']) ?></td>
            <td class="text-muted"><?= h($l['glosa_linea']) ?></td>
            <td class="text-right num <?= $l['debe']>0?'fw-600':'text-muted' ?>"><?= money($l['debe']) ?></td>
            <td class="text-right num <?= $l['haber']>0?'fw-600':'text-muted' ?>"><?= money($l['haber']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="text-right">TOTALES</td>
            <td class="text-right num"><?= money($cur['total_debe']) ?></td>
            <td class="text-right num"><?= money($cur['total_haber']) ?></td>
          </tr>
          <tr>
            <td colspan="3" class="text-right">CUADRE</td>
            <td colspan="2" class="text-right num <?= comprobante_cuadra((float)$cur['total_debe'],(float)$cur['total_haber']) ? 'cuadre-ok' : 'cuadre-mal' ?>">
              <?= comprobante_cuadra((float)$cur['total_debe'],(float)$cur['total_haber']) ? 'CUADRA ✓' : 'NO CUADRA ✗' ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
