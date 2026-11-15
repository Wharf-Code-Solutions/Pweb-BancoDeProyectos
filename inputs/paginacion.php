<?php
/**
 * Paginación reutilizable.
 *
 *   $pag = paginar($total, 20);                 // lee ?pagina=
 *   ... LIMIT $pag['por_pagina'] OFFSET $pag['desde']
 *   echo pie_paginacion($pag, count($filas), 'mod_admin/usuarios.php', $filtros);
 */

function paginar(int $total, int $por_pagina = 20): array
{
    $paginas = max(1, (int) ceil($total / $por_pagina));
    $pagina = min($paginas, max(1, (int) ($_GET['pagina'] ?? 1)));
    return ['total' => $total, 'por_pagina' => $por_pagina, 'paginas' => $paginas,
            'pagina' => $pagina, 'desde' => ($pagina - 1) * $por_pagina];
}

/** URL de la lista conservando los filtros. */
function url_lista(string $ruta, array $parametros = []): string
{
    if (($parametros['pagina'] ?? null) === 1) {
        unset($parametros['pagina']);
    }
    $parametros = array_filter($parametros, fn ($v) => $v !== null && $v !== '');
    return url($ruta . ($parametros ? '?' . http_build_query($parametros) : ''));
}

/** "Mostrando 1–20 de 87" + páginas. */
function pie_paginacion(array $pag, int $en_pagina, string $ruta, array $filtros = []): string
{
    $texto = $pag['total'] === 0 ? 'Sin resultados'
        : 'Mostrando ' . ($pag['desde'] + 1) . '–' . ($pag['desde'] + $en_pagina) . ' de ' . $pag['total'];
    $html = '<span>' . $texto . '</span>';
    if ($pag['paginas'] <= 1) {
        return $html;
    }

    $p = $pag['pagina'];
    $enlace = fn (int $n) => e(url_lista($ruta, $filtros + ['pagina' => $n]));
    $html .= '<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item' . ($p <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . $enlace(max(1, $p - 1)) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Anterior</span></a></li>';

    // Muestra como máximo 7 números alrededor de la página actual.
    $inicio = max(1, min($p - 3, $pag['paginas'] - 6));
    $fin = min($pag['paginas'], $inicio + 6);
    for ($n = $inicio; $n <= $fin; $n++) {
        $html .= $n === $p
            ? '<li class="page-item active" aria-current="page"><span class="page-link">' . $n . '</span></li>'
            : '<li class="page-item"><a class="page-link" href="' . $enlace($n) . '">' . $n . '</a></li>';
    }
    $html .= '<li class="page-item' . ($p >= $pag['paginas'] ? ' disabled' : '') . '"><a class="page-link" href="' . $enlace(min($pag['paginas'], $p + 1)) . '"><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Siguiente</span></a></li>';
    return $html . '</ul></nav>';
}
