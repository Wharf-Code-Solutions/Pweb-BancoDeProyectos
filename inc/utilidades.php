<?php
/**
 * Funciones generales: escape de HTML, URLs, redirecciones y errores HTTP.
 */

/** Escapa texto para imprimirlo en HTML. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Ruta base del sitio (ej. "/Banco_Proyectos").
 * Usa config['base_url'] si existe; si no, la calcula desde DOCUMENT_ROOT.
 */
function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $c = config();
        if (isset($c['base_url'])) {
            $base = rtrim($c['base_url'], '/');
        } else {
            $raiz = str_replace('\\', '/', realpath(__DIR__ . '/..'));
            $doc  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
            $base = ($doc !== '' && stripos($raiz, $doc) === 0) ? substr($raiz, strlen($doc)) : '';
            $base = '/' . trim($base, '/');
            $base = $base === '/' ? '' : $base;
        }
    }
    return $base;
}

/** URL absoluta (desde la raíz del servidor) a una ruta del proyecto. */
function url(string $ruta = ''): string
{
    return base_url() . '/' . ltrim($ruta, '/');
}

/** Redirige y termina. */
function redirigir(string $ruta): never
{
    // Acepta rutas del proyecto ('mod_admin/usuarios.php') o URLs ya armadas con url() ('/Banco_Proyectos/...').
    $absoluta = str_starts_with($ruta, '/') && !str_starts_with($ruta, '//');
    header('Location: ' . ($absoluta ? $ruta : url($ruta)));
    exit;
}

/** Muestra la página de error correspondiente (403, 404 o 500) y termina. */
function error_http(int $codigo): never
{
    if (!in_array($codigo, [403, 404, 500], true)) {
        $codigo = 500;
    }
    http_response_code($codigo);
    require __DIR__ . '/../errores/plantilla_error.php';
    exit;
}

/** ¿La petición actual es POST? */
function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Iniciales para el avatar (ej. "María Fernanda López" → "ML"). */
function iniciales(string $nombre): string
{
    $conectores = ['de', 'del', 'la', 'las', 'los', 'y'];
    $partes = array_values(array_filter(
        preg_split('/\s+/', trim($nombre)) ?: [],
        fn ($p) => !in_array(mb_strtolower($p), $conectores, true)
    ));
    $primera = $partes[0] ?? '';
    $ultima  = count($partes) > 2 ? $partes[count($partes) - 2] : ($partes[1] ?? '');
    return mb_strtoupper(mb_substr($primera, 0, 1) . mb_substr($ultima, 0, 1));
}
