<?php
/**
 * Conexión única a la base de datos (PDO).
 *
 * Uso:
 *   require_once __DIR__ . '/../bd/conexion.php';
 *   $pdo = conexion();
 */

function config(): array
{
    static $config = null;
    if ($config === null) {
        $archivo = __DIR__ . '/config.local.php';
        if (!is_file($archivo)) {
            $archivo = __DIR__ . '/config.ejemplo.php';
        }
        $config = require $archivo;
    }
    return $config;
}

function conexion(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config();
        $dsn = "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $stmt = $pdo->prepare('SET time_zone = ?');
        $stmt->execute([$c['db_timezone']]);
    }
    return $pdo;
}

/**
 * Reserva el siguiente folio (PRE-00000000) con sp_siguiente_folio.
 * Llamarlo dentro de la misma transacción del INSERT, salvo para HIS y NTF,
 * que se reservan antes de abrir la transacción.
 */
function siguiente_folio(string $prefijo): string
{
    $pdo = conexion();
    $stmt = $pdo->prepare('CALL sp_siguiente_folio(?, @folio)');
    $stmt->execute([$prefijo]);
    $stmt->closeCursor();
    return (string) $pdo->query('SELECT @folio')->fetchColumn();
}
