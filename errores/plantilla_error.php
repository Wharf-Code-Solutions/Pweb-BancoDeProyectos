<?php
/**
 * Pantalla P4 · Páginas de error. La usa error_http($codigo), que ya fijó
 * el código de respuesta. $codigo: 403, 404 o 500.
 */
$codigo = $codigo ?? http_response_code();
$datos = [
    403 => ['bi-shield-lock', 'No tienes permiso para ver esta página',
            'Tu cuenta no tiene acceso a esta sección. Si crees que es un error, contacta a la administración del Tec.'],
    404 => ['bi-question-circle', 'No encontramos lo que buscas',
            'La página o el registro no existe o no es tuyo.'],
    500 => ['bi-exclamation-octagon', 'Algo salió mal',
            'Ocurrió un error en el sistema. Intenta de nuevo en unos minutos.'],
][$codigo] ?? null;
if ($datos === null) {
    $codigo = 500;
    $datos = [500 => ['bi-exclamation-octagon', 'Algo salió mal', 'Ocurrió un error en el sistema. Intenta de nuevo en unos minutos.']][500];
}
[$icono, $titulo_error, $texto] = $datos;
$titulo = "Error $codigo";
$destino = usuario_actual() ? ruta_panel() : 'index.php';
$texto_boton = usuario_actual() ? 'Ir a mi panel' : 'Ir al inicio';

require __DIR__ . '/../menumain/publico_encabezado.php';
?>
<section class="seccion-publica alterna" style="padding:4rem 0 5rem">
    <div class="contenedor">
        <div class="error-pagina">
            <div class="vacio-icono" style="width:88px;height:88px;margin:0 auto 1rem;border-radius:50%;display:grid;place-items:center;background:var(--peligro-bg);color:var(--peligro);font-size:2.4rem">
                <i class="bi <?= $icono ?>" aria-hidden="true"></i>
            </div>
            <div class="codigo"><?= (int) $codigo ?></div>
            <h1 class="h2 mt-2"><?= e($titulo_error) ?></h1>
            <p class="text-mute"><?= e($texto) ?></p>
            <a class="btn btn-principal" href="<?= e(url($destino)) ?>"><?= e($texto_boton) ?></a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../menumain/publico_pie.php'; ?>
