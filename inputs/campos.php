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
 * req_envio (marca ** de obligatorio para enviar), solo_lectura, maxlength, minlength,
 * min, max, step, placeholder, pattern (con title que explica el formato), inputmode,
 * autocomplete, clase_contenedor.
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
    } elseif (!empty($o['req_envio'])) {
        $html .= '<span class="req-envio" aria-hidden="true">**</span>';
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
    if (!empty($o['minlength'])) {
        $extra .= ' minlength="' . (int) $o['minlength'] . '"';
    }
    foreach (['min', 'max', 'step', 'placeholder', 'pattern', 'title', 'inputmode'] as $attr) {
        if (isset($o[$attr])) {
            $extra .= ' ' . $attr . '="' . e((string) $o[$attr]) . '"';
        }
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
           . (!empty($o['requerido']) ? '<span class="req" aria-hidden="true">*</span>'
              : (!empty($o['req_envio']) ? '<span class="req-envio" aria-hidden="true">**</span>' : '')) . '</label>';
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

/** Área de texto. Opciones: requerido, opcional, ayuda, error, filas, maxlength, minlength, placeholder, clase_contenedor. */
function campo_area(string $nombre, string $etiqueta, ?string $valor, array $o = []): string
{
    $id = $o['id'] ?? $nombre;
    $html = '<div class="' . e($o['clase_contenedor'] ?? '') . '">';
    $html .= '<label class="form-label" for="' . e($id) . '">' . e($etiqueta)
           . (!empty($o['requerido']) ? '<span class="req" aria-hidden="true">*</span>'
              : (!empty($o['req_envio']) ? '<span class="req-envio" aria-hidden="true">**</span>'
              : (!empty($o['opcional']) ? ' <span class="fw-normal text-mute">(opcional)</span>' : ''))) . '</label>';
    $invalido = !empty($o['error']);
    $describe = array_filter([!empty($o['ayuda']) ? $id . '-a' : null, $invalido ? $id . '-e' : null]);
    $html .= '<textarea class="form-control' . ($invalido ? ' is-invalid' : '') . '" id="' . e($id) . '" name="' . e($nombre) . '"'
           . ' rows="' . (int) ($o['filas'] ?? 3) . '"'
           . (!empty($o['maxlength']) ? ' maxlength="' . (int) $o['maxlength'] . '"' : '')
           . (!empty($o['minlength']) ? ' minlength="' . (int) $o['minlength'] . '"' : '')
           . (isset($o['placeholder']) ? ' placeholder="' . e($o['placeholder']) . '"' : '')
           . (!empty($o['requerido']) ? ' required' : '')
           . ($describe ? ' aria-describedby="' . e(implode(' ', $describe)) . '"' : '')
           . ($invalido ? ' aria-invalid="true"' : '') . '>' . e($valor) . '</textarea>';
    if (!empty($o['ayuda'])) {
        $html .= '<div class="form-text" id="' . e($id) . '-a">' . e($o['ayuda']) . '</div>';
    }
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
