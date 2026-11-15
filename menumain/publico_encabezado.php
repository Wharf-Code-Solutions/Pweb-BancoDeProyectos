<?php
/**
 * Plantilla A · Pública (parte superior): franja gob.mx y encabezado con logos.
 * Variables: $titulo.
 */
$titulo ??= 'Banco de Proyectos Académicos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Banco de Proyectos Académicos</title>
    <?php require __DIR__ . '/recursos_css.php'; ?>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="franja-gob"><div class="contenedor"><a href="https://www.gob.mx">gob.mx</a><span>Gobierno de México</span></div></div>
<header class="encabezado-publico">
    <div class="contenedor">
        <a href="<?= e(url('index.php')) ?>" class="d-flex align-items-center gap-3 text-decoration-none">
            <div class="logos"><span class="logo-ph tecnm">LOGO TecNM</span><span class="sep" aria-hidden="true"></span><span class="logo-ph itver">ITVer</span></div>
            <span class="nombre d-none d-md-block">Banco de Proyectos Académicos<small>Vinculación de empresas, alumnos y profesores · ITVer</small></span>
        </a>
        <?php if ($u = usuario_actual()): ?>
            <a class="btn btn-secundario" href="<?= e(url(ruta_panel($u))) ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i>Ir a mi panel</a>
        <?php elseif (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'login.php'): ?>
            <a class="btn btn-secundario" href="<?= e(url('login.php')) ?>"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Iniciar sesión</a>
        <?php endif; ?>
    </div>
</header>
<main id="contenido">
