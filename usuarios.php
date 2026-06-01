<?php
/**
 * ContaUBI — Gestión de usuarios (sólo rol admin).
 *
 * Funciones:
 *   - Listar usuarios.
 *   - Crear usuario (nombre, usuario, password, rol, estado).
 *   - Editar usuario (nombre, rol, estado, opcionalmente password).
 *   - Activar / desactivar usuario (no se eliminan físicamente).
 *
 * El admin no puede desactivarse a sí mismo ni quitarse el rol admin.
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
auth_require('usuarios.gestionar');

$pageTitle  = 'Usuarios';
$pageIcon   = 'bi-people';
$activePage = 'usuarios';

$ROLES   = ['admin', 'contador', 'consulta'];
$ESTADOS = ['activo', 'inactivo'];

$me = auth_user();

/* ---------------------------------------------------------------
 * Acciones POST
 * --------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre   = trim($_POST['nombre']   ?? '');
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = (string)($_POST['password'] ?? '');
        $rol      = $_POST['rol']    ?? 'consulta';
        $estado   = $_POST['estado'] ?? 'activo';

        if ($nombre === '' || $usuario === '' || $password === '') {
            flash_set('Nombre, usuario y contraseña son obligatorios.', 'danger');
        } elseif (!in_array($rol, $ROLES, true) || !in_array($estado, $ESTADOS, true)) {
            flash_set('Rol o estado inválido.', 'danger');
        } elseif (mb_strlen($password) < 4) {
            flash_set('La contraseña debe tener al menos 4 caracteres.', 'danger');
        } else {
            $existe = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $existe->bind_param('s', $usuario);
            $existe->execute();
            if ($existe->get_result()->fetch_assoc()) {
                flash_set("Ya existe un usuario con el nombre '{$usuario}'.", 'danger');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre, usuario, password, rol, estado)
                                        VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('sssss', $nombre, $usuario, $hash, $rol, $estado);
                $stmt->execute();
                flash_set("Usuario '{$usuario}' creado.", 'success');
            }
        }
        header('Location: usuarios.php'); exit;
    }

    if ($accion === 'editar') {
        $id       = (int)($_POST['id'] ?? 0);
        $nombre   = trim($_POST['nombre'] ?? '');
        $rol      = $_POST['rol']    ?? 'consulta';
        $estado   = $_POST['estado'] ?? 'activo';
        $password = (string)($_POST['password'] ?? '');

        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            flash_set('Usuario no encontrado.', 'danger');
        } elseif ($nombre === '' || !in_array($rol, $ROLES, true) || !in_array($estado, $ESTADOS, true)) {
            flash_set('Datos inválidos.', 'danger');
        } else {
            // El admin no puede quitarse a sí mismo el rol admin ni desactivarse.
            if ((int)$row['id'] === (int)$me['id']) {
                $rol    = 'admin';
                $estado = 'activo';
            }

            if ($password !== '') {
                if (mb_strlen($password) < 4) {
                    flash_set('La contraseña debe tener al menos 4 caracteres.', 'danger');
                    header('Location: usuarios.php?id=' . $id); exit;
                }
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, rol=?, estado=?, password=? WHERE id=?");
                $stmt->bind_param('ssssi', $nombre, $rol, $estado, $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, rol=?, estado=? WHERE id=?");
                $stmt->bind_param('sssi', $nombre, $rol, $estado, $id);
            }
            $stmt->execute();

            // Si editó su propio perfil, refrescar la sesión.
            if ((int)$row['id'] === (int)$me['id']) {
                $_SESSION['usuario']['nombre'] = $nombre;
            }

            flash_set("Usuario '{$row['usuario']}' actualizado.", 'success');
        }
        header('Location: usuarios.php'); exit;
    }

    if ($accion === 'toggle_estado') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$me['id']) {
            flash_set('No podés desactivarte a vos mismo.', 'warn');
        } else {
            $row = $conn->query("SELECT usuario, estado FROM usuarios WHERE id=$id")->fetch_assoc();
            if ($row) {
                $nuevo = $row['estado'] === 'activo' ? 'inactivo' : 'activo';
                $stmt = $conn->prepare("UPDATE usuarios SET estado=? WHERE id=?");
                $stmt->bind_param('si', $nuevo, $id);
                $stmt->execute();
                flash_set("Usuario '{$row['usuario']}' marcado como {$nuevo}.", 'info');
            }
        }
        header('Location: usuarios.php'); exit;
    }
}

/* ---------------------------------------------------------------
 * Datos para la vista
 * --------------------------------------------------------------*/
$usuarios = $conn->query("SELECT id, nombre, usuario, rol, estado, creado_en
                          FROM usuarios
                          ORDER BY rol, usuario")->fetch_all(MYSQLI_ASSOC);

$editId   = (int)($_GET['id'] ?? 0);
$editar   = null;
if ($editId > 0) {
    $stmt = $conn->prepare("SELECT id, nombre, usuario, rol, estado FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editar = $stmt->get_result()->fetch_assoc() ?: null;
}

include __DIR__ . '/layout_top.php';
?>

<div class="anim">

  <div class="card">
    <div class="card-header">
      <span><i class="bi bi-person-plus"></i>
        <?= $editar ? 'Editar usuario: ' . h($editar['usuario']) : 'Nuevo usuario' ?>
      </span>
      <?php if ($editar): ?>
        <a href="usuarios.php" class="btn btn-ghost btn-sm">
          <i class="bi bi-x-circle"></i> Cancelar
        </a>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
        <?php if ($editar): ?>
          <input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
        <?php endif; ?>

        <div class="form-grid form-grid-2">
          <div class="form-group">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" maxlength="120" required
                   value="<?= h($editar['nombre'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Usuario (login)</label>
            <input type="text" name="usuario" class="form-control" maxlength="50" required
                   pattern="[A-Za-z0-9_\.]+"
                   value="<?= h($editar['usuario'] ?? '') ?>"
                   <?= $editar ? 'readonly' : '' ?>>
            <div class="form-hint">Sólo letras, números, "_" y ".". No se puede cambiar luego.</div>
          </div>
        </div>

        <div class="form-grid form-grid-3">
          <div class="form-group">
            <label class="form-label">Rol</label>
            <select name="rol" class="form-control" required>
              <?php foreach ($ROLES as $r):
                $sel = ($editar && $editar['rol'] === $r) ? 'selected' : ''; ?>
                <option value="<?= $r ?>" <?= $sel ?>><?= strtoupper($r) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-control" required>
              <?php foreach ($ESTADOS as $e):
                $sel = ($editar && $editar['estado'] === $e)
                       ? 'selected'
                       : ((!$editar && $e === 'activo') ? 'selected' : ''); ?>
                <option value="<?= $e ?>" <?= $sel ?>><?= strtoupper($e) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">
              <?= $editar ? 'Nueva contraseña (opcional)' : 'Contraseña' ?>
            </label>
            <input type="password" name="password" class="form-control" maxlength="100"
                   <?= $editar ? '' : 'required' ?>
                   placeholder="<?= $editar ? 'Dejar vacío para no cambiar' : 'Mínimo 4 caracteres' ?>">
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle"></i>
            <?= $editar ? 'Guardar cambios' : 'Crear usuario' ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span><i class="bi bi-people"></i> Usuarios registrados</span>
      <span class="text-muted"><?= count($usuarios) ?> total</span>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Usuario</th>
              <th>Rol</th>
              <th>Estado</th>
              <th>Creado</th>
              <th class="text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($usuarios as $u): $soyYo = ((int)$u['id'] === (int)$me['id']); ?>
            <tr>
              <td><?= h($u['nombre']) ?> <?= $soyYo ? '<span class="chip">vos</span>' : '' ?></td>
              <td class="num"><?= h($u['usuario']) ?></td>
              <td>
                <span class="rol-chip rol-<?= h($u['rol']) ?>"><?= strtoupper(h($u['rol'])) ?></span>
              </td>
              <td>
                <span class="<?= $u['estado']==='activo' ? 'estado-aprobado' : 'estado-anulado' ?>">
                  <?= strtoupper(h($u['estado'])) ?>
                </span>
              </td>
              <td class="text-muted"><?= fecha_es($u['creado_en']) ?></td>
              <td class="text-center" style="white-space:nowrap">
                <a class="btn btn-ghost btn-sm" title="Editar"
                   href="usuarios.php?id=<?= (int)$u['id'] ?>">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (!$soyYo): ?>
                  <form method="POST" style="display:inline"
                        onsubmit="return confirm('¿Cambiar el estado de <?= h($u['usuario']) ?>?')">
                    <input type="hidden" name="accion" value="toggle_estado">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-ghost btn-sm"
                            title="<?= $u['estado']==='activo' ? 'Desactivar' : 'Activar' ?>">
                      <i class="bi bi-<?= $u['estado']==='activo' ? 'toggle-on' : 'toggle-off' ?>"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
