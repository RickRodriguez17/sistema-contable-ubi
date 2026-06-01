<?php
/**
 * ContaUBI — Eliminar cuenta (sólo POST, con verificación de movimientos e hijas)
 *
 * Reglas de integridad:
 *  - Cuentas PUCT oficiales (es_puct=1) NUNCA se pueden eliminar.
 *  - Una cuenta con movimientos contables NO se puede eliminar.
 *  - Una cuenta con cuentas hijas (subcuentas) NO se puede eliminar
 *    hasta que se eliminen primero todas sus hijas.
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
auth_require('cuentas.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: cuentas.php'); exit; }

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { flash_set('ID inválido.','danger'); header('Location: cuentas.php'); exit; }

$stmt = $conn->prepare("SELECT codigo, nivel, es_puct, clase, grupo, subgrupo, cuenta_principal, cuenta_analitica
                        FROM cuentas WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { flash_set('Cuenta no encontrada.','danger'); header('Location: cuentas.php'); exit; }

if ((int)$row['es_puct'] === 1) {
    flash_set("La cuenta {$row['codigo']} pertenece al PUCT oficial y no puede eliminarse.", 'danger');
    header('Location: cuentas.php'); exit;
}

/* ¿Tiene movimientos? */
$stmt = $conn->prepare("SELECT COUNT(*) c FROM movimientos WHERE cuenta_id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$cnt = (int)$stmt->get_result()->fetch_assoc()['c'];
if ($cnt > 0) {
    flash_set("No se puede eliminar la cuenta {$row['codigo']}: tiene $cnt movimiento(s) registrados.", 'danger');
    header('Location: cuentas.php'); exit;
}

/* ¿Tiene cuentas hijas? (mismas raíces, nivel mayor) */
$nivel = (int)$row['nivel'];
if ($nivel < 5) {
    /* Buscamos descendientes según el nivel actual. */
    $where = ['nivel > ?'];
    $types = 'i';
    $params = [$nivel];
    /* La rama queda fijada por las columnas hasta el nivel actual. */
    $cols = ['clase','grupo','subgrupo','cuenta_principal','cuenta_analitica'];
    for ($i = 0; $i < $nivel; $i++) {
        $where[] = $cols[$i] . ' = ?';
        $types  .= 'i';
        $params[] = (int)$row[$cols[$i]];
    }
    $sql = "SELECT COUNT(*) c FROM cuentas WHERE " . implode(' AND ', $where);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $hijas = (int)$stmt->get_result()->fetch_assoc()['c'];
    if ($hijas > 0) {
        flash_set("No se puede eliminar {$row['codigo']}: tiene $hijas cuenta(s) hija(s). Eliminá primero las cuentas inferiores.", 'danger');
        header('Location: cuentas.php'); exit;
    }
}

$stmt = $conn->prepare("DELETE FROM cuentas WHERE id=?");
$stmt->bind_param('i',$id);
$stmt->execute();

flash_set("Cuenta {$row['codigo']} eliminada.", 'success');
header('Location: cuentas.php'); exit;
