<?php
/**
 * ContaUBI — Configuración de la empresa
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
auth_require('empresa.editar');

$pageTitle  = 'Empresa';
$pageIcon   = 'bi-building';
$activePage = 'empresa';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = [
        'nombre'    => trim($_POST['nombre'] ?? ''),
        'nit'       => trim($_POST['nit'] ?? ''),
        'ciudad'    => trim($_POST['ciudad'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'telefono'  => trim($_POST['telefono'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'moneda'    => trim($_POST['moneda'] ?? 'Bs.'),
        'ejercicio' => (int)($_POST['ejercicio'] ?? date('Y')),
        'fecha_inicio_ejercicio' => $_POST['fecha_inicio'] ?? '',
        'fecha_cierre_ejercicio' => $_POST['fecha_cierre'] ?? '',
    ];

    if ($f['nombre']==='')                                                    $error = 'Nombre obligatorio.';
    elseif (!preg_match('/^\d{4}$/', (string)$f['ejercicio']))                $error = 'Ejercicio debe ser año (4 dígitos).';
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['fecha_inicio_ejercicio'])
         || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['fecha_cierre_ejercicio']))
                                                                              $error = 'Fechas de ejercicio inválidas.';
    elseif ($f['fecha_inicio_ejercicio'] > $f['fecha_cierre_ejercicio'])      $error = 'La fecha de inicio no puede ser posterior al cierre.';
    else {
        $stmt = $conn->prepare("UPDATE empresa SET
            nombre=?, nit=?, ciudad=?, direccion=?, telefono=?, email=?, moneda=?, ejercicio=?,
            fecha_inicio_ejercicio=?, fecha_cierre_ejercicio=?
            WHERE id = ?");
        $stmt->bind_param('sssssssisss',
            $f['nombre'],$f['nit'],$f['ciudad'],$f['direccion'],$f['telefono'],$f['email'],
            $f['moneda'],$f['ejercicio'],$f['fecha_inicio_ejercicio'],$f['fecha_cierre_ejercicio'],
            $EMPRESA['id']);
        try {
            $stmt->execute();
            flash_set('Configuración de empresa actualizada.','success');
            header('Location: empresa.php'); exit;
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
    $EMPRESA = array_merge($EMPRESA, $f);
}

include __DIR__ . '/layout_top.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<div class="card anim" style="max-width:760px">
  <div class="card-header"><i class="bi bi-building"></i> Datos de la empresa</div>
  <div class="card-body">
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Razón social</label>
        <input type="text" name="nombre" class="form-control" maxlength="150" required value="<?= h($EMPRESA['nombre']) ?>">
      </div>
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">NIT</label>
          <input type="text" name="nit" class="form-control" maxlength="20" value="<?= h($EMPRESA['nit']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Ciudad</label>
          <input type="text" name="ciudad" class="form-control" maxlength="80" value="<?= h($EMPRESA['ciudad']) ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Dirección</label>
        <input type="text" name="direccion" class="form-control" maxlength="200" value="<?= h($EMPRESA['direccion']) ?>">
      </div>
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Teléfono</label>
          <input type="text" name="telefono" class="form-control" maxlength="40" value="<?= h($EMPRESA['telefono']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" maxlength="120" value="<?= h($EMPRESA['email']) ?>">
        </div>
      </div>

      <h6 style="margin-top:1.5rem;color:var(--gold-2)"><i class="bi bi-calendar-range"></i> Ejercicio contable</h6>
      <div class="form-grid form-grid-3">
        <div class="form-group">
          <label class="form-label">Año</label>
          <input type="number" name="ejercicio" class="form-control" min="2000" max="2100" required value="<?= h($EMPRESA['ejercicio']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Fecha inicio</label>
          <input type="date" name="fecha_inicio" class="form-control" required value="<?= h($EMPRESA['fecha_inicio_ejercicio']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Fecha cierre</label>
          <input type="date" name="fecha_cierre" class="form-control" required value="<?= h($EMPRESA['fecha_cierre_ejercicio']) ?>">
        </div>
      </div>

      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Moneda</label>
          <input type="text" name="moneda" class="form-control" maxlength="10" required value="<?= h($EMPRESA['moneda']) ?>">
          <div class="form-hint">Ej: Bs., USD</div>
        </div>
      </div>

      <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
