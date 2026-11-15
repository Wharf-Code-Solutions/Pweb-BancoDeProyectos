<?php
/**
 * Plantilla de configuración.
 * Copia este archivo como bd/config.local.php y ajusta los valores.
 * config.local.php está en .gitignore: las credenciales reales nunca se suben.
 */
return [
    'db_host'     => 'localhost',
    'db_name'     => 'banco_proyectos',
    'db_user'     => 'root',
    'db_pass'     => '',
    'db_timezone' => '-06:00',
    'debug'       => true,   // false en producción: oculta los detalles de errores
];
