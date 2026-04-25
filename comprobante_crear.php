<?php
/**
 * ContaUBI — Crear comprobante (asiento contable) con partida doble
 *
 * Restricciones:
 *  - Solo se pueden seleccionar cuentas IMPUTABLES y ACTIVAS
 *  - En cada línea, sólo uno entre debe/haber puede ser > 0
 *  - Total debe = Total haber (partida doble)
 *  - Se requieren al menos 2 líneas con monto > 0
 *  - La fecha debe estar dentro del ejercicio activo (configurable en empresa)
 *  - Se guarda como BORRADOR; aprobar/anular es una operación separada
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

$pageTitle  = 'Nuevo Asiento';
$pageIcon   = 'bi-plus-circle';
$activePage = 'comprobante_crear';

$cuentas = cuentas_imputables($conn);

$error = '';
$old = [
    'fecha'  => date('Y-m-d'),
    'tipo'   => 'TRASPASO',
    'glosa'  => '',
    'lineas' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['fecha'] = $_POST['fecha'] ?? date('Y-m-d');
    $old['tipo']  = $_POST['tipo']  ?? 'TRASPASO';
    $old['glosa'] = trim($_POST['glosa'] ?? '');

    $cuentaIds = $_POST['cuenta_id'] ?? [];
    $debes     = $_POST['debe']      ?? [];
    $haberes   = $_POST['haber']     ?? [];
    $glosas    = $_POST['glosa_linea'] ?? [];

    $lineas = [];
    $totalD = 0.0; $totalH = 0.0;
    for ($i=0; $i<count($cuentaIds); $i++) {
        $cid = (int)$cuentaIds[$i];
        $d   = (float)str_replace(',', '', $debes[$i]   ?? 0);
        $h   = (float)str_replace(',', '', $haberes[$i] ?? 0);
        $g   = trim($glosas[$i] ?? '');
        if ($cid <= 0 && $d == 0 && $h == 0) continue;
        $lineas[] = ['cuenta_id'=>$cid,'debe'=>$d,'haber'=>$h,'glosa_linea'=>$g];
        $totalD += $d; $totalH += $h;
    }
    $old['lineas'] = $lineas;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['fecha']))         $error = 'Fecha inválida.';
    elseif ($old['fecha'] < $EMPRESA['fecha_inicio_ejercicio'] ||
            $old['fecha'] > $EMPRESA['fecha_cierre_ejercicio'])       $error = 'La fecha está fuera del ejercicio ' . $EMPRESA['ejercicio'] . '.';
    elseif (!in_array($old['tipo'], ['INGRESO','EGRESO','TRASPASO','APERTURA','CIERRE','AJUSTE'], true))
                                                                     $error = 'Tipo de comprobante inválido.';
    elseif ($old['glosa'] === '')                                    $error = 'La glosa es obligatoria.';
    elseif (mb_strlen($old['glosa']) > 255)                          $error = 'La glosa supera los 255 caracteres.';
    elseif (count($lineas) < 2)                                      $error = 'Se requieren al menos 2 líneas con monto.';
    elseif (!comprobante_cuadra($totalD, $totalH))                   $error = 'El asiento no cuadra: Debe ' . money($totalD) . ' ≠ Haber ' . money($totalH) . '.';
    else {
        /* Validar cada línea */
        $idsValidos = array_column($cuentas, 'id');
        foreach ($lineas as $i => $l) {
            $n = $i + 1;
            if (!in_array($l['cuenta_id'], $idsValidos)) { $error = "Línea $n: cuenta inválida o no imputable."; break; }
            if ($l['debe']  < 0 || $l['haber'] < 0)      { $error = "Línea $n: montos negativos no permitidos."; break; }
            if ($l['debe']  > 0 && $l['haber'] > 0)      { $error = "Línea $n: no puede tener debe y haber a la vez."; break; }
            if ($l['debe'] == 0 && $l['haber'] == 0)     { $error = "Línea $n: debe tener monto en debe o haber."; break; }
        }
    }

    if (!$error) {
        $numero = siguiente_numero_comprobante($conn, (int)$EMPRESA['ejercicio']);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO comprobantes
                (numero, tipo, fecha, glosa, moneda, estado, total_debe, total_haber)
                VALUES (?, ?, ?, ?, ?, 'BORRADOR', ?, ?)");
            $stmt->bind_param('sssssdd',
                $numero, $old['tipo'], $old['fecha'], $old['glosa'], $EMPRESA['moneda'], $totalD, $totalH);
            $stmt->execute();
            $cid = $conn->insert_id;

            $stmtL = $conn->prepare("INSERT INTO movimientos
                (comprobante_id, cuenta_id, debe, haber, glosa_linea, orden)
                VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($lineas as $i => $l) {
                $orden = $i + 1;
                $stmtL->bind_param('iiddsi',
                    $cid, $l['cuenta_id'], $l['debe'], $l['haber'], $l['glosa_linea'], $orden);
                $stmtL->execute();
            }
            $conn->commit();
            flash_set("Comprobante {$numero} creado en estado BORRADOR.", 'success');
            header('Location: comprobante_ver.php?id=' . $cid); exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

if (empty($old['lineas'])) {
    /* placeholders por defecto */
    $old['lineas'] = [['cuenta_id'=>0,'debe'=>0,'haber'=>0,'glosa_linea'=>''],
                      ['cuenta_id'=>0,'debe'=>0,'haber'=>0,'glosa_linea'=>'']];
}

include __DIR__ . '/layout_top.php';
?>

<a href="comprobantes.php" class="btn btn-ghost btn-sm" style="margin-bottom:1rem"><i class="bi bi-arrow-left"></i> Volver</a>

<?php if ($error): ?>
<div class="alert alert-danger anim"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<form method="POST" id="frmCompr">
<div class="card anim">
  <div class="card-header"><i class="bi bi-receipt"></i> Cabecera del asiento</div>
  <div class="card-body">
    <div class="form-grid form-grid-3">
      <div class="form-group">
        <label class="form-label">Fecha</label>
        <input type="date" name="fecha" class="form-control" required
               min="<?= h($EMPRESA['fecha_inicio_ejercicio']) ?>"
               max="<?= h($EMPRESA['fecha_cierre_ejercicio']) ?>"
               value="<?= h($old['fecha']) ?>">
        <div class="form-hint">Ejercicio: <?= h($EMPRESA['fecha_inicio_ejercicio']) ?> a <?= h($EMPRESA['fecha_cierre_ejercicio']) ?></div>
      </div>
      <div class="form-group">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-control">
          <?php foreach (['INGRESO','EGRESO','TRASPASO','APERTURA','CIERRE','AJUSTE'] as $t): ?>
            <option value="<?= $t ?>" <?= $old['tipo']===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Moneda</label>
        <input type="text" class="form-control" value="<?= h($EMPRESA['moneda']) ?>" disabled>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Glosa (descripción del asiento)</label>
      <input type="text" name="glosa" class="form-control" maxlength="255" required
             placeholder="Ej: Compra de mercadería al contado según factura N° 123"
             value="<?= h($old['glosa']) ?>">
    </div>
  </div>
</div>

<div class="card anim">
  <div class="card-header">
    <span><i class="bi bi-list-ol"></i> Movimientos (partida doble)</span>
    <button type="button" class="btn btn-sm" onclick="agregarLinea()"><i class="bi bi-plus"></i> Agregar línea</button>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="table" id="tblLineas">
        <thead>
          <tr>
            <th style="width:40px">#</th>
            <th>Cuenta</th>
            <th style="width:30%">Glosa línea</th>
            <th class="text-right" style="width:140px">Debe</th>
            <th class="text-right" style="width:140px">Haber</th>
            <th style="width:50px"></th>
          </tr>
        </thead>
        <tbody id="bodyLineas">
          <?php foreach ($old['lineas'] as $i => $ln): ?>
          <tr>
            <td class="num text-muted">#<?= $i+1 ?></td>
            <td>
              <select name="cuenta_id[]" class="form-control" required>
                <option value="">— seleccionar —</option>
                <?php foreach ($cuentas as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= ((int)$ln['cuenta_id'])===(int)$c['id']?'selected':'' ?>>
                    <?= h($c['codigo']) ?> · <?= h($c['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="text" name="glosa_linea[]" class="form-control" maxlength="255" value="<?= h($ln['glosa_linea']) ?>"></td>
            <td><input type="number" step="0.01" min="0" name="debe[]"  class="form-control text-right num" value="<?= $ln['debe']>0?h(number_format($ln['debe'],2,'.','')):'' ?>" oninput="actualizarTotales();sincronDebe(this)"></td>
            <td><input type="number" step="0.01" min="0" name="haber[]" class="form-control text-right num" value="<?= $ln['haber']>0?h(number_format($ln['haber'],2,'.','')):'' ?>" oninput="actualizarTotales();sincronHaber(this)"></td>
            <td class="text-center"><button type="button" class="btn btn-ghost btn-sm" onclick="quitarLinea(this)"><i class="bi bi-x-lg" style="color:var(--danger)"></i></button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="text-right">TOTALES</td>
            <td class="text-right num" id="totDebe">0.00</td>
            <td class="text-right num" id="totHaber">0.00</td>
            <td></td>
          </tr>
          <tr>
            <td colspan="3" class="text-right">DIFERENCIA</td>
            <td colspan="2" class="text-right num" id="diff">0.00</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<div style="display:flex;gap:.5rem;justify-content:flex-end">
  <a href="comprobantes.php" class="btn">Cancelar</a>
  <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar Asiento</button>
</div>

</form>

<template id="tplLinea">
  <tr>
    <td class="num text-muted">#</td>
    <td>
      <select name="cuenta_id[]" class="form-control" required>
        <option value="">— seleccionar —</option>
        <?php foreach ($cuentas as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= h($c['codigo']) ?> · <?= h($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="text" name="glosa_linea[]" class="form-control" maxlength="255"></td>
    <td><input type="number" step="0.01" min="0" name="debe[]"  class="form-control text-right num" oninput="actualizarTotales();sincronDebe(this)"></td>
    <td><input type="number" step="0.01" min="0" name="haber[]" class="form-control text-right num" oninput="actualizarTotales();sincronHaber(this)"></td>
    <td class="text-center"><button type="button" class="btn btn-ghost btn-sm" onclick="quitarLinea(this)"><i class="bi bi-x-lg" style="color:var(--danger)"></i></button></td>
  </tr>
</template>

<script>
function agregarLinea(){
  const tpl = document.getElementById('tplLinea').content.cloneNode(true);
  document.getElementById('bodyLineas').appendChild(tpl);
  renumerar();
}
function quitarLinea(btn){
  const tr = btn.closest('tr');
  if (document.querySelectorAll('#bodyLineas tr').length <= 2) return;
  tr.remove(); renumerar(); actualizarTotales();
}
function renumerar(){
  document.querySelectorAll('#bodyLineas tr').forEach((tr,i)=>{
    tr.children[0].textContent = '#' + (i+1);
  });
}
function actualizarTotales(){
  let td=0, th=0;
  document.querySelectorAll('input[name="debe[]"]').forEach(i => td += parseFloat(i.value||0));
  document.querySelectorAll('input[name="haber[]"]').forEach(i => th += parseFloat(i.value||0));
  document.getElementById('totDebe').textContent  = td.toFixed(2);
  document.getElementById('totHaber').textContent = th.toFixed(2);
  const diff = (td - th);
  const el = document.getElementById('diff');
  el.textContent = diff.toFixed(2);
  el.classList.toggle('cuadre-ok',  Math.abs(diff) < 0.005 && td > 0);
  el.classList.toggle('cuadre-mal', Math.abs(diff) >= 0.005);
}
function sincronDebe(inp){
  if (parseFloat(inp.value||0) > 0) {
    const haber = inp.closest('tr').querySelector('input[name="haber[]"]');
    haber.value = '';
  }
  actualizarTotales();
}
function sincronHaber(inp){
  if (parseFloat(inp.value||0) > 0) {
    const debe = inp.closest('tr').querySelector('input[name="debe[]"]');
    debe.value = '';
  }
  actualizarTotales();
}
actualizarTotales();
</script>

<?php include __DIR__ . '/layout_bottom.php'; ?>
