<?php
/**
 * ContaUBI — conexión a base de datos
 * Sistema Contable Universidad Boliviana de Informática
 *
 * Variables de entorno opcionales (con fallback para entornos académicos):
 *   CONTAUBI_DB_HOST   default: 127.0.0.1
 *   CONTAUBI_DB_USER   default: root
 *   CONTAUBI_DB_PASS   default: (vacío)
 *   CONTAUBI_DB_NAME   default: contaubi
 */
declare(strict_types=1);

$DB_HOST = getenv('CONTAUBI_DB_HOST') ?: '127.0.0.1';
$DB_USER = getenv('CONTAUBI_DB_USER') ?: 'root';
$DB_PASS = getenv('CONTAUBI_DB_PASS') ?: '';
$DB_NAME = getenv('CONTAUBI_DB_NAME') ?: 'contaubi';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
    $conn->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1 style='font-family:sans-serif;color:#c00'>Error de conexión a la base de datos</h1>";
    echo "<p>Verificá que MySQL esté corriendo y que la base <code>contaubi</code> exista.</p>";
    echo "<pre style='background:#111;color:#eee;padding:1rem'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

/* Empresa (singleton) — siempre disponible para encabezados y reportes */
$EMPRESA = $conn->query("SELECT * FROM empresa LIMIT 1")->fetch_assoc()
        ?: ['nombre'=>'ContaUBI','nit'=>'','ciudad'=>'','moneda'=>'Bs.','ejercicio'=>(int)date('Y'),
            'fecha_inicio_ejercicio'=>date('Y').'-01-01','fecha_cierre_ejercicio'=>date('Y').'-12-31',
            'logo_texto'=>'UBI'];
