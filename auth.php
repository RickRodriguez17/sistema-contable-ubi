<?php
/**
 * ContaUBI — Autenticación y control de acceso por roles.
 *
 * Roles:
 *   admin    — acceso total (gestiona usuarios, cuentas, comprobantes, empresa)
 *   contador — registra movimientos / crea comprobantes / ve reportes
 *   consulta — sólo lectura del dashboard y reportes
 *
 * Uso en una página protegida (cualquier .php con sesión obligatoria):
 *
 *     require_once __DIR__ . '/conexion.php';
 *     require_once __DIR__ . '/helpers.php';
 *     require_once __DIR__ . '/auth.php';                 // exige login
 *     auth_require('comprobantes.crear');                 // exige permiso
 *
 * Para páginas públicas (login, validar_login, logout):
 *
 *     $AUTH_PUBLIC = true;
 *     require_once __DIR__ . '/auth.php';
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Devuelve el usuario logueado o null. */
function auth_user(): ?array {
    return $_SESSION['usuario'] ?? null;
}

/** Devuelve el rol del usuario actual o null. */
function auth_role(): ?string {
    return $_SESSION['usuario']['rol'] ?? null;
}

/** ¿Hay sesión iniciada? */
function auth_logged_in(): bool {
    return !empty($_SESSION['usuario']);
}

/**
 * Matriz de permisos por rol.
 * Cada acción es una cadena del estilo "modulo.accion".
 */
function auth_can(string $accion): bool {
    $rol = auth_role();
    if ($rol === null) return false;

    $matrix = [
        'admin' => [
            'dashboard.ver',
            'reportes.ver',
            'cuentas.ver',
            'cuentas.gestionar',     // crear / editar / eliminar
            'comprobantes.ver',
            'comprobantes.crear',
            'comprobantes.aprobar',  // aprobar / anular / reactivar
            'empresa.editar',
            'usuarios.gestionar',
        ],
        'contador' => [
            'dashboard.ver',
            'reportes.ver',
            'cuentas.ver',
            'comprobantes.ver',
            'comprobantes.crear',
        ],
        'consulta' => [
            'dashboard.ver',
            'reportes.ver',
            'cuentas.ver',
            'comprobantes.ver',
        ],
    ];

    return in_array($accion, $matrix[$rol] ?? [], true);
}

/** Si no hay sesión iniciada, redirige a login.php. */
function auth_check(): void {
    if (!auth_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** Bloquea la página si el rol actual no tiene el permiso pedido. */
function auth_require(string $accion): void {
    auth_check();
    if (!auth_can($accion)) {
        if (function_exists('flash_set')) {
            flash_set('No tenés permiso para acceder a esa sección.', 'danger');
        }
        header('Location: index.php');
        exit;
    }
}

/* Si la página NO marcó $AUTH_PUBLIC = true antes del include, exigimos login. */
if (empty($AUTH_PUBLIC)) {
    auth_check();
}
