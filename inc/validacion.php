<?php
/**
 * Validaciones comunes. Cada función devuelve null si el dato es válido
 * o el mensaje de error para mostrar junto al campo.
 */

const SECTORES_EMPRESA = ['Logística', 'Salud', 'Software', 'Comercio', 'Manufactura', 'Gobierno', 'Educación', 'Otro'];

/** Recorta espacios y colapsa espacios internos. */
function limpiar(?string $texto): string
{
    return trim(preg_replace('/\s+/u', ' ', (string) $texto));
}

function validar_requerido(string $valor, string $que = 'Este campo'): ?string
{
    return $valor === '' ? "$que es obligatorio." : null;
}

function validar_contrasena(string $pw, string $confirmacion): ?string
{
    if (strlen($pw) < 8 || !preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'La contraseña debe tener al menos 8 caracteres, con mayúscula, minúscula y número.';
    }
    if (strlen($pw) > 72) {
        return 'La contraseña no puede tener más de 72 caracteres.';
    }
    if ($pw !== $confirmacion) {
        return 'Las contraseñas no coinciden.';
    }
    return null;
}

/** Teléfono opcional: 10 dígitos (la base acepta de 10 a 15). */
function validar_telefono(string $tel): ?string
{
    if ($tel === '') {
        return null;
    }
    return preg_match('/^\d{10,15}$/', $tel) ? null : 'Escribe el teléfono con 10 dígitos, sin espacios ni guiones.';
}

function validar_correo(string $correo): ?string
{
    if ($correo === '') {
        return 'El correo es obligatorio.';
    }
    if (strlen($correo) > 254 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return 'Escribe un correo válido, por ejemplo nombre@empresa.com.';
    }
    return null;
}

/** RFC del SAT: 3 letras (moral) o 4 (física), 6 dígitos de fecha y 3 de homoclave. */
function validar_rfc(string $rfc): ?string
{
    if ($rfc === '') {
        return 'El RFC es obligatorio.';
    }
    if (strlen($rfc) < 12 || strlen($rfc) > 13) {
        return 'El RFC debe tener 12 o 13 caracteres.';
    }
    if (!preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc)) {
        return 'El RFC no tiene el formato del SAT (letras, fecha y homoclave).';
    }
    return null;
}

function correo_registrado(string $correo, ?string $excepto_usuario = null): bool
{
    $s = conexion()->prepare('SELECT COUNT(*) FROM usuario WHERE correo = ? AND c_usuario <> ?');
    $s->execute([$correo, $excepto_usuario ?? '']);
    return (int) $s->fetchColumn() > 0;
}
