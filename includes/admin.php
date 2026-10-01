<?php
/**
 * Utilidades del panel de administración.
 * Cada página de admin/ empieza con:
 *   require __DIR__ . '/../includes/admin.php';   (carga init.php y exige rol administrador)
 */

require_once __DIR__ . '/init.php';
requerir_login();
requerir_admin();

const IMAGEN_MAX_BYTES = 2 * 1024 * 1024;
const IMAGEN_TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/** Clase CSS de la etiqueta según el estado. */
function clase_estado(string $estado): string
{
    return match ($estado) {
        'confirmado', 'confirmada', 'entregado', 'atendida' => 'etiqueta--exito',
        'cancelado', 'cancelada', 'rechazada'               => 'etiqueta--error',
        default                                             => 'etiqueta--aviso',
    };
}

function etiqueta_estado(string $estado, array $catalogo): string
{
    return '<span class="etiqueta ' . clase_estado($estado) . '">' . e($catalogo[$estado] ?? $estado) . '</span>';
}

/** "HH:MM" del formulario → "HH:MM:00" para la BD; null si no es válida. */
function hora_desde_input(mixed $valor): ?string
{
    return is_string($valor) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valor) ? $valor . ':00' : null;
}

/** "HH:MM:SS" de la BD → "HH:MM" para <input type="time">. */
function hora_para_input(?string $hora): string
{
    return $hora ? substr($hora, 0, 5) : '';
}

/** Convierte "12.000" o "$ 12000" en 12000; null si no hay dígitos. */
function entero_desde_texto(mixed $valor): ?int
{
    if (!is_string($valor)) {
        return null;
    }
    $digitos = preg_replace('/\D/', '', $valor);
    return $digitos === '' || strlen($digitos) > 9 ? null : (int) $digitos;
}

/** Id positivo recibido por GET o POST, o null. */
function id_recibido(string $campo = 'id'): ?int
{
    $v = filter_var($_REQUEST[$campo] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $v === false ? null : $v;
}

/**
 * Valida y guarda una imagen de producto subida por el formulario.
 * Devuelve la ruta relativa ("uploads/productos/xxxx.jpg"), null si no se subió nada,
 * o lanza RuntimeException con un mensaje para el usuario.
 */
function guardar_imagen_producto(array $archivo): ?string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        throw new RuntimeException('No se pudo subir la imagen. Inténtalo de nuevo.');
    }
    if ($archivo['size'] > IMAGEN_MAX_BYTES) {
        throw new RuntimeException('La imagen pesa más de 2 MB.');
    }
    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if (!isset(IMAGEN_TIPOS[$tipo]) || @getimagesize($archivo['tmp_name']) === false) {
        throw new RuntimeException('La imagen debe ser JPG, PNG o WebP.');
    }
    $nombre = bin2hex(random_bytes(12)) . '.' . IMAGEN_TIPOS[$tipo];
    if (!move_uploaded_file($archivo['tmp_name'], CARPETA_UPLOADS . '/' . $nombre)) {
        throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
    }
    return 'uploads/productos/' . $nombre;
}

/** Borra una imagen anterior, solo si está dentro de uploads/productos/. */
function borrar_imagen_producto(?string $ruta): void
{
    if ($ruta && preg_match('#^uploads/productos/[a-f0-9]+\.(jpg|png|webp)$#', $ruta)) {
        @unlink(RAIZ . '/' . $ruta);
    }
}
