<?php
/**
 * Metadatos comunes del <head> (los incluyen las plantillas A y B).
 *
 * Variables opcionales que define la página antes de la plantilla:
 *   $descripcion  Descripción de la página (si no, se usa la general).
 *   $indexar      true solo en páginas públicas; las privadas no se indexan.
 */
$descripcion ??= 'Sistema web del TecNM · Instituto Tecnológico de Veracruz para registrar, evaluar, '
               . 'autorizar y asignar proyectos académicos entre empresas, alumnos y profesores.';
$indexar ??= false;
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($descripcion) ?>">
    <meta name="author" content="Carlos Daniel Rodríguez Cano, Evaristo Romero Tirado">
    <meta name="keywords" content="banco de proyectos, proyectos académicos, TecNM, ITVer, Sistemas Computacionales, candidaturas, residencias, vinculación empresarial">
    <meta name="application-name" content="Banco de Proyectos Académicos">
    <meta name="robots" content="<?= $indexar ? 'index, follow' : 'noindex, nofollow' ?>">
    <meta name="theme-color" content="#1b396a">
    <meta name="color-scheme" content="light dark">
    <meta name="format-detection" content="telephone=no">
    <script src="<?= e(url_recurso('js/tema-inicio.js')) ?>"></script>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('img/favicon.svg')) ?>">
