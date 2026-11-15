<?php
/**
 * Arranque común de todas las páginas.
 *   require_once __DIR__ . '/inc/inicio.php';        (desde la raíz)
 *   require_once __DIR__ . '/../inc/inicio.php';     (desde un módulo)
 */

declare(strict_types=1);

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/utilidades.php';
require_once __DIR__ . '/avisos.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/reglas.php';
require_once __DIR__ . '/menu.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Mexico_City');

// Encabezados de seguridad básicos.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// Errores: en desarrollo se ven; en producción se registran y se muestra la página 500.
$__debug = (bool) (config()['debug'] ?? false);
ini_set('display_errors', $__debug ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $ex) use ($__debug): void {
    error_log((string) $ex);
    if ($__debug) {
        http_response_code(500);
        echo '<pre style="white-space:pre-wrap;padding:1rem">' . e((string) $ex) . '</pre>';
        return;
    }
    if (!headers_sent()) {
        error_http(500);
    }
});

iniciar_sesion_php();
