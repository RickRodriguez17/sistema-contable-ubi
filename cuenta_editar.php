<?php
/**
 * ContaUBI — Editar cuenta del plan de cuentas
 *
 * Reglas:
 *   - Cuentas PUCT (es_puct=1): SOLO se puede modificar descripción y estado.
 *     Nombre / naturaleza / código son inmutables (los fija el SIN).
 *   - Cuentas Analíticas propias (es_puct=0): edición completa de
 *     nombre, descripción, naturaleza y estado.
 *   - El código nunca se puede cambiar (rompería los movimientos).
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
auth_require('cuentas.gestionar');

$pageTitle  = 'Editar Cuenta';
$pageIcon   = 'bi-pencil';
$activePage = 'cuentas';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM cuentas WHERE id=?");
$stmt->bind_param('i',$id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { flash_set('Cuenta no encontrada.','danger'); header('Location: cuentas.php'); exit; }

$es_puct = (int)$row['es_puct'] === 1;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activa      = ($_POST['activa'] ?? '0') === '1' ? 1 : 0;

    if ($es_puct) {
        /* Cuentas PUCT: sólo descripción + estado */
        $stmt = $conn->prepare("UPDATE cuentas SET descripcion=?, activa=? WHERE id=?");
        $stmt->bind_param('sii', $descripcion, $activa, $id);
        $stmt->execute();
        flash_set("Cuenta PUCT {$row['codigo']} actualizada (descripción / estado).", 'success');
        header('Location: cuentas.php'); exit;
    }

    /* Cuentas analíticas propias: edición completa */
    $nombre     = trim($_POST['nombre'] ?? '');
    $naturaleza = $_POST['naturaleza'] ?? '';

    if ($nombre === '')                                           $error = 'Nombre obligatorio.';
    elseif (mb_strlen($nombre) > 160)                             $error = 'Nombre supera 160 caracteres.';
    elseif (!in_array($naturaleza, ['DEUDORA','ACREEDORA'], true)) $error = 'Naturaleza inválida.';
    else {
        /* Verificar duplicado en el mismo CP padre (excluyendo la propia fila) */
        $stmt = $conn->prepare("SELECT id FROM cuentas
                                WHERE clase=? AND grupo=? AND subgrupo=? AND cuenta_principal=?
                                  AND LOWER(nombre)=LOWER(?) AND id<>? LIMIT 1");
        $stmt->bind_param('iiiisi',
            $row['clase'], $row['grupo'], $row['subgrupo'], $row['cuenta_principal'],
            $nombre, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Ya existe otra cuenta con el nombre \"$nombre\" bajo la misma Cuenta Principal.";
        } else {
            $stmt = $conn->prepare("UPDATE cuentas
                SET nombre=?, descripcion=?, naturaleza=?, activa=?
                WHERE id=?");
            $stmt->bind_param('sssii', $nombre, $descripcion, $naturaleza, $activa, $id);
            $stmt->execute();
            flash_set("Cuenta {$row['codigo']} actualizada.", 'success');
            header('Location: cuentas.php'); exit;
        }
    }
    /* preserva valores ingresados si hay error */
    $row['nombre']=$nombre; $row['descripcion']=$descripcion;
    $row['naturaleza']=$naturaleza; $row['activa']=$activa;
}

include __DIR__ . '/layout_top.php';
?>

<a href="cuentas.php" class="btn btn-ghost btn-sm" style="margin-bottom:1rem">
  <i class="bi bi-arrow-left"></i> Volver
</a>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<div class="card anim" style="max-width:760px">
  <div class="card-header">
    <i class="bi bi-pencil"></i>
    Editando: <span class="chip"><?= h($row['codigo']) ?></span>
    <span class="badge <?= clase_badge((int)$row['clase']) ?>" style="margin-left:.5rem"><?= nombre_clase((int)$row['clase']) ?></span>
    <span class="badge badge-nivel" style="margin-left:.25rem"><?= h(nombre_nivel((int)$row['nivel'])) ?></span>
    <?php if ($es_puct): ?>
      <span class="badge badge-puct" style="margin-left:.25rem" title="Estructura oficial del SIN">PUCT</span>
    <?php else: ?>
      <span class="badge badge-propia" style="margin-left:.25rem" title="Cuenta analítica del contribuyente">Propia</span>
    <?php endif; ?>
  </div>
  <div class="card-body">

    <?php if ($es_puct): ?>
      <div class="alert alert-info" style="margin-bottom:1.25rem">
        <i class="bi bi-shield-lock"></i>
        <div>
          Esta cuenta forma parte del <strong>PUCT oficial</strong> del SIN.
          El nombre, código y naturaleza son inmutables. Sólo se puede ajustar
          la <em>descripción</em> y el <em>estado activa/inactiva</em>.
        </div>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" maxlength="160"
               <?= $es_puct ? 'disabled' : 'required' ?>
               value="<?= h($row['nombre']) ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control form-textarea"><?= h($row['descripcion']) ?></textarea>
      </div>

      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Naturaleza</label>
          <select name="naturaleza" class="form-control" <?= $es_puct ? 'disabled' : 'required' ?>>
            <option value="DEUDORA"  <?= $row['naturaleza']==='DEUDORA' ?'selected':'' ?>>DEUDORA</option>
            <option value="ACREEDORA"<?= $row['naturaleza']==='ACREEDORA'?'selected':'' ?>>ACREEDORA</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Estado</label>
          <select name="activa" class="form-control">
            <option value="1" <?= $row['activa']?'selected':'' ?>>Activa</option>
            <option value="0" <?= !$row['activa']?'selected':'' ?>>Inactiva</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <a href="cuentas.php" class="btn btn-ghost">Cancelar</a>
        <button class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar cambios</button>
      </div>
    </form>
  </div>
</div>
