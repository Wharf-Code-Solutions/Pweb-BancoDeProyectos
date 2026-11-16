<?php
/**
 * Plantilla A · Pública (parte superior): encabezado con el nombre del sistema.
 * Variables: $titulo.
 */
$titulo ??= 'Banco de Proyectos Académicos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/metadatos.php'; ?>
    <title><?= e($titulo) ?> · Banco de Proyectos Académicos</title>
    <?php require __DIR__ . '/recursos_css.php'; ?>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<header class="encabezado-publico">
    <div class="contenedor">
        <a href="<?= e(url('index.php')) ?>" class="marca-publica">
            <img src="<?= e(url('img/favicon.svg')) ?>" alt="" width="32" height="32">
            <span class="nombre">Banco de Proyectos Académicos<small>TecNM · Instituto Tecnológico de Veracruz</small></span>
        </a>
        <?php if ($u = usuario_actual()): ?>
            <a class="btn btn-secundario" href="<?= e(url(ruta_panel($u))) ?>">Ir a mi panel</a>
        <?php elseif (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'login.php'): ?>
            <a class="btn btn-secundario" href="<?= e(url('login.php')) ?>">Iniciar sesión</a>
        <?php endif; ?>
    </div>
</header>
<main id="contenido">
