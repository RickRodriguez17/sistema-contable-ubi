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

include __DIR__ . '/layout_top.php';
?>

<a href="cuentas.php" class="btn btn-ghost btn-sm" style="margin-bottom:1rem"><i class="bi bi-arrow-left"></i> Volver</a>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<div class="card anim" style="max-width:760px">
  <div class="card-header"><i class="bi bi-plus-circle"></i> Datos de la nueva cuenta</div>
  <div class="card-body">

    <div style="background:rgba(20,184,106,.07);border:1px dashed rgba(20,184,106,.35);border-radius:10px;padding:.85rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:1rem">
      <div>
        <div class="form-label" style="margin:0">Código generado</div>
        <div id="codigoPrev" class="num" style="font-size:1.55rem;color:var(--accent-2);font-weight:700;letter-spacing:.05em">— — — — — — — —</div>
      </div>
      <div class="text-muted" style="font-size:.78rem;margin-left:auto;text-align:right;line-height:1.3">
        Estructura: <strong>G S CC SS AA</strong><br>
        clase · grupo · cuenta · subcuenta · auxiliar
      </div>
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
            <div class="form-hint"><?= $hint ?></div>
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
          <div class="form-hint">0-99</div>
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
function pad(s,n){ s=String(s); while(s.length<n)s='0'+s; return s; }
function actualizar(){
  const c = document.getElementById('f_clase').value;
  const g = document.getElementById('f_grupo').value || '0';
  const ct= document.getElementById('f_cuenta').value || '0';
  const sc= document.getElementById('f_subcuenta').value || '0';
  const ax= document.getElementById('f_auxiliar').value || '0';
  document.getElementById('codigoPrev').textContent =
    c + g + pad(ct,2) + pad(sc,2) + pad(ax,2);
}
actualizar();
</script>

<?php include __DIR__ . '/layout_bottom.php'; ?>
