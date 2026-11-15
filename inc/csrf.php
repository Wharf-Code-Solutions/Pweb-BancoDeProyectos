<?php
/**
 * Protección CSRF para todos los formularios POST.
 *
 *   <form method="post"> <?= csrf_campo() ?> ... </form>
 *   if (es_post()) { verificar_csrf(); ... }
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Detiene la petición con 403 si el token no coincide. */
function verificar_csrf(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        error_http(403);
    }
}
