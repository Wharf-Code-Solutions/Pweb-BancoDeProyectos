<?php
/**
 * Componentes de formulario con el marcado de las pantallas:
 * etiqueta, marca de obligatorio, texto de ayuda y mensaje de error.
 *
 *   echo campo('rfc', 'RFC', $datos['rfc'], [
 *       'requerido' => true, 'ayuda' => 'Persona moral 12 caracteres…',
 *       'error' => $errores['rfc'] ?? null, 'maxlength' => 13,
 *   ]);
 *
 * Opciones: tipo, requerido, opcional (muestra "(opcional)"), ayuda, error,
 * solo_lectura, maxlength, autocomplete, clase_contenedor.
 */

function campo(string $nombre, string $etiqueta, ?string $valor = '', array $o = []): string
{
    $tipo = $o['tipo'] ?? 'text';
    $id = $o['id'] ?? $nombre;
    $describe = [];
    $extra = '';

    $html = '<div class="' . e($o['clase_contenedor'] ?? '') . '">';
    $html .= '<label class="form-label" for="' . e($id) . '">' . e($etiqueta);
    if (!empty($o['requerido'])) {
        $html .= '<span class="req" aria-hidden="true">*</span>';
    } elseif (!empty($o['opcional'])) {
        $html .= ' <span class="fw-normal text-mute">(opcional)</span>';
    }
    $html .= '</label>';

    $ayuda = '';
    if (!empty($o['ayuda'])) {
        $describe[] = $id . '-a';
        $ayuda = '<div class="form-text" id="' . e($id) . '-a">' . e($o['ayuda']) . '</div>';
    }
    $error = '';
    if (!empty($o['error'])) {
        $describe[] = $id . '-e';
        $error = '<div class="invalid-feedback" id="' . e($id) . '-e"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>' . e($o['error']) . '</div>';
        $extra .= ' aria-invalid="true"';
    }
    if ($describe) {
        $extra .= ' aria-describedby="' . e(implode(' ', $describe)) . '"';
    }
    if (!empty($o['requerido'])) {
        $extra .= ' required';
    }
    if (!empty($o['solo_lectura'])) {
        $extra .= ' readonly';
    }
    if (!empty($o['maxlength'])) {
        $extra .= ' maxlength="' . (int) $o['maxlength'] . '"';
    }
    if (!empty($o['autocomplete'])) {
        $extra .= ' autocomplete="' . e($o['autocomplete']) . '"';
    }

    $clase = 'form-control' . (!empty($o['error']) ? ' is-invalid' : '');
    $valor_attr = $tipo === 'password' ? '' : ' value="' . e($valor) . '"';
    $html .= '<input class="' . $clase . '" id="' . e($id) . '" name="' . e($nombre) . '" type="' . e($tipo) . '"' . $valor_attr . $extra . '>';

    return $html . $ayuda . $error . '</div>';
}

/** Lista desplegable. $opciones: ['valor' => 'Texto'] o lista simple. */
function campo_select(string $nombre, string $etiqueta, ?string $valor, array $opciones, array $o = []): string
{
    $id = $o['id'] ?? $nombre;
    $es_lista = array_is_list($opciones);
    $html = '<div class="' . e($o['clase_contenedor'] ?? '') . '">';
    $html .= '<label class="form-label" for="' . e($id) . '">' . e($etiqueta)
           . (!empty($o['requerido']) ? '<span class="req" aria-hidden="true">*</span>' : '') . '</label>';
    $invalido = !empty($o['error']);
    $html .= '<select class="form-select' . ($invalido ? ' is-invalid' : '') . '" id="' . e($id) . '" name="' . e($nombre) . '"'
           . (!empty($o['requerido']) ? ' required' : '')
           . ($invalido ? ' aria-invalid="true" aria-describedby="' . e($id) . '-e"' : '') . '>';
    $html .= '<option value="">Selecciona…</option>';
    foreach ($opciones as $k => $texto) {
        $v = $es_lista ? $texto : (string) $k;
        $html .= '<option value="' . e($v) . '"' . ($v === $valor ? ' selected' : '') . '>' . e($texto) . '</option>';
    }
    $html .= '</select>';
    if ($invalido) {
        $html .= '<div class="invalid-feedback" id="' . e($id) . '-e"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>' . e($o['error']) . '</div>';
    }
    return $html . '</div>';
}

/** Alerta general cuando el formulario tiene errores. */
function alerta_errores(array $errores, string $mensaje = 'Algún dato no tiene el formato correcto. Revisa los campos marcados.'): string
{
    if (!$errores) {
        return '';
    }
    $general = $errores['_general'] ?? $mensaje;
    return '<div class="alert alert-danger" role="alert"><i class="bi bi-x-octagon-fill" aria-hidden="true"></i><div class="alert-cuerpo">' . e($general) . '</div></div>';
}
