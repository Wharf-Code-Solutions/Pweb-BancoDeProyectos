<?php
/**
 * Plantilla B · Aplicación (parte superior): menú lateral con la marca, la campana
 * y el usuario; en celular, barra superior y menú deslizable (offcanvas).
 *
 * Variables que define la página antes de incluirla:
 *   $titulo       Título de la pestaña y del encabezado.
 *   $menu_activo  Ruta del menú que se marca (ej. 'mod_alumno/index.php').
 *   $migas        (opcional) [['Panel', 'mod_alumno/index.php'], ['Mi cuenta', null]]
 */
$usuario = usuario_actual();
$menu_activo ??= '';
$titulo ??= 'Banco de Proyectos Académicos';
$sin_leer = notificaciones_sin_leer($usuario['c_usuario']);
$rol_txt = nombre_rol($usuario['rol']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/metadatos.php'; ?>
    <title><?= e($titulo) ?> · Banco de Proyectos Académicos</title>
    <?php require __DIR__ . '/recursos_css.php'; ?>
</head>
<body>
<?php
$campana = function (string $clase = '') use ($sin_leer): string {
    return '<a class="btn-icono ' . $clase . '" href="' . e(url('mod_comun/notificaciones.php')) . '" aria-label="Notificaciones'
         . ($sin_leer ? ': ' . $sin_leer . ' sin leer' : '') . '"><i class="bi bi-bell" aria-hidden="true"></i>'
         . ($sin_leer ? '<span class="contador" aria-hidden="true">' . ($sin_leer > 99 ? '99+' : $sin_leer) . '</span>' : '') . '</a>';
};
$menu_cuenta = '<li><span class="dropdown-header">' . e($usuario['subtitulo'] ?: $rol_txt) . '</span></li>'
    . '<li><a class="dropdown-item" href="' . e(url('mod_comun/cuenta.php')) . '">Mi cuenta</a></li>'
    . '<li><a class="dropdown-item" href="' . e(url('mod_comun/notificaciones.php')) . '">Notificaciones</a></li>'
    . '<li><hr class="dropdown-divider"></li>'
    . '<li><div class="apariencia"><span class="dropdown-header p-0">Apariencia</span>'
    . '<label class="fila-apariencia"><span>Modo oscuro</span><span class="form-check form-switch">'
    . '<input class="form-check-input" type="checkbox" role="switch" data-tema-interruptor></span></label>'
    . '<div class="fila-apariencia fila-letra"><span>Tamaño de letra</span><span class="btn-group selector-letra" role="group" aria-label="Tamaño de letra">'
    . '<button type="button" class="btn btn-secundario btn-sm" data-letra="normal" aria-label="Letra normal" title="Normal">A</button>'
    . '<button type="button" class="btn btn-secundario btn-sm" data-letra="grande" aria-label="Letra grande" title="Grande">A</button>'
    . '<button type="button" class="btn btn-secundario btn-sm" data-letra="mayor" aria-label="Letra más grande" title="Más grande">A</button>'
    . '</span></div></div></li>'
    . '<li><hr class="dropdown-divider"></li>'
    . '<li><form method="post" action="' . e(url('logout.php')) . '">' . csrf_campo()
    . '<button type="submit" class="dropdown-item">Cerrar sesión</button></form></li>';
?>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="app">
    <header class="barra-movil">
        <button type="button" class="btn-icono" aria-label="Abrir menú"
                data-bs-toggle="offcanvas" data-bs-target="#menu-movil" aria-controls="menu-movil">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <a href="<?= e(url(ruta_panel($usuario))) ?>" class="marca">
            <img src="<?= e(url('img/favicon.svg')) ?>" alt="" width="26" height="26">
            <span>Banco de Proyectos</span>
        </a>
        <?= $campana() ?>
        <div class="dropdown">
            <button type="button" class="menu-usuario solo-avatar" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                    aria-label="Menú de usuario: <?= e($usuario['nombre']) ?>">
                <span class="avatar"><?= e(iniciales($usuario['nombre'])) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end"><?= $menu_cuenta ?></ul>
        </div>
    </header>

    <div class="offcanvas offcanvas-start offcanvas-nav" tabindex="-1" id="menu-movil" aria-label="Menú">
        <div class="offcanvas-header">
            <span class="marca"><img src="<?= e(url('img/favicon.svg')) ?>" alt="" width="26" height="26"><span>Banco de Proyectos<small>TecNM · ITVer</small></span></span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav-rol"><?= html_menu($usuario['rol'], $menu_activo) ?></ul>
        </div>
    </div>

    <div class="cuerpo-app">
        <aside class="barra-lateral">
            <div class="lateral-cab">
                <a href="<?= e(url(ruta_panel($usuario))) ?>" class="marca">
                    <img src="<?= e(url('img/favicon.svg')) ?>" alt="" width="28" height="28">
                    <span>Banco de Proyectos<small>TecNM · ITVer</small></span>
                </a>
                <?= $campana() ?>
            </div>
            <nav aria-label="Navegación principal"><ul class="nav-rol"><?= html_menu($usuario['rol'], $menu_activo) ?></ul></nav>
            <div class="lateral-pie dropup">
                <button type="button" class="menu-usuario" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                        aria-label="Menú de usuario: <?= e($usuario['nombre']) ?>">
                    <span class="avatar"><?= e(iniciales($usuario['nombre'])) ?></span>
                    <span class="quien"><strong><?= e($usuario['nombre']) ?></strong><small><?= e($rol_txt) ?></small></span>
                    <i class="bi bi-chevron-expand" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu"><?= $menu_cuenta ?></ul>
            </div>
        </aside>
        <div class="contenido">
            <?php if (!empty($migas)): ?>
                <nav class="barra-pagina" aria-label="Migas de pan"><ol class="breadcrumb">
                    <?php foreach ($migas as $i => [$texto, $ruta]): $ultima = $i === array_key_last($migas); ?>
                        <li class="breadcrumb-item<?= $ultima ? ' active' : '' ?>"<?= $ultima ? ' aria-current="page"' : '' ?>>
                            <?= ($ruta && !$ultima) ? '<a href="' . e(url($ruta)) . '">' . e($texto) . '</a>' : e($texto) ?>
                        </li>
                    <?php endforeach; ?>
                </ol></nav>
            <?php endif; ?>
            <main id="contenido">
                <?= mostrar_avisos() ?>
