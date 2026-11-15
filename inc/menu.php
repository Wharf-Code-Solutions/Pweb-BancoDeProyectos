<?php
/**
 * Menú lateral de cada rol (igual al de las pantallas).
 * Cada elemento: ['ruta', 'icono', 'texto'] o ['grupo' => 'Título'].
 * La ruta es relativa a la raíz del proyecto.
 */

function menu_del_rol(string $rol): array
{
    return match ($rol) {
        'alumno' => [
            ['mod_alumno/index.php',          'bi-speedometer2', 'Panel'],
            ['mod_alumno/proyectos.php',      'bi-search',       'Proyectos disponibles'],
            ['mod_alumno/solicitudes.php',    'bi-send',         'Mis solicitudes'],
            ['mod_alumno/perfil.php',         'bi-person-vcard', 'Mi perfil'],
            ['mod_alumno/habilidades.php',    'bi-stars',        'Mis habilidades'],
            ['mod_comun/notificaciones.php',  'bi-bell',         'Notificaciones'],
        ],
        'empresa' => [
            ['mod_empresa/index.php',         'bi-speedometer2', 'Panel'],
            ['mod_empresa/proyectos.php',     'bi-folder2-open', 'Mis proyectos'],
            ['mod_empresa/proyecto_form.php', 'bi-plus-circle',  'Nuevo proyecto'],
            ['mod_empresa/datos.php',         'bi-building',     'Datos de la empresa'],
            ['mod_comun/notificaciones.php',  'bi-bell',         'Notificaciones'],
        ],
        'profesor' => [
            ['mod_profesor/index.php',        'bi-speedometer2', 'Panel'],
            ['mod_profesor/proyectos.php',    'bi-search',       'Proyectos disponibles'],
            ['mod_profesor/solicitudes.php',  'bi-send-check',   'Mis solicitudes de dirección'],
            ['mod_profesor/dirijo.php',       'bi-briefcase',    'Proyectos que dirijo'],
            ['mod_profesor/perfil.php',       'bi-person-vcard', 'Mi perfil'],
            ['mod_comun/notificaciones.php',  'bi-bell',         'Notificaciones'],
        ],
        'administrador' => [
            ['mod_admin/index.php',           'bi-speedometer2',  'Panel'],
            ['grupo' => 'Revisión'],
            ['mod_admin/revisar_proyectos.php', 'bi-clipboard-check', 'Proyectos por revisar'],
            ['mod_admin/candidaturas.php',    'bi-people',        'Candidaturas por decidir'],
            ['mod_admin/direccion.php',       'bi-person-check',  'Solicitudes de dirección'],
            ['grupo' => 'Gestión'],
            ['mod_admin/proyectos.php',       'bi-folder2',       'Proyectos'],
            ['mod_admin/asignaciones.php',    'bi-diagram-3',     'Asignaciones'],
            ['mod_admin/usuarios.php',        'bi-person-gear',   'Usuarios'],
            ['grupo' => 'Catálogos'],
            ['mod_admin/habilidades.php',     'bi-tags',          'Habilidades'],
            ['mod_admin/criterios.php',       'bi-sliders',       'Criterios'],
            ['grupo' => 'Consulta'],
            ['mod_admin/reportes.php',        'bi-bar-chart',     'Reportes'],
            ['mod_admin/historial.php',       'bi-clock-history', 'Historial'],
        ],
        default => [],
    };
}

/** Nombre del rol para mostrar. */
function nombre_rol(string $rol): string
{
    return match ($rol) {
        'alumno' => 'Alumno', 'empresa' => 'Empresa', 'profesor' => 'Profesor',
        'administrador' => 'Administrador', default => ucfirst($rol),
    };
}

/** <li> del menú; marca como activa la página actual. */
function html_menu(string $rol, string $activa): string
{
    $html = '';
    foreach (menu_del_rol($rol) as $item) {
        if (isset($item['grupo'])) {
            $html .= '<li class="grupo">' . e($item['grupo']) . '</li>';
            continue;
        }
        [$ruta, $icono, $texto] = $item;
        $es = $ruta === $activa;
        $html .= '<li><a href="' . e(url($ruta)) . '"' . ($es ? ' class="active" aria-current="page"' : '') . '>'
               . '<i class="bi ' . $icono . '" aria-hidden="true"></i><span>' . e($texto) . '</span></a></li>';
    }
    return $html;
}

/** Notificaciones sin leer del usuario (para la campana). */
function notificaciones_sin_leer(string $c_usuario): int
{
    $s = conexion()->prepare('SELECT COUNT(*) FROM notificacion WHERE c_usuario = ? AND leida = 0');
    $s->execute([$c_usuario]);
    return (int) $s->fetchColumn();
}
