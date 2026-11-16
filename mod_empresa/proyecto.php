<?php
/** Pantalla E3 · Detalle de proyecto (empresa). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('empresa');

$id = (string) ($_GET['id'] ?? '');
$p = obtener_proyecto($id);
if (!$p || $p['rfc_empresa'] !== $usuario['clave']) {
    error_http(404);
}
$pestana = ($_GET['tab'] ?? '') === 'historial' ? 'historial' : 'info';
$eventos = linea_tiempo_proyecto($id);
$editable = in_array($p['estado'], ['borrador', 'devuelto'], true);
$con_candidatos = in_array($p['estado'], ['publicado', 'en_curso', 'finalizado'], true);

$s = conexion()->prepare("SELECT COUNT(*) FROM solicitud_candidatura WHERE c_proyecto = ? AND estado <> 'cancelada'");
$s->execute([$id]);
$num_candidatos = (int) $s->fetchColumn();

$publicado_el = null;
foreach (autorizaciones_proyecto($id) as $a) {
    if ($a['decision'] === 'aprobada') {
        $publicado_el = $a['fecha'];
        break;
    }
}

$titulo = $p['titulo'];
$menu_activo = 'mod_empresa/proyectos.php';
$migas = [['Panel', 'mod_empresa/index.php'], ['Mis proyectos', 'mod_empresa/proyectos.php'], [$p['titulo'], null]];
require __DIR__ . '/../menumain/app_encabezado.php';

$nota_estado = match ($p['estado']) {
    'borrador'    => 'El proyecto está en borrador. Complétalo y envíalo a revisión cuando esté listo.',
    'devuelto'    => 'El Tec pidió correcciones. Edita el proyecto y reenvíalo.',
    'en_revision' => 'El Tec está revisando el proyecto. Mientras tanto no se puede editar.',
    'publicado'   => 'Mientras el proyecto está publicado no se puede editar. Si necesitas cambios, contacta a la administración.',
    'en_curso'    => 'El proyecto está en curso.',
    'rechazado'   => 'El proyecto fue rechazado y no puede reenviarse.',
    default       => 'El proyecto está cerrado.',
};
?>
<div class="encabezado-pagina">
    <div>
        <h1 class="titulo-izquierda"><?= e($p['titulo']) ?></h1>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
            <?= insignia('proyecto', $p['estado']) ?><?= html_folio($id) ?>
            <span class="small text-mute"><?= $publicado_el ? 'Publicado el ' . e(fecha_corta($publicado_el)) : 'Registrado el ' . e(fecha_corta($p['fecha_registro'])) ?></span>
        </div>
    </div>
    <div class="acciones">
        <?php if ($editable): ?>
            <a class="btn btn-principal" href="<?= e(url('mod_empresa/proyecto_form.php?id=' . urlencode($id))) ?>"><?= $p['estado'] === 'devuelto' ? 'Corregir y reenviar' : 'Editar' ?></a>
        <?php elseif ($con_candidatos): ?>
            <a class="btn btn-principal" href="<?= e(url('mod_empresa/candidatos.php?id=' . urlencode($id))) ?>">Ver candidatos</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <ul class="nav nav-tabs">
            <li class="nav-item"><a class="nav-link<?= $pestana === 'info' ? ' active" aria-current="page' : '' ?>" href="<?= e(url('mod_empresa/proyecto.php?id=' . urlencode($id))) ?>">Información</a></li>
            <?php if ($con_candidatos): ?>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('mod_empresa/candidatos.php?id=' . urlencode($id))) ?>">Candidatos <?php if ($num_candidatos): ?><span class="badge rounded-pill text-bg-primary"><?= $num_candidatos ?></span><?php endif; ?></a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link<?= $pestana === 'historial' ? ' active" aria-current="page' : '' ?>" href="<?= e(url('mod_empresa/proyecto.php?id=' . urlencode($id) . '&tab=historial')) ?>">Historial</a></li>
        </ul>
        <div class="panel-pestana">
            <h2 class="visually-hidden"><?= $pestana === 'info' ? 'Información del proyecto' : 'Historial del proyecto' ?></h2>
            <?= $pestana === 'info' ? html_contenido_proyecto($p, requisitos_proyecto($id)) : html_linea_tiempo($eventos) ?>
        </div>
    </div>
    <div class="col-lg-4 d-grid gap-3 align-self-start">
        <?= html_resumen_proyecto($p) ?>
        <div class="card">
            <div class="card-header"><h2 class="h3 mb-0">Último movimiento</h2></div>
            <div class="card-body">
                <p class="small text-mute mb-2"><?= e($nota_estado) ?></p>
                <?= html_linea_tiempo(array_slice($eventos, -1)) ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
