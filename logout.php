<?php
/** Cierra la sesión (solo por POST con token CSRF). */
require_once __DIR__ . '/inc/inicio.php';

if (!es_post()) {
    redirigir(usuario_actual() ? ruta_panel() : 'login.php');
}
verificar_csrf();

if ($u = usuario_actual()) {
    registrar_auditoria('cerrar_sesion', 'usuario', $u['c_usuario'], 'exito');
}
reiniciar_sesion();
aviso('exito', 'Cerraste sesión.');
redirigir('login.php');
