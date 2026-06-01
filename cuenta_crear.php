<?php
/**
 * ContaUBI — Crear Cuenta (cualquier nivel 1-5)
 *
 * Permite crear cuentas propias (es_puct=0) desde el Nivel 1 (Clase)
 * hasta el Nivel 5 (Cuenta Analítica). La jerarquía PUCT oficial
 * (es_puct=1) sigue siendo inmutable.
 *
 *   Estructura: C·G·SG·CP·CA = 8 dígitos
 *     C  Clase             1 dígito  (1-9)
 *     G  Grupo             1 dígito  (1-9)
 *     SG Subgrupo          2 dígitos (1-99)
 *     CP Cuenta Principal  2 dígitos (1-99)
 *     CA Cuenta Analítica  2 dígitos (1-99)
 *
 * Sólo el nivel 5 (CA) es imputable (acepta movimientos).
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
auth_require('cuentas.gestionar');

$pageTitle  = 'Nueva Cuenta';
$pageIcon   = 'bi-plus-circle';
$activePage = 'cuentas';

/* Longitudes máximas centralizadas (también en BD: VARCHAR(160) / VARCHAR(500)). */
const LIM_NOMBRE_CUENTA = 160;
const LIM_DESC_CUENTA   = 500;

$error = '';
$old = [
    'nivel'       => '5',
    'parent_id'   => '',
    'clase'       => '',
    'digito'      => '',
    'nombre'      => '',
    'descripcion' => '',
    'naturaleza'  => '',
    'activa'      => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) { $old[$k] = trim((string)($_POST[$k] ?? $old[$k])); }

    $nivel     = (int)$old['nivel'];
    $parent_id = (int)$old['parent_id'];
    $digito    = $old['digito'];

    /* Validaciones de campos básicos */
    if ($nivel < 1 || $nivel > 5) {
        $error = 'Nivel inválido (debe ser 1 a 5).';
    } elseif ($old['nombre'] === '') {
        $error = 'El nombre de la cuenta es obligatorio.';
    } elseif (mb_strlen($old['nombre']) > LIM_NOMBRE_CUENTA) {
        $error = 'El nombre supera los ' . LIM_NOMBRE_CUENTA . ' caracteres.';
    } elseif (mb_strlen($old['descripcion']) > LIM_DESC_CUENTA) {
        $error = 'La descripción supera los ' . LIM_DESC_CUENTA . ' caracteres.';
    } elseif (!in_array($old['naturaleza'], ['DEUDORA','ACREEDORA'], true)) {
        $error = 'Naturaleza inválida.';
    } else {
        /* Reglas de código según nivel */
        $clase = 0; $grupo = 0; $subgrupo = 0; $cuenta_principal = 0; $cuenta_analitica = 0;
        $padre = null;

        if ($nivel === 1) {
            /* Clase: 1 dígito 1-9, sin padre */
            if (!preg_match('/^[1-9]$/', $old['clase'])) {
                $error = 'La Clase debe ser un número del 1 al 9.';
            } else {
                $clase = (int)$old['clase'];
            }
        } else {
            /* Niveles 2-5: requieren padre */
            if ($parent_id <= 0) {
                $error = 'Debe seleccionar la cuenta padre.';
            } else {
                $stmt = $conn->prepare("SELECT clase, grupo, subgrupo, cuenta_principal, cuenta_analitica,
                                               nivel, naturaleza, nombre, es_imputable
                                        FROM cuentas WHERE id=? LIMIT 1");
                $stmt->bind_param('i', $parent_id);
                $stmt->execute();
                $padre = $stmt->get_result()->fetch_assoc();
                if (!$padre || (int)$padre['nivel'] !== ($nivel - 1)) {
                    $error = 'La cuenta padre seleccionada no corresponde al nivel ' . ($nivel - 1) . '.';
                } else {
                    /* La cuenta padre no puede ser imputable (de nivel 5) si vamos a colgar más bajo */
                    /* Reglas de dígitos por nivel */
                    if ($nivel === 2 && !preg_match('/^[1-9]$/', $digito)) {
                        $error = 'El dígito de Grupo debe ser 1-9.';
                    } elseif (in_array($nivel, [3,4,5], true) && !preg_match('/^[0-9]{1,2}$/', $digito)) {
                        $error = 'El dígito de este nivel debe ser un número de 1 a 99.';
                    } elseif (in_array($nivel, [3,4,5], true) && ((int)$digito < 1 || (int)$digito > 99)) {
                        $error = 'El dígito debe estar entre 1 y 99.';
                    } else {
                        $clase            = (int)$padre['clase'];
                        $grupo            = (int)$padre['grupo'];
                        $subgrupo         = (int)$padre['subgrupo'];
                        $cuenta_principal = (int)$padre['cuenta_principal'];
                        $cuenta_analitica = (int)$padre['cuenta_analitica'];

                        if      ($nivel === 2) $grupo            = (int)$digito;
                        elseif  ($nivel === 3) $subgrupo         = (int)$digito;
                        elseif  ($nivel === 4) $cuenta_principal = (int)$digito;
                        elseif  ($nivel === 5) $cuenta_analitica = (int)$digito;
                    }
                }
            }
        }

        if (!$error) {
            $codigo = armar_codigo($clase, $grupo, $subgrupo, $cuenta_principal, $cuenta_analitica);

            /* Validación duplicado de código (también respaldada por UNIQUE en BD). */
            $stmt = $conn->prepare("SELECT id, nombre FROM cuentas WHERE codigo=? LIMIT 1");
            $stmt->bind_param('s', $codigo);
            $stmt->execute();
            $dup = $stmt->get_result()->fetch_assoc();
            if ($dup) {
                $error = "El código $codigo ya está registrado (" . $dup['nombre'] . ").";
            } else {
                /* Duplicado de nombre dentro del mismo grupo padre */
                $stmt = $conn->prepare("SELECT id FROM cuentas
                                        WHERE clase=? AND grupo=? AND subgrupo=? AND cuenta_principal=?
                                          AND LOWER(nombre)=LOWER(?) LIMIT 1");
                $stmt->bind_param('iiiis',
                    $clase, $grupo, $subgrupo, $cuenta_principal, $old['nombre']);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "Ya existe una cuenta con el nombre \"{$old['nombre']}\" bajo la misma jerarquía.";
                } else {
                    /* Insert */
                    $es_imputable = ($nivel === 5) ? 1 : 0;
                    $es_puct      = 0;            /* siempre cuenta propia */
                    $activa       = $old['activa'] === '1' ? 1 : 0;

                    $stmt = $conn->prepare("INSERT INTO cuentas
                        (codigo, clase, grupo, subgrupo, cuenta_principal, cuenta_analitica,
                         nivel, nombre, descripcion, naturaleza, es_imputable, es_puct, activa)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
                    $stmt->bind_param('siiiiiisssii',
                        $codigo, $clase, $grupo, $subgrupo, $cuenta_principal, $cuenta_analitica,
                        $nivel, $old['nombre'], $old['descripcion'], $old['naturaleza'],
                        $es_imputable, $activa);
                    try {
                        $stmt->execute();
                        flash_set("Cuenta {$codigo} — " . nombre_nivel($nivel) . " creada correctamente.", 'success');
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
}

/* Cargamos TODAS las cuentas (para poblar los selectores de padre por nivel). */
$todas = $conn->query("SELECT id, codigo, nombre, clase, grupo, subgrupo, cuenta_principal, cuenta_analitica,
                              nivel, naturaleza
                       FROM cuentas
                       ORDER BY codigo")->fetch_all(MYSQLI_ASSOC);

/* Particionamos por nivel para los <option> dependientes del JS. */
$porNivel = [1=>[], 2=>[], 3=>[], 4=>[], 5=>[]];
foreach ($todas as $c) { $porNivel[(int)$c['nivel']][] = $c; }

include __DIR__ . '/layout_top.php';
?>

<a href="cuentas.php" class="btn btn-ghost btn-sm" style="margin-bottom:1rem">
  <i class="bi bi-arrow-left"></i> Volver al Plan de Cuentas
</a>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<div class="card anim" style="max-width:920px">
  <div class="card-header"><i class="bi bi-plus-circle"></i> Nueva Cuenta</div>
  <div class="card-body">

    <div class="alert alert-info" style="margin-bottom:1.25rem">
      <i class="bi bi-info-circle"></i>
      <div>
        Podés crear cuentas <strong>propias</strong> desde el Nivel 1 (Clase) hasta el Nivel 5
        (Cuenta Analítica). Sólo el Nivel 5 acepta movimientos contables (imputable).
      </div>
    </div>

    <div class="codigo-preview">
      <div>
        <div class="form-label" style="margin:0">Código generado</div>
        <div id="codigoPrev" class="num codigo-num">— — — — — — — —</div>
        <div id="codigoEstado" class="form-hint" style="margin-top:.25rem"></div>
      </div>
      <div class="codigo-meta">
        Estructura: <strong>C·G·SG·CP·CA</strong><br>
        clase · grupo · subgrupo · cta. principal · cta. analítica
      </div>
    </div>

    <form method="POST" id="frmCuenta" autocomplete="off">
      <input type="hidden" name="nivel" id="f_nivel_hidden" value="<?= h($old['nivel']) ?>">

      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Nivel a crear</label>
          <select id="f_nivel" class="form-control" required onchange="cambiarNivel()">
            <?php foreach ([1=>'Clase',2=>'Grupo',3=>'Subgrupo',4=>'Cuenta Principal',5=>'Cuenta Analítica'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= (string)$old['nivel']===(string)$k?'selected':'' ?>><?= $k ?> · <?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-hint">Sólo el nivel 5 admite movimientos.</div>
        </div>

        <div class="form-group" id="grpNaturaleza">
          <label class="form-label">Naturaleza</label>
          <select name="naturaleza" id="f_naturaleza" class="form-control" required>
            <option value="">— Seleccionar —</option>
            <option value="DEUDORA"  <?= $old['naturaleza']==='DEUDORA' ?'selected':'' ?>>DEUDORA — saldo en el debe</option>
            <option value="ACREEDORA"<?= $old['naturaleza']==='ACREEDORA'?'selected':'' ?>>ACREEDORA — saldo en el haber</option>
          </select>
          <div class="form-hint">En cuentas hijas se hereda del padre (podés sobreescribir).</div>
        </div>
      </div>

      <!-- Bloque NIVEL 1: clase suelta -->
      <div class="form-grid form-grid-2" id="bloqueClase" style="display:none">
        <div class="form-group">
          <label class="form-label">Código de Clase (1-9)</label>
          <input type="text" inputmode="numeric" name="clase" id="f_clase"
                 class="form-control" maxlength="1" pattern="[1-9]"
                 value="<?= h($old['clase']) ?>"
                 placeholder="Ej: 6"
                 oninput="this.value=this.value.replace(/[^1-9]/g,'').slice(0,1); actualizar()">
          <div class="form-hint">1 dígito · 1-9 (las clases 1-5 son PUCT oficial).</div>
        </div>
      </div>

      <!-- Bloque NIVELES 2-5: padre + dígito -->
      <div id="bloqueHijo" style="display:none">
        <div class="form-group">
          <label class="form-label" id="lblPadre">Cuenta padre</label>
          <select name="parent_id" id="f_parent" class="form-control" onchange="actualizar()">
            <option value="">— Seleccione la cuenta padre —</option>
          </select>
          <div class="form-hint" id="hintPadre"></div>
        </div>
        <div class="form-grid form-grid-2">
          <div class="form-group">
            <label class="form-label" id="lblDigito">Dígito del nivel</label>
            <input type="text" inputmode="numeric" name="digito" id="f_digito"
                   class="form-control" maxlength="2"
                   value="<?= h($old['digito']) ?>"
                   placeholder="Ej: 01"
                   oninput="filtrarDigito(this);actualizar()">
            <div class="form-hint" id="hintDigito">1 a 99</div>
          </div>
        </div>
      </div>

      <div id="rutaJerarquia" class="ruta-jerarquia" style="display:none">
        <i class="bi bi-diagram-3"></i>
        <span class="text-muted">Pertenece a:</span>
        <span id="rutaTexto"></span>
      </div>

      <div class="form-group">
        <label class="form-label">Nombre de la cuenta <span class="text-muted">(máx <?= LIM_NOMBRE_CUENTA ?>)</span></label>
        <input type="text" name="nombre" id="f_nombre" class="form-control"
               maxlength="<?= LIM_NOMBRE_CUENTA ?>" required
               placeholder="Ej: Caja Moneda Nacional"
               value="<?= h($old['nombre']) ?>"
               oninput="contarChars('f_nombre','contNombre',<?= LIM_NOMBRE_CUENTA ?>)">
        <div class="form-hint">
          <span id="contNombre">0</span> / <?= LIM_NOMBRE_CUENTA ?> caracteres ·
          No se permiten nombres duplicados bajo la misma jerarquía.
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Descripción <span class="text-muted">(opcional, máx <?= LIM_DESC_CUENTA ?>)</span></label>
        <textarea name="descripcion" id="f_descripcion" class="form-control form-textarea"
                  maxlength="<?= LIM_DESC_CUENTA ?>"
                  placeholder="Detalle del uso de la cuenta…"
                  oninput="contarChars('f_descripcion','contDesc',<?= LIM_DESC_CUENTA ?>)"><?= h($old['descripcion']) ?></textarea>
        <div class="form-hint"><span id="contDesc">0</span> / <?= LIM_DESC_CUENTA ?> caracteres.</div>
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
        <button type="submit" id="btnGuardar" class="btn btn-primary">
          <i class="bi bi-check-circle"></i> Guardar Cuenta
        </button>
      </div>
    </form>
  </div>
</div>

<script>
/* Cuentas particionadas por nivel para el <select> de padre. */
const CUENTAS_POR_NIVEL = <?= json_encode($porNivel, JSON_UNESCAPED_UNICODE) ?>;
let codigoOcupadoTimer = null;
let codigoOcupado = false;

function cambiarNivel() {
  const nivel = parseInt(document.getElementById('f_nivel').value || '5', 10);
  document.getElementById('f_nivel_hidden').value = nivel;

  const padre = (nivel >= 2);
  document.getElementById('bloqueClase').style.display = (nivel === 1 ? 'grid' : 'none');
  document.getElementById('bloqueHijo').style.display  = (padre ? 'block' : 'none');
  document.getElementById('f_clase').required = (nivel === 1);
  document.getElementById('f_parent').required = padre;
  document.getElementById('f_digito').required = padre;

  if (padre) {
    /* Llenamos el <select> de padre con los del nivel previo. */
    const sel = document.getElementById('f_parent');
    const prev = nivel - 1;
    sel.innerHTML = '<option value="">— Seleccione la cuenta padre (nivel ' + prev + ') —</option>';
    (CUENTAS_POR_NIVEL[prev] || []).forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.codigo + ' — ' + c.nombre;
      opt.dataset.naturaleza = c.naturaleza;
      sel.appendChild(opt);
    });
    /* Hints según nivel */
    const labelsPadre = {2:'Cuenta padre (Clase, nivel 1)', 3:'Cuenta padre (Grupo, nivel 2)',
                         4:'Cuenta padre (Subgrupo, nivel 3)', 5:'Cuenta padre (Cuenta Principal, nivel 4)'};
    const hintsDigito = {2:'1 dígito · 1-9 (grupo)',
                         3:'2 dígitos · 1-99 (subgrupo)',
                         4:'2 dígitos · 1-99 (cuenta principal)',
                         5:'2 dígitos · 1-99 (cuenta analítica)'};
    document.getElementById('lblPadre').textContent = labelsPadre[nivel];
    document.getElementById('hintDigito').textContent = hintsDigito[nivel];
    document.getElementById('lblDigito').textContent = ({2:'Dígito de Grupo',3:'Dígito de Subgrupo',
                                                          4:'Dígito de Cuenta Principal',
                                                          5:'Dígito de Cuenta Analítica'})[nivel];
    document.getElementById('f_digito').maxLength = (nivel === 2 ? 1 : 2);
  }
  actualizar();
}

function filtrarDigito(inp) {
  const nivel = parseInt(document.getElementById('f_nivel').value || '5', 10);
  let v = (inp.value || '').replace(/[^0-9]/g, '');
  if (nivel === 2) v = v.replace(/^0+/, '').slice(0,1);
  else             v = v.slice(0,2);
  inp.value = v;
}

function actualizar() {
  const nivel = parseInt(document.getElementById('f_nivel').value || '5', 10);
  let clase=0, grupo=0, subgrupo=0, cp=0, ca=0;
  let rutaParts = [];

  if (nivel === 1) {
    clase = parseInt(document.getElementById('f_clase').value || '0', 10);
  } else {
    const sel = document.getElementById('f_parent');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
      const padre = (CUENTAS_POR_NIVEL[nivel-1] || []).find(c => String(c.id) === String(opt.value));
      if (padre) {
        clase    = parseInt(padre.clase, 10);
        grupo    = parseInt(padre.grupo, 10);
        subgrupo = parseInt(padre.subgrupo, 10);
        cp       = parseInt(padre.cuenta_principal, 10);
        ca       = parseInt(padre.cuenta_analitica, 10);
        rutaParts.push(padre.codigo + ' ' + padre.nombre);
        /* heredar naturaleza si no se eligió otra */
        const natSel = document.getElementById('f_naturaleza');
        if (!natSel.value) natSel.value = padre.naturaleza;
      }
    }
    const d = parseInt(document.getElementById('f_digito').value || '0', 10);
    if      (nivel === 2) grupo    = d;
    else if (nivel === 3) subgrupo = d;
    else if (nivel === 4) cp       = d;
    else if (nivel === 5) ca       = d;
  }

  const codigo = String(clase) + String(grupo) + String(subgrupo).padStart(2,'0')
               + String(cp).padStart(2,'0') + String(ca).padStart(2,'0');
  document.getElementById('codigoPrev').textContent = codigo.replace(/(.)(.)(..)(..)(..)/, '$1·$2·$3·$4·$5');

  const rj = document.getElementById('rutaJerarquia');
  const rt = document.getElementById('rutaTexto');
  if (rutaParts.length > 0) {
    rj.style.display = 'flex';
    rt.textContent = rutaParts.join(' › ');
  } else {
    rj.style.display = 'none';
  }

  verificarCodigo(codigo);
}

/* Verificación AJAX de código duplicado en tiempo real. */
function verificarCodigo(codigo) {
  const est = document.getElementById('codigoEstado');
  const prev = document.getElementById('codigoPrev');
  const btn = document.getElementById('btnGuardar');

  if (!/^[1-9][0-9]{7}$/.test(codigo)) {
    est.textContent = '';
    est.className = 'form-hint';
    prev.style.color = '';
    btn.disabled = false;
    codigoOcupado = false;
    return;
  }

  clearTimeout(codigoOcupadoTimer);
  codigoOcupadoTimer = setTimeout(() => {
    fetch('cuenta_check_codigo.php?codigo=' + encodeURIComponent(codigo))
      .then(r => r.json())
      .then(j => {
        if (j.exists) {
          est.innerHTML = '<i class="bi bi-x-circle-fill" style="color:var(--danger)"></i> ' +
                          'Código ya registrado: <strong>' + j.codigo + '</strong> — ' + j.nombre;
          est.style.color = 'var(--danger)';
          prev.style.color = 'var(--danger)';
          btn.disabled = true;
          codigoOcupado = true;
        } else {
          est.innerHTML = '<i class="bi bi-check-circle-fill" style="color:var(--ok)"></i> Código disponible';
          est.style.color = 'var(--ok)';
          prev.style.color = 'var(--ok)';
          btn.disabled = false;
          codigoOcupado = false;
        }
      })
      .catch(() => { est.textContent = ''; btn.disabled = false; codigoOcupado = false; });
  }, 200);
}

function contarChars(idCampo, idCont, max) {
  const v = document.getElementById(idCampo).value || '';
  const el = document.getElementById(idCont);
  el.textContent = v.length;
  el.style.color = (v.length > max ? 'var(--danger)' : (v.length >= max*0.85 ? 'var(--warn)' : ''));
}

document.getElementById('frmCuenta').addEventListener('submit', (e) => {
  if (codigoOcupado) {
    e.preventDefault();
    alert('No se puede registrar: el código ya existe.');
  }
});

cambiarNivel();
contarChars('f_nombre','contNombre',<?= LIM_NOMBRE_CUENTA ?>);
contarChars('f_descripcion','contDesc',<?= LIM_DESC_CUENTA ?>);
</script>

<?php include __DIR__ . '/layout_bottom.php'; ?>
