<?php
/**
 * Plantilla B · Aplicación (parte superior): barra superior, menú lateral,
 * menú móvil (offcanvas) y apertura del contenido.
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
<div class="app">
    <a class="saltar" href="#contenido">Saltar al contenido</a>
    <header class="barra-superior">
        <button type="button" class="btn-icono btn-menu" aria-label="Abrir menú"
                data-bs-toggle="offcanvas" data-bs-target="#menu-movil" aria-controls="menu-movil">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <a href="<?= e(url(ruta_panel($usuario))) ?>" class="marca">
            <img src="<?= e(url('img/favicon.svg')) ?>" alt="" width="28" height="28">
            <span class="nombre-largo">Banco de Proyectos Académicos</span>
            <span class="nombre-corto">Banco de Proyectos</span>
        </a>
        <a class="btn-icono" href="<?= e(url('mod_comun/notificaciones.php')) ?>"
           aria-label="Notificaciones<?= $sin_leer ? ': ' . $sin_leer . ' sin leer' : '' ?>">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <?php if ($sin_leer): ?><span class="contador" aria-hidden="true"><?= $sin_leer > 99 ? '99+' : $sin_leer ?></span><?php endif; ?>
        </a>
        <div class="dropdown">
            <button type="button" class="menu-usuario" data-bs-toggle="dropdown" aria-expanded="false"
                    aria-label="Menú de usuario: <?= e($usuario['nombre']) ?>">
                <span class="avatar"><?= e(iniciales($usuario['nombre'])) ?></span>
                <span class="quien"><strong><?= e($usuario['nombre']) ?></strong><small><?= e($rol_txt) ?></small></span>
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-header"><?= e($usuario['subtitulo'] ?: $rol_txt) ?></span></li>
                <li><a class="dropdown-item" href="<?= e(url('mod_comun/cuenta.php')) ?>">Mi cuenta</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('logout.php')) ?>">
                        <?= csrf_campo() ?>
                        <button type="submit" class="dropdown-item">Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <div class="offcanvas offcanvas-start offcanvas-nav" tabindex="-1" id="menu-movil" aria-label="Menú">
        <div class="offcanvas-header">
            <span class="rol-actual m-0 p-0 border-0"><?= e($rol_txt) ?><strong><?= e($usuario['nombre']) ?></strong></span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav-rol"><?= html_menu($usuario['rol'], $menu_activo) ?></ul>
            <ul class="nav-rol secundario">
                <li><a href="<?= e(url('mod_comun/cuenta.php')) ?>"><i class="bi bi-person-circle" aria-hidden="true"></i><span>Mi cuenta</span></a></li>
                <li>
                    <form method="post" action="<?= e(url('logout.php')) ?>">
                        <?= csrf_campo() ?>
                        <button type="submit" class="enlace-menu"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Cerrar sesión</span></button>
                    </form>
                </li>
            </ul>
        </div>
    </div>

    <div class="cuerpo-app">
        <nav class="barra-lateral" aria-label="Navegación principal">
            <ul class="nav-rol"><?= html_menu($usuario['rol'], $menu_activo) ?></ul>
        </nav>
        <div class="contenido">
            <main id="contenido">
                <?php if (!empty($migas)): ?>
                    <nav aria-label="Migas de pan"><ol class="breadcrumb">
                        <?php foreach ($migas as $i => [$texto, $ruta]): $ultima = $i === array_key_last($migas); ?>
                            <li class="breadcrumb-item<?= $ultima ? ' active' : '' ?>"<?= $ultima ? ' aria-current="page"' : '' ?>>
                                <?= ($ruta && !$ultima) ? '<a href="' . e(url($ruta)) . '">' . e($texto) . '</a>' : e($texto) ?>
                            </li>
                        <?php endforeach; ?>
                    </ol></nav>
                <?php endif; ?>
                <?= mostrar_avisos() ?>
