<?php
/**
 * ContaUBI — Editar cuenta del plan de cuentas
 * Sólo permite cambiar nombre, descripción, naturaleza y estado.
 * El código no se puede modificar (rompería los movimientos).
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Editar Cuenta';
$pageIcon   = 'bi-pencil';
$activePage = 'cuentas';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM cuentas WHERE id=?");
$stmt->bind_param('i',$id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { flash_set('Cuenta no encontrada.','danger'); header('Location: cuentas.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $naturaleza  = $_POST['naturaleza'] ?? '';
    $activa      = $_POST['activa'] === '1' ? 1 : 0;
    $imputable   = $_POST['es_imputable'] === '1' ? 1 : 0;

    if ($nombre === '')                                 $error = 'Nombre obligatorio.';
    elseif (mb_strlen($nombre) > 120)                   $error = 'Nombre supera 120 caracteres.';
    elseif (!in_array($naturaleza, ['DEUDORA','ACREEDORA'], true))
                                                        $error = 'Naturaleza inválida.';
    else {
        /* Verificar duplicado en el mismo nivel (excluyendo la propia fila) */
        $stmt = $conn->prepare("SELECT id FROM cuentas
                                WHERE clase=? AND grupo=? AND cuenta=? AND subcuenta=?
                                  AND LOWER(nombre)=LOWER(?) AND id<>? LIMIT 1");
        $stmt->bind_param('iiiisi',
            $row['clase'], $row['grupo'], $row['cuenta'], $row['subcuenta'], $nombre, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Ya existe otra cuenta con el nombre \"$nombre\" en el mismo nivel.";
        } else {
            /* Si se está cambiando a "no imputable" y tiene movimientos, no permitir */
            if (!$imputable && $row['es_imputable']) {
                $cnt = (int)$conn->query("SELECT COUNT(*) c FROM movimientos WHERE cuenta_id=$id")
                       ->fetch_assoc()['c'];
                if ($cnt > 0) {
                    $error = "No se puede convertir a cabecera: la cuenta tiene $cnt movimiento(s).";
                }
            }
            if (!$error) {
                $stmt = $conn->prepare("UPDATE cuentas
                    SET nombre=?, descripcion=?, naturaleza=?, es_imputable=?, activa=?
                    WHERE id=?");
                $stmt->bind_param('sssiii', $nombre, $descripcion, $naturaleza, $imputable, $activa, $id);
                $stmt->execute();
                flash_set("Cuenta {$row['codigo']} actualizada.", 'success');
                header('Location: cuentas.php'); exit;
            }
        }
    }
    /* preserva valores ingresados si hay error */
    $row['nombre']=$nombre; $row['descripcion']=$descripcion; $row['naturaleza']=$naturaleza;
    $row['activa']=$activa; $row['es_imputable']=$imputable;
}

include __DIR__ . '/layout_top.php';
?>

<a href="cuentas.php" class="btn btn-ghost btn-sm" style="margin-bottom:1rem"><i class="bi bi-arrow-left"></i> Volver</a>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<div class="card anim" style="max-width:760px">
  <div class="card-header"><i class="bi bi-pencil"></i> Editando: <span class="chip"><?= h($row['codigo']) ?></span></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" maxlength="120" required value="<?= h($row['nombre']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control form-textarea"><?= h($row['descripcion']) ?></textarea>
      </div>
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Naturaleza</label>
          <select name="naturaleza" class="form-control" required>
            <option value="DEUDORA"  <?= $row['naturaleza']==='DEUDORA' ?'selected':'' ?>>DEUDORA</option>
            <option value="ACREEDORA"<?= $row['naturaleza']==='ACREEDORA'?'selected':'' ?>>ACREEDORA</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tipo</label>
          <select name="es_imputable" class="form-control">
            <option value="1" <?= $row['es_imputable']?'selected':'' ?>>Imputable</option>
            <option value="0" <?= !$row['es_imputable']?'selected':'' ?>>Cabecera</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Estado</label>
        <select name="activa" class="form-control">
          <option value="1" <?= $row['activa']?'selected':'' ?>>Activa</option>
          <option value="0" <?= !$row['activa']?'selected':'' ?>>Inactiva</option>
        </select>
      </div>
      <button class="btn btn-primary btn-block"><i class="bi bi-check-lg"></i> Guardar cambios</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
