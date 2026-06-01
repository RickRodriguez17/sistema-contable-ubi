<?php
/**
 * ContaUBI — Endpoint AJAX para verificación de código de cuenta en tiempo real
 *
 * Responde JSON: {"exists": true|false, "codigo": "...", "nombre": "...", "nivel": N}
 *
 * Parámetros:
 *   codigo  (string)  código de 8 dígitos a verificar
 *   exclude (int)     id de cuenta a excluir (para modo edición)
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/auth.php';

if (!auth_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$codigo  = trim((string)($_GET['codigo'] ?? $_POST['codigo'] ?? ''));
$exclude = (int)($_GET['exclude'] ?? $_POST['exclude'] ?? 0);

if (!preg_match('/^[0-9]{1,8}$/', $codigo)) {
    echo json_encode([
        'exists'  => false,
        'codigo'  => $codigo,
        'mensaje' => 'Código inválido (debe ser numérico, 1-8 dígitos).',
    ]);
    exit;
}

/* Normalizamos a 8 dígitos (pad-derecho con ceros, como guarda la BD). */
$codigoFull = str_pad($codigo, 8, '0', STR_PAD_RIGHT);

$stmt = $conn->prepare("SELECT id, codigo, nombre, nivel
                        FROM cuentas
                        WHERE codigo = ? AND id <> ?
                        LIMIT 1");
$stmt->bind_param('si', $codigoFull, $exclude);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
    echo json_encode([
        'exists' => true,
        'codigo' => $row['codigo'],
        'nombre' => $row['nombre'],
        'nivel'  => (int)$row['nivel'],
    ]);
} else {
    echo json_encode([
        'exists' => false,
        'codigo' => $codigoFull,
    ]);
}
