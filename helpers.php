<?php
/**
 * ContaUBI — helpers comunes
 */
declare(strict_types=1);

/** Escape para HTML. */
function h($s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formato de número con separadores y 2 decimales. Siempre valor absoluto.
 *  Los montos contables (debe/haber/saldos) se exhiben sin signo negativo.
 */
function money($n): string {
    return number_format(abs((float)$n), 2, '.', ',');
}

/** Formato de fecha YYYY-MM-DD a DD/MM/YYYY. */
function fecha_es(?string $f): string {
    if (!$f) return '';
    $t = strtotime($f);
    return $t ? date('d/m/Y', $t) : (string)$f;
}

/** Etiqueta de la clase contable (1..5). */
function nombre_clase(int $clase): string {
    return [1=>'ACTIVO',2=>'PASIVO',3=>'PATRIMONIO',4=>'INGRESOS',5=>'EGRESOS'][$clase] ?? (string)$clase;
}

/** Slug CSS del badge según clase. */
function clase_badge(int $clase): string {
    return [1=>'badge-activo',2=>'badge-pasivo',3=>'badge-patrimonio',4=>'badge-ingresos',5=>'badge-egresos'][$clase]
           ?? 'badge-default';
}

/** Etiqueta de estado de comprobante. */
function estado_badge(string $e): string {
    return [
        'BORRADOR' => 'estado-borrador',
        'APROBADO' => 'estado-aprobado',
        'ANULADO'  => 'estado-anulado',
    ][$e] ?? 'estado-borrador';
}

/** Genera el código de 8 dígitos del PUCT.
 *  Estructura: C(1) G(1) SG(2) CP(2) CA(2)
 *    C  = Clase             (1-5)
 *    G  = Grupo             (1 dígito)
 *    SG = Subgrupo          (2 dígitos)
 *    CP = Cuenta Principal  (2 dígitos)
 *    CA = Cuenta Analítica  (2 dígitos)
 */
function armar_codigo(int $clase, int $grupo, int $subgrupo, int $cuenta_principal, int $cuenta_analitica): string {
    return sprintf('%01d%01d%02d%02d%02d', $clase, $grupo, $subgrupo, $cuenta_principal, $cuenta_analitica);
}

/** Etiqueta del nivel jerárquico del PUCT. */
function nombre_nivel(int $nivel): string {
    return [
        1 => 'Clase',
        2 => 'Grupo',
        3 => 'Subgrupo',
        4 => 'Cuenta Principal',
        5 => 'Cuenta Analítica',
    ][$nivel] ?? '—';
}

/** Devuelve el siguiente número de comprobante para el ejercicio (formato: 2026-000123). */
function siguiente_numero_comprobante(mysqli $conn, int $ejercicio): string {
    $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(numero,'-',-1) AS UNSIGNED)) AS max
                            FROM comprobantes WHERE numero LIKE ?");
    $like = $ejercicio . '-%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $next = (int)($row['max'] ?? 0) + 1;
    return sprintf('%04d-%06d', $ejercicio, $next);
}

/** Asegura que un comprobante cumpla con la partida doble (debe = haber). */
function comprobante_cuadra(float $debe, float $haber): bool {
    return abs($debe - $haber) < 0.005;
}

/** Devuelve la lista de cuentas IMPUTABLES para usar en formularios. */
function cuentas_imputables(mysqli $conn, bool $solo_activas = true): array {
    $where = $solo_activas ? "WHERE es_imputable = 1 AND activa = 1" : "WHERE es_imputable = 1";
    $rs = $conn->query("SELECT id, codigo, nombre, naturaleza, clase FROM cuentas $where ORDER BY codigo");
    return $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
}

/** Mensaje flash simple basado en cookie efímera. */
function flash_set(string $msg, string $tipo = 'success'): void {
    setcookie('contaubi_flash', json_encode(['msg'=>$msg,'tipo'=>$tipo]), time()+10, '/');
}
function flash_get(): ?array {
    if (empty($_COOKIE['contaubi_flash'])) return null;
    $d = json_decode($_COOKIE['contaubi_flash'], true);
    setcookie('contaubi_flash','',time()-1,'/');
    return is_array($d) ? $d : null;
}

/* =====================================================================
 * Multimoneda y tipos de cambio
 * ===================================================================== */

/** Catálogo de monedas soportadas. Etiqueta visible + símbolo. */
function monedas_disponibles(): array {
    return [
        'BOB' => ['simbolo' => 'Bs.', 'nombre' => 'Bolivianos'],
        'USD' => ['simbolo' => '$us', 'nombre' => 'Dólares estadounidenses'],
        'UFV' => ['simbolo' => 'UFV', 'nombre' => 'Unidad de Fomento a la Vivienda'],
    ];
}

/** Símbolo de una moneda (BOB → Bs.) */
function moneda_simbolo(string $cod): string {
    $m = monedas_disponibles();
    return $m[$cod]['simbolo'] ?? $cod;
}

/** Formato de monto con símbolo de moneda. */
function money_m($n, string $moneda = 'BOB'): string {
    return moneda_simbolo($moneda) . ' ' . number_format(abs((float)$n), 2, '.', ',');
}

/**
 * Tipo de cambio vigente para una fecha dada.
 * Devuelve [id, fecha, tasa_usd, ufv] o null si la tabla está vacía.
 * Si no hay un registro para la fecha exacta, toma el más reciente anterior.
 */
function tipo_cambio_vigente(mysqli $conn, string $fecha = ''): ?array {
    if ($fecha === '') $fecha = date('Y-m-d');
    $stmt = $conn->prepare("SELECT id, fecha, tasa_usd, ufv
                            FROM tipos_cambio
                            WHERE fecha <= ?
                            ORDER BY fecha DESC, id DESC
                            LIMIT 1");
    $stmt->bind_param('s', $fecha);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ?: null;
}

