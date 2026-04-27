<?php
/**
 * ContaUBI — Crear Cuenta Analítica (CA, nivel 5)
 *
 * El PUCT (niveles 1-4) es cerrado por el SIN: el contribuyente sólo crea
 * Cuentas Analíticas (CA) bajo una Cuenta Principal existente.
 *
 *   Estructura: C·G·SG·CP·CA = 8 dígitos
 *   - El usuario elige una Cuenta Principal padre (nivel 4)
 *   - Digita un código CA de 1-2 dígitos (1-99)
 *   - Da nombre a la nueva analítica
 *   - Hereda la naturaleza del padre (puede invertirla si es contra-cuenta)
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Nueva Cuenta Analítica';
$pageIcon   = 'bi-plus-circle';
$activePage = 'cuentas';

$error = '';
$old = [
    'parent_id'        => '',
    'cuenta_analitica' => '',
    'nombre'           => '',
    'descripcion'      => '',
    'naturaleza'       => '',
    'activa'           => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) { $old[$k] = trim((string)($_POST[$k] ?? $old[$k])); }

    $parent_id = (int)$old['parent_id'];
    $ca        = $old['cuenta_analitica'];

    if ($parent_id <= 0) {
        $error = 'Debe seleccionar la Cuenta Principal padre.';
    } elseif (!preg_match('/^[0-9]{1,2}$/', $ca) || (int)$ca < 1 || (int)$ca > 99) {
        $error = 'La Cuenta Analítica debe ser un número entre 1 y 99.';
    } elseif ($old['nombre'] === '') {
        $error = 'El nombre de la cuenta es obligatorio.';
    } elseif (mb_strlen($old['nombre']) > 160) {
        $error = 'El nombre supera los 160 caracteres.';
    } elseif (!in_array($old['naturaleza'], ['DEUDORA','ACREEDORA'], true)) {
        $error = 'Naturaleza inválida.';
    } else {
        /* Carga el padre y verifica que sea nivel 4 (Cuenta Principal) */
        $stmt = $conn->prepare("SELECT clase, grupo, subgrupo, cuenta_principal, nivel, naturaleza, nombre
                                FROM cuentas WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $parent_id);
        $stmt->execute();
        $padre = $stmt->get_result()->fetch_assoc();

        if (!$padre || (int)$padre['nivel'] !== 4) {
            $error = 'La cuenta padre seleccionada no es una Cuenta Principal válida.';
        } else {
            $codigo = armar_codigo(
                (int)$padre['clase'],
                (int)$padre['grupo'],
                (int)$padre['subgrupo'],
                (int)$padre['cuenta_principal'],
                (int)$ca
            );

            /* Verificar duplicado de nombre dentro del mismo CP */
            $stmt = $conn->prepare("SELECT id FROM cuentas
                                    WHERE clase=? AND grupo=? AND subgrupo=? AND cuenta_principal=?
                                      AND LOWER(nombre)=LOWER(?) LIMIT 1");
            $stmt->bind_param('iiiis',
                $padre['clase'], $padre['grupo'], $padre['subgrupo'], $padre['cuenta_principal'],
                $old['nombre']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Ya existe una cuenta con el nombre \"{$old['nombre']}\" bajo {$padre['nombre']}.";
            } else {
                $act = $old['activa'] === '1' ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO cuentas
                    (codigo, clase, grupo, subgrupo, cuenta_principal, cuenta_analitica,
                     nivel, nombre, descripcion, naturaleza, es_imputable, es_puct, activa)
                    VALUES (?, ?, ?, ?, ?, ?, 5, ?, ?, ?, 1, 0, ?)");
                $stmt->bind_param('siiiiisssi',
                    $codigo, $padre['clase'], $padre['grupo'], $padre['subgrupo'],
                    $padre['cuenta_principal'], $ca,
                    $old['nombre'], $old['descripcion'], $old['naturaleza'], $act);
                try {
                    $stmt->execute();
                    flash_set("Cuenta analítica $codigo creada correctamente.", 'success');
                    header('Location: cuentas.php'); exit;
                } catch (mysqli_sql_exception $e) {
                    if ($conn->errno === 1062) {
                        $error = "Ya existe una cuenta con código $codigo en el sistema.";
                    } else {
                        $error = 'Error al guardar: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

/* Lista de Cuentas Principales (nivel 4) para el selector de padre */
$cps = $conn->query("SELECT id, codigo, nombre, clase, grupo, subgrupo, cuenta_principal, naturaleza
                     FROM cuentas WHERE nivel=4 ORDER BY codigo")->fetch_all(MYSQLI_ASSOC);

/* Mapa: id -> ruta jerárquica completa para el preview */
$rutas = [];
foreach ($cps as $cp) {
    /* nombres de clase/grupo/subgrupo */
    $rclase = $conn->query("SELECT nombre FROM cuentas WHERE codigo='".intval($cp['clase'])."0000000' LIMIT 1")
                   ->fetch_assoc();
    $rgrupo = $conn->query(sprintf(
        "SELECT nombre FROM cuentas WHERE clase=%d AND grupo=%d AND nivel=2 LIMIT 1",
        $cp['clase'], $cp['grupo']))->fetch_assoc();
    $rsg = $conn->query(sprintf(
        "SELECT nombre FROM cuentas WHERE clase=%d AND grupo=%d AND subgrupo=%d AND nivel=3 LIMIT 1",
        $cp['clase'], $cp['grupo'], $cp['subgrupo']))->fetch_assoc();
    $rutas[$cp['id']] = [
        'clase'    => $rclase['nombre'] ?? '',
        'grupo'    => $rgrupo['nombre'] ?? '',
        'subgrupo' => $rsg['nombre']    ?? '',
        'cp'       => $cp['nombre'],
        'codigo_base' => $cp['codigo'], // 8 dígitos terminando en 00
        'naturaleza'  => $cp['naturaleza'],
    ];
}

include __DIR__ . '/layout_top.php';
?>

<a href="cuentas.php" class="btn btn-ghost btn-sm" style="margin-bottom:1rem">
  <i class="bi bi-arrow-left"></i> Volver al Plan de Cuentas
</a>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<div class="card anim" style="max-width:880px">
  <div class="card-header"><i class="bi bi-plus-circle"></i> Nueva Cuenta Analítica</div>
  <div class="card-body">

    <div class="alert alert-info" style="margin-bottom:1.25rem">
      <i class="bi bi-info-circle"></i>
      <div>
        <strong>Sólo se pueden crear Cuentas Analíticas (5° nivel).</strong>
        Los niveles superiores del PUCT (Clase, Grupo, Subgrupo, Cuenta Principal)
        están cerrados por el SIN y no se modifican.
      </div>
    </div>

    <div class="codigo-preview">
      <div>
        <div class="form-label" style="margin:0">Código generado</div>
        <div id="codigoPrev" class="num codigo-num">— — — — — — — —</div>
      </div>
      <div class="codigo-meta">
        Estructura: <strong>C·G·SG·CP·CA</strong><br>
        clase · grupo · subgrupo · cta. principal · cta. analítica
      </div>
    </div>

    <div id="rutaJerarquia" class="ruta-jerarquia" style="display:none">
      <i class="bi bi-diagram-3"></i>
      <span class="text-muted">Pertenece a:</span>
      <span id="rutaTexto"></span>
    </div>

    <form method="POST" id="frmCuenta">
      <div class="form-group">
        <label class="form-label">Cuenta Principal padre (nivel 4)</label>
        <select name="parent_id" id="f_parent" class="form-control" required onchange="actualizar()">
          <option value="">— Seleccione la Cuenta Principal —</option>
          <?php foreach ($cps as $cp): ?>
            <option value="<?= (int)$cp['id'] ?>"
                    data-naturaleza="<?= h($cp['naturaleza']) ?>"
                    data-codigo="<?= h($cp['codigo']) ?>"
                    <?= (string)$old['parent_id']===(string)$cp['id']?'selected':'' ?>>
              <?= h($cp['codigo']) ?> — <?= h($cp['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">464 cuentas principales del PUCT disponibles.</div>
      </div>

      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Cuenta Analítica (código 1-99)</label>
          <input type="text" inputmode="numeric" name="cuenta_analitica" id="f_ca"
                 class="form-control" maxlength="2" pattern="[0-9]{1,2}" required
                 value="<?= h($old['cuenta_analitica']) ?>"
                 placeholder="Ej: 01"
                 oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,2); actualizar()">
          <div class="form-hint">2 dígitos · 01-99</div>
        </div>
        <div class="form-group">
          <label class="form-label">Naturaleza</label>
          <select name="naturaleza" id="f_naturaleza" class="form-control" required>
            <option value="">— Heredada del padre —</option>
            <option value="DEUDORA"  <?= $old['naturaleza']==='DEUDORA' ?'selected':'' ?>>DEUDORA — saldo en el debe</option>
            <option value="ACREEDORA"<?= $old['naturaleza']==='ACREEDORA'?'selected':'' ?>>ACREEDORA — saldo en el haber</option>
          </select>
          <div class="form-hint">Por defecto se hereda del padre. Cámbialo sólo en contra-cuentas.</div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Nombre de la cuenta</label>
        <input type="text" name="nombre" class="form-control" maxlength="160" required
               placeholder="Ej: Caja Moneda Nacional"
               value="<?= h($old['nombre']) ?>">
        <div class="form-hint">Máx 160 caracteres. No se permiten nombres duplicados bajo la misma Cuenta Principal.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Descripción (opcional)</label>
        <textarea name="descripcion" class="form-control form-textarea"
                  placeholder="Detalle del uso de la cuenta…"><?= h($old['descripcion']) ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Estado</label>
        <select name="activa" class="form-control">
          <option value="1" <?= $old['activa']==='1'?'selected':'' ?>>Activa</option>
          <option value="0" <?= $old['activa']==='0'?'selected':'' ?>>Inactiva</option>
        </select>
      </div>

      <div class="form-actions">
        <a href="cuentas.php" class="btn btn-ghost">Cancelar</a>
        <button class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar Cuenta</button>
      </div>
    </form>
  </div>
</div>

<script>
const RUTAS = <?= json_encode($rutas, JSON_UNESCAPED_UNICODE) ?>;

function actualizar() {
  const sel  = document.getElementById('f_parent');
  const opt  = sel.options[sel.selectedIndex];
  const ca   = document.getElementById('f_ca').value || '';
  const nat  = document.getElementById('f_naturaleza');
  const prev = document.getElementById('codigoPrev');
  const ruta = document.getElementById('rutaJerarquia');
  const rTxt = document.getElementById('rutaTexto');

  if (!sel.value || !RUTAS[sel.value]) {
    prev.textContent = '— — — — — — — —';
    ruta.style.display = 'none';
    return;
  }

  const r = RUTAS[sel.value];
  const base = r.codigo_base.substring(0, 6); // sin los 2 dígitos finales
  const caPad = ca.padStart(2, '0');
  prev.textContent = (base + caPad).split('').join(' ');

  rTxt.innerHTML =
      `<span class="badge-clase">${r.clase}</span>`
    + ` <i class="bi bi-chevron-right"></i> <strong>${r.grupo}</strong>`
    + ` <i class="bi bi-chevron-right"></i> <strong>${r.subgrupo}</strong>`
    + ` <i class="bi bi-chevron-right"></i> <strong>${r.cp}</strong>`;
  ruta.style.display = 'flex';

  /* Sugerir naturaleza heredada si no eligió */
  if (!nat.value) {
    nat.value = opt.dataset.naturaleza || '';
  }
}

document.addEventListener('DOMContentLoaded', actualizar);
</script>
