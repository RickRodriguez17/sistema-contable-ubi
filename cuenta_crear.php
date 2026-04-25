<?php
/**
 * ContaUBI — Crear cuenta del plan de cuentas
 *
 * Validaciones:
 *  - Clase 1-5, Grupo 0-9, Cuenta 0-99, Subcuenta 0-99, Auxiliar 0-99
 *  - Código (8 dígitos) único
 *  - Nombre único dentro del mismo nivel (clase, grupo, cuenta, subcuenta)
 *  - Naturaleza obligatoria (DEUDORA/ACREEDORA)
 *  - Si la cuenta es imputable, el código no puede terminar con grupos en ceros
 *    (debe representar un nivel hoja).
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Nueva Cuenta';
$pageIcon   = 'bi-plus-circle';
$activePage = 'cuentas';

$error = '';
$old = [
    'clase'=>'1','grupo'=>'1','cuenta'=>'1','subcuenta'=>'1','auxiliar'=>'0',
    'nombre'=>'','descripcion'=>'','naturaleza'=>'DEUDORA','es_imputable'=>'1','activa'=>'1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k=>$_) { $old[$k] = trim($_POST[$k] ?? $old[$k]); }

    $clase     = $old['clase'];
    $grupo     = $old['grupo'];
    $cuenta    = $old['cuenta'];
    $subcta    = $old['subcuenta'];
    $aux       = $old['auxiliar'];

    if (!preg_match('/^[1-5]$/', $clase))             $error = 'Clase debe ser 1..5.';
    elseif (!preg_match('/^[0-9]$/', $grupo))         $error = 'Grupo debe ser 0..9.';
    elseif (!preg_match('/^([0-9]|[1-9][0-9])$/', $cuenta))    $error = 'Cuenta 0..99.';
    elseif (!preg_match('/^([0-9]|[1-9][0-9])$/', $subcta))    $error = 'Subcuenta 0..99.';
    elseif (!preg_match('/^([0-9]|[1-9][0-9])$/', $aux))       $error = 'Auxiliar 0..99.';
    elseif ($old['nombre'] === '')                    $error = 'El nombre es obligatorio.';
    elseif (mb_strlen($old['nombre']) > 120)          $error = 'El nombre supera los 120 caracteres.';
    elseif (!in_array($old['naturaleza'], ['DEUDORA','ACREEDORA'], true))
                                                       $error = 'Naturaleza inválida.';
    else {
        $codigo = armar_codigo((int)$clase,(int)$grupo,(int)$cuenta,(int)$subcta,(int)$aux);

        /* Verificar que el nombre no se repita dentro del mismo nivel */
        $stmt = $conn->prepare("SELECT id FROM cuentas
                                WHERE clase=? AND grupo=? AND cuenta=? AND subcuenta=?
                                  AND LOWER(nombre)=LOWER(?) LIMIT 1");
        $stmt->bind_param('iiiis', $clase, $grupo, $cuenta, $subcta, $old['nombre']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Ya existe una cuenta con el nombre \"{$old['nombre']}\" en el nivel {$clase}.{$grupo}.{$cuenta}.{$subcta}.";
        } else {
            $imp = $old['es_imputable'] === '1' ? 1 : 0;
            $act = $old['activa']        === '1' ? 1 : 0;

            $stmt = $conn->prepare("INSERT INTO cuentas
                (codigo, clase, grupo, cuenta, subcuenta, auxiliar, nombre, descripcion, naturaleza, es_imputable, activa)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('siiiiisssii',
                $codigo, $clase, $grupo, $cuenta, $subcta, $aux,
                $old['nombre'], $old['descripcion'], $old['naturaleza'], $imp, $act);

            try {
                $stmt->execute();
                flash_set("Cuenta {$codigo} creada.", 'success');
                header('Location: cuentas.php'); exit;
            } catch (mysqli_sql_exception $e) {
                if ($conn->errno === 1062) {
                    $error = "Ya existe otra cuenta con código {$codigo}.";
                } else {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
}

/* Cargar el catálogo para mostrar en vivo a qué clase/grupo/cuenta/subcuenta
 * pertenece cada dígito que va digitando el usuario. */
$catalogo = [];
$rs = $conn->query("SELECT codigo, clase, grupo, cuenta, subcuenta, auxiliar, nombre, es_imputable
                    FROM cuentas ORDER BY codigo");
while ($r = $rs->fetch_assoc()) {
    $catalogo[] = [
        'codigo'      => $r['codigo'],
        'clase'       => (int)$r['clase'],
        'grupo'       => (int)$r['grupo'],
        'cuenta'      => (int)$r['cuenta'],
        'subcuenta'   => (int)$r['subcuenta'],
        'auxiliar'    => (int)$r['auxiliar'],
        'nombre'      => $r['nombre'],
        'imputable'   => (int)$r['es_imputable'],
    ];
}

include __DIR__ . '/layout_top.php';
?>

<a href="cuentas.php" class="btn btn-ghost btn-sm" style="margin-bottom:1rem"><i class="bi bi-arrow-left"></i> Volver</a>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<div class="card anim" style="max-width:760px">
  <div class="card-header"><i class="bi bi-plus-circle"></i> Datos de la nueva cuenta</div>
  <div class="card-body">

    <div style="background:rgba(20,184,106,.07);border:1px dashed rgba(20,184,106,.35);border-radius:10px;padding:.85rem 1rem;margin-bottom:.75rem;display:flex;align-items:center;gap:1rem">
      <div>
        <div class="form-label" style="margin:0">Código generado</div>
        <div id="codigoPrev" class="num" style="font-size:1.55rem;color:var(--accent-2);font-weight:700;letter-spacing:.05em">— — — — — — — —</div>
      </div>
      <div class="text-muted" style="font-size:.78rem;margin-left:auto;text-align:right;line-height:1.3">
        Estructura: <strong>G S CC SS AA</strong><br>
        clase · grupo · cuenta · subcuenta · auxiliar
      </div>
    </div>

    <div id="rutaJerarquia" style="background:rgba(200,166,72,.08);border:1px solid rgba(200,166,72,.3);border-radius:10px;padding:.6rem .9rem;margin-bottom:1.25rem;font-size:.86rem;line-height:1.55;display:none">
      <i class="bi bi-diagram-3" style="color:var(--gold,#c8a648)"></i>
      <span class="text-muted">Pertenece a:</span>
      <span id="rutaTexto"></span>
    </div>

    <form method="POST" id="frmCuenta">
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Clase contable</label>
          <select name="clase" id="f_clase" class="form-control" required onchange="actualizar()">
            <?php foreach ([1=>'ACTIVO',2=>'PASIVO',3=>'PATRIMONIO',4=>'INGRESOS',5=>'EGRESOS'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $old['clase']==(string)$k?'selected':'' ?>><?= $k ?> · <?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-hint"><span class="hintNivel" id="hint_clase" style="color:var(--accent-2,#0d8a4f);font-weight:600">—</span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Naturaleza</label>
          <select name="naturaleza" class="form-control" required>
            <option value="DEUDORA"  <?= $old['naturaleza']==='DEUDORA' ?'selected':'' ?>>DEUDORA — saldo en el debe</option>
            <option value="ACREEDORA"<?= $old['naturaleza']==='ACREEDORA'?'selected':'' ?>>ACREEDORA — saldo en el haber</option>
          </select>
        </div>
      </div>

      <div class="form-grid form-grid-3">
        <?php
        $campos = [
            ['grupo',     'Grupo',      '0-9',   '[0-9]',         1],
            ['cuenta',    'Cuenta',     '0-99',  '[0-9]{1,2}',    2],
            ['subcuenta', 'Subcuenta',  '0-99',  '[0-9]{1,2}',    2],
        ];
        foreach ($campos as $c): list($name,$lbl,$hint,$pat,$max) = $c; ?>
          <div class="form-group">
            <label class="form-label"><?= $lbl ?></label>
            <input type="text" inputmode="numeric" name="<?= $name ?>" id="f_<?= $name ?>"
                   class="form-control" maxlength="<?= $max ?>"
                   pattern="<?= $pat ?>" required value="<?= h($old[$name]) ?>"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,<?= $max ?>); actualizar()">
            <div class="form-hint"><span class="rangoTxt"><?= $hint ?></span> · <span class="hintNivel" id="hint_<?= $name ?>" style="color:var(--accent-2,#0d8a4f);font-weight:600">—</span></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label class="form-label">Auxiliar (00 = mayor sin auxiliar)</label>
          <input type="text" inputmode="numeric" name="auxiliar" id="f_auxiliar"
                 class="form-control" maxlength="2" pattern="[0-9]{1,2}"
                 value="<?= h($old['auxiliar']) ?>"
                 oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,2); actualizar()">
          <div class="form-hint"><span class="rangoTxt">0-99</span> · <span class="hintNivel" id="hint_auxiliar" style="color:var(--accent-2,#0d8a4f);font-weight:600">—</span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Tipo de cuenta</label>
          <select name="es_imputable" class="form-control">
            <option value="1" <?= $old['es_imputable']==='1'?'selected':'' ?>>Imputable (acepta movimientos)</option>
            <option value="0" <?= $old['es_imputable']==='0'?'selected':'' ?>>Cabecera / agrupación</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Nombre de la cuenta</label>
        <input type="text" name="nombre" class="form-control" maxlength="120" required
               placeholder="Ej: Caja Moneda Nacional" value="<?= h($old['nombre']) ?>">
        <div class="form-hint">Máx 120 caracteres. No se permiten nombres duplicados en el mismo nivel.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control form-textarea" placeholder="Opcional"><?= h($old['descripcion']) ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Estado</label>
        <select name="activa" class="form-control">
          <option value="1" <?= $old['activa']==='1'?'selected':'' ?>>Activa</option>
          <option value="0" <?= $old['activa']==='0'?'selected':'' ?>>Inactiva (oculta en listas de movimiento)</option>
        </select>
      </div>

      <button class="btn btn-primary btn-block"><i class="bi bi-check-lg"></i> Guardar Cuenta</button>
    </form>
  </div>
</div>

<script>
const CATALOGO = <?= json_encode($catalogo, JSON_UNESCAPED_UNICODE) ?>;
const NOMBRES_CLASE = {1:'ACTIVO',2:'PASIVO',3:'PATRIMONIO',4:'INGRESOS',5:'EGRESOS'};

function pad(s,n){ s=String(s); while(s.length<n)s='0'+s; return s; }

/* Busca el nombre del nodo padre en el catálogo dado el prefijo de niveles.
 * level = 'grupo' | 'cuenta' | 'subcuenta'
 * Devuelve {nombre, codigo, exact} o null si no hay coincidencia. */
function buscarNivel(level, c, g, ct, sc) {
  // 1) Intenta encontrar el "header" exacto del nivel.
  let exacto = null;
  if (level === 'grupo') {
    exacto = CATALOGO.find(x => x.clase===c && x.grupo===g && x.cuenta===0 && x.subcuenta===0 && x.auxiliar===0);
  } else if (level === 'cuenta') {
    exacto = CATALOGO.find(x => x.clase===c && x.grupo===g && x.cuenta===ct && x.subcuenta===0 && x.auxiliar===0)
          || CATALOGO.find(x => x.clase===c && x.grupo===g && x.cuenta===ct && x.auxiliar===0);
  } else if (level === 'subcuenta') {
    exacto = CATALOGO.find(x => x.clase===c && x.grupo===g && x.cuenta===ct && x.subcuenta===sc && x.auxiliar===0);
  }
  if (exacto) return { nombre: exacto.nombre, codigo: exacto.codigo, exact: true };

  // 2) Si no, devuelve cualquier descendiente que comparta el prefijo (para
  //    que el usuario sepa "ya hay X cuentas en este nivel").
  let candidato = null;
  if (level === 'grupo') {
    candidato = CATALOGO.find(x => x.clase===c && x.grupo===g);
  } else if (level === 'cuenta') {
    candidato = CATALOGO.find(x => x.clase===c && x.grupo===g && x.cuenta===ct);
  } else if (level === 'subcuenta') {
    candidato = CATALOGO.find(x => x.clase===c && x.grupo===g && x.cuenta===ct && x.subcuenta===sc);
  }
  if (candidato) return { nombre: candidato.nombre, codigo: candidato.codigo, exact: false };
  return null;
}

function pintar(id, valor, color) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = valor;
  el.style.color = color || 'var(--accent-2,#0d8a4f)';
}

function actualizar(){
  const c  = parseInt(document.getElementById('f_clase').value, 10);
  const gS = document.getElementById('f_grupo').value;
  const cS = document.getElementById('f_cuenta').value;
  const sS = document.getElementById('f_subcuenta').value;
  const aS = document.getElementById('f_auxiliar').value;
  const g  = gS === '' ? 0 : parseInt(gS,10);
  const ct = cS === '' ? 0 : parseInt(cS,10);
  const sc = sS === '' ? 0 : parseInt(sS,10);
  const ax = aS === '' ? 0 : parseInt(aS,10);

  // Código generado
  document.getElementById('codigoPrev').textContent =
    c + pad(g,1) + pad(ct,2) + pad(sc,2) + pad(ax,2);

  // Hint de Clase (siempre disponible)
  pintar('hint_clase', NOMBRES_CLASE[c] || '—', 'var(--accent-2,#0d8a4f)');

  // Hint del Grupo
  if (gS === '') {
    pintar('hint_grupo', 'pendiente', 'var(--text-muted,#8e98a8)');
  } else {
    const r = buscarNivel('grupo', c, g);
    if (r)      pintar('hint_grupo', r.nombre, r.exact ? 'var(--accent-2,#0d8a4f)' : 'var(--gold,#c8a648)');
    else        pintar('hint_grupo', '(grupo nuevo)', 'var(--gold,#c8a648)');
  }

  // Hint de la Cuenta
  if (gS === '' || cS === '') {
    pintar('hint_cuenta', 'pendiente', 'var(--text-muted,#8e98a8)');
  } else if (ct === 0) {
    pintar('hint_cuenta', 'sin cuenta', 'var(--text-muted,#8e98a8)');
  } else {
    const r = buscarNivel('cuenta', c, g, ct);
    if (r)      pintar('hint_cuenta', r.nombre, r.exact ? 'var(--accent-2,#0d8a4f)' : 'var(--gold,#c8a648)');
    else        pintar('hint_cuenta', '(cuenta nueva)', 'var(--gold,#c8a648)');
  }

  // Hint de la Subcuenta
  if (gS === '' || cS === '' || sS === '') {
    pintar('hint_subcuenta', 'pendiente', 'var(--text-muted,#8e98a8)');
  } else if (sc === 0) {
    pintar('hint_subcuenta', 'sin subcuenta', 'var(--text-muted,#8e98a8)');
  } else {
    const r = buscarNivel('subcuenta', c, g, ct, sc);
    if (r)      pintar('hint_subcuenta', r.nombre, r.exact ? 'var(--accent-2,#0d8a4f)' : 'var(--gold,#c8a648)');
    else        pintar('hint_subcuenta', '(subcuenta nueva)', 'var(--gold,#c8a648)');
  }

  // Hint del Auxiliar: chequea si el código completo ya existe
  if (aS === '') {
    pintar('hint_auxiliar', 'pendiente', 'var(--text-muted,#8e98a8)');
  } else {
    const codigo = c + pad(g,1) + pad(ct,2) + pad(sc,2) + pad(ax,2);
    const existe = CATALOGO.find(x => x.codigo === codigo);
    if (existe) pintar('hint_auxiliar', '⚠ código en uso: ' + existe.nombre, '#e85a5a');
    else if (ax === 0) pintar('hint_auxiliar', 'sin auxiliar', 'var(--text-muted,#8e98a8)');
    else pintar('hint_auxiliar', 'libre', 'var(--accent-2,#0d8a4f)');
  }

  // Ruta jerárquica (breadcrumb)
  const partes = [];
  if (!isNaN(c) && NOMBRES_CLASE[c]) partes.push(`<strong>${c}</strong> · ${NOMBRES_CLASE[c]}`);
  if (gS !== '' && g >= 0) {
    const r = buscarNivel('grupo', c, g);
    partes.push(`<strong>${g}</strong> · ${r ? r.nombre : '(grupo nuevo)'}`);
  }
  if (cS !== '' && ct > 0) {
    const r = buscarNivel('cuenta', c, g, ct);
    partes.push(`<strong>${pad(ct,2)}</strong> · ${r ? r.nombre : '(cuenta nueva)'}`);
  }
  if (sS !== '' && sc > 0) {
    const r = buscarNivel('subcuenta', c, g, ct, sc);
    partes.push(`<strong>${pad(sc,2)}</strong> · ${r ? r.nombre : '(subcuenta nueva)'}`);
  }
  const ruta = document.getElementById('rutaJerarquia');
  if (partes.length > 0) {
    document.getElementById('rutaTexto').innerHTML = ' ' + partes.join(' <span style="color:var(--gold,#c8a648)">›</span> ');
    ruta.style.display = 'block';
  } else {
    ruta.style.display = 'none';
  }
}
actualizar();
</script>

<?php include __DIR__ . '/layout_bottom.php'; ?>
