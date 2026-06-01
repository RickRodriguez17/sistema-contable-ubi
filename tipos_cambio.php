<?php
/**
 * ContaUBI — Administración de tipos de cambio históricos
 *
 * Permite registrar y modificar el tipo de cambio (USD/BOB) y el valor
 * de la UFV para una fecha. Los comprobantes usan estos valores como
 * sugerencia (pueden sobreescribirlos por comprobante).
 *
 * Sólo accesible para roles con permiso `cuentas.gestionar` (en práctica
 * admin/contador), igual que el resto del setup contable.
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
auth_require('cuentas.gestionar');

$pageTitle  = 'Tipos de Cambio · UFV';
$pageIcon   = 'bi-currency-exchange';
$activePage = 'tipos_cambio';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'guardar';
    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM tipos_cambio WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            flash_set('Tipo de cambio eliminado.', 'success');
        }
        header('Location: tipos_cambio.php'); exit;
    }

    $id       = (int)($_POST['id'] ?? 0);
    $fecha    = trim((string)($_POST['fecha'] ?? ''));
    $tasa_usd = (float)str_replace(',', '.', (string)($_POST['tasa_usd'] ?? '0'));
    $ufv      = (float)str_replace(',', '.', (string)($_POST['ufv'] ?? '0'));
    $nota     = trim((string)($_POST['nota'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))  $error = 'Fecha inválida (YYYY-MM-DD).';
    elseif ($tasa_usd < 0 || $ufv < 0)                 $error = 'Las tasas no pueden ser negativas.';
    elseif (mb_strlen($nota) > 160)                    $error = 'La nota supera los 160 caracteres.';
    else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE tipos_cambio
                                    SET fecha=?, tasa_usd=?, ufv=?, nota=?
                                    WHERE id=?");
            $stmt->bind_param('sddsi', $fecha, $tasa_usd, $ufv, $nota, $id);
            $stmt->execute();
            if ($stmt->errno) $error = 'Error al actualizar: ' . $stmt->error;
            else { flash_set('Tipo de cambio actualizado.', 'success'); header('Location: tipos_cambio.php'); exit; }
        } else {
            $stmt = $conn->prepare("INSERT INTO tipos_cambio (fecha, tasa_usd, ufv, nota)
                                    VALUES (?, ?, ?, ?)");
            $stmt->bind_param('sdds', $fecha, $tasa_usd, $ufv, $nota);
            $stmt->execute();
            if ($stmt->errno === 1062) {
                $error = 'Ya existe un registro para esa fecha. Editá el existente o cambiá la fecha.';
            } elseif ($stmt->errno) {
                $error = 'Error al guardar: ' . $stmt->error;
            } else {
                flash_set('Tipo de cambio registrado.', 'success'); header('Location: tipos_cambio.php'); exit;
            }
        }
    }
}

/* Edición o nuevo */
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM tipos_cambio WHERE id=? LIMIT 1");
    $eid = (int)$_GET['edit'];
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
}
$form = $edit ?: [
    'id'       => 0,
    'fecha'    => date('Y-m-d'),
    'tasa_usd' => '6.960000',
    'ufv'      => '2.480000',
    'nota'     => '',
];

$rs = $conn->query("SELECT * FROM tipos_cambio ORDER BY fecha DESC, id DESC LIMIT 200");
$lista = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/layout_top.php';
?>

<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
  <a href="comprobantes.php" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-left"></i> Volver a Comprobantes</a>
  <button class="btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir histórico</button>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<div class="print-header">
  <h2><?= h($EMPRESA['nombre']) ?></h2>
  <p>NIT: <?= h($EMPRESA['nit']) ?></p>
  <p><strong>TIPOS DE CAMBIO Y UFV — HISTÓRICO</strong></p>
</div>

<div class="card anim" style="max-width:980px">
  <div class="card-header">
    <span><i class="bi bi-currency-exchange"></i> <?= $edit ? 'Editar' : 'Registrar' ?> tasa</span>
    <?php if ($edit): ?>
      <a href="tipos_cambio.php" class="btn btn-ghost btn-sm">Cancelar edición</a>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
      <div class="form-grid form-grid-4">
        <div class="form-group">
          <label class="form-label">Fecha</label>
          <input type="date" name="fecha" class="form-control" required value="<?= h($form['fecha']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">1 USD = <span class="text-muted">? Bs.</span></label>
          <input type="number" step="0.000001" min="0" name="tasa_usd" class="form-control text-right num" required value="<?= h($form['tasa_usd']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">1 UFV = <span class="text-muted">? Bs.</span></label>
          <input type="number" step="0.000001" min="0" name="ufv" class="form-control text-right num" required value="<?= h($form['ufv']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Nota <span class="text-muted">(opcional)</span></label>
          <input type="text" name="nota" maxlength="160" class="form-control" value="<?= h($form['nota']) ?>" placeholder="Fuente: BCB...">
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <button class="btn btn-primary"><i class="bi bi-save"></i> <?= $edit ? 'Actualizar' : 'Registrar' ?></button>
      </div>
    </form>
  </div>
</div>

<div class="card anim" style="margin-top:1rem">
  <div class="card-header"><i class="bi bi-list"></i> Histórico (últimos 200)</div>
  <div class="card-body" style="padding:0">
    <?php if (!$lista): ?>
      <div class="empty-state"><i class="bi bi-inbox"></i><p>No hay tipos de cambio registrados todavía.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th class="text-right">1 USD = Bs.</th>
              <th class="text-right">1 UFV = Bs.</th>
              <th>Nota</th>
              <th class="no-print" style="width:140px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lista as $tc): ?>
              <tr>
                <td><?= fecha_es($tc['fecha']) ?></td>
                <td class="text-right num"><?= number_format((float)$tc['tasa_usd'],6,'.',',') ?></td>
                <td class="text-right num"><?= number_format((float)$tc['ufv'],6,'.',',') ?></td>
                <td class="text-muted"><?= h($tc['nota']) ?></td>
                <td class="text-center no-print">
                  <a href="tipos_cambio.php?edit=<?= (int)$tc['id'] ?>" class="btn btn-ghost btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
                  <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar la tasa del <?= h($tc['fecha']) ?>?')">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int)$tc['id'] ?>">
                    <button class="btn btn-ghost btn-sm" title="Eliminar"><i class="bi bi-trash text-danger"></i></button>
                  </form>
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
