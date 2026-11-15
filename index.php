<?php
/**
 * Página temporal de verificación del entorno (Fase 0).
 * Se reemplaza por la pantalla P1 (Inicio) en la Fase 2.
 */
require_once __DIR__ . '/bd/conexion.php';

$checks = [];
$checks['PHP ' . PHP_VERSION] = version_compare(PHP_VERSION, '8.0', '>=');
$checks['Extensión pdo_mysql'] = extension_loaded('pdo_mysql');
$checks['Configuración local (bd/config.local.php)'] = is_file(__DIR__ . '/bd/config.local.php');

$detalle = '';
try {
    $pdo = conexion();
    $checks['Conexión a la base'] = true;
    $tablas = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchColumn();
    $checks["Tablas creadas ($tablas de 20)"] = $tablas === 20;
    $usuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn();
    $checks["Usuarios de prueba ($usuarios)"] = $usuarios >= 4;

    $pdo->beginTransaction();
    $folio = siguiente_folio('PRY');
    $pdo->rollBack();
    $checks["Procedimiento de folios ($folio)"] = (bool) preg_match('/^PRY-\d{8}$/', $folio);
} catch (PDOException $e) {
    $checks['Conexión a la base'] = false;
    $detalle = config()['debug'] ? $e->getMessage() : 'Revisa bd/config.local.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Banco de Proyectos · Verificación del entorno</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f5f4ef; color: #1f1e1d; max-width: 640px; margin: 40px auto; padding: 0 16px; }
        li { margin: 6px 0; }
        .ok { color: #1a7f37; } .fallo { color: #b42318; }
        code { background: #e9e7df; padding: 2px 4px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Banco de Proyectos Académicos</h1>
    <p>Verificación del entorno de desarrollo.</p>
    <ul>
        <?php foreach ($checks as $nombre => $ok): ?>
            <li class="<?= $ok ? 'ok' : 'fallo' ?>"><?= $ok ? '✔' : '✘' ?> <?= htmlspecialchars($nombre) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($detalle !== ''): ?>
        <p class="fallo"><code><?= htmlspecialchars($detalle) ?></code></p>
    <?php endif; ?>
</body>
</html>
