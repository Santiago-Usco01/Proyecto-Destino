<?php
/**
 * Funciones de uso general: escape, formato, rutas, horarios, validación y WhatsApp.
 */

// ---------------------------------------------------------------- Salida y formato

/** Escapa texto para imprimirlo en HTML (evita XSS). Usar en TODA salida de datos. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 18000 → "$18.000" */
function precio(int $valor): string
{
    return '$' . number_format($valor, 0, ',', '.');
}

/** 123 → "DST-000123" */
function codigo_pedido(int $id): string
{
    return sprintf('DST-%06d', $id);
}

/** 7 → "RES-000007" */
function codigo_reserva(int $id): string
{
    return sprintf('RES-%06d', $id);
}

/** "2026-10-04" → "sábado 4 de octubre de 2026" */
function fecha_legible(string $fecha): string
{
    $f = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$f) {
        return $fecha;
    }
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
              'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $dias[(int) $f->format('w')] . ' ' . $f->format('j') . ' de '
         . $meses[(int) $f->format('n') - 1] . ' de ' . $f->format('Y');
}

/** Fecha de hoy + $dias en formato "Y-m-d". */
function fecha_mas_dias(int $dias): string
{
    return (new DateTime('today'))->modify("+$dias days")->format('Y-m-d');
}

/** "16:00:00" → "4:00 p. m." */
function hora_legible(string $hora): string
{
    $h = DateTime::createFromFormat('H:i:s', $hora) ?: DateTime::createFromFormat('H:i', $hora);
    if (!$h) {
        return $hora;
    }
    return $h->format('g:i') . ($h->format('H') < 12 ? ' a. m.' : ' p. m.');
}

// ---------------------------------------------------------------- Rutas

/** Ruta absoluta dentro del proyecto: url('menu.php') → "/destino/menu.php" */
function url(string $ruta = ''): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

function redirigir(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

/** Imagen de un producto, o la imagen genérica si no tiene. */
function imagen_producto(?string $imagen): string
{
    return $imagen ? url($imagen) : url('assets/img/producto-generico.svg');
}

// ---------------------------------------------------------------- Horarios

/** Hora actual "HH:MM:SS" en Neiva (o la hora de prueba en modo desarrollo). */
function hora_actual(): string
{
    return MODO_DESARROLLO && HORA_PRUEBA !== null ? HORA_PRUEBA : date('H:i:s');
}

/** ¿La hora actual (o $ahora, "HH:MM:SS") está entre $inicio y $fin? */
function en_franja(string $inicio, string $fin, ?string $ahora = null): bool
{
    $ahora ??= hora_actual();
    return $ahora >= $inicio && $ahora < $fin;
}

function local_abierto(): bool
{
    return en_franja(HORA_APERTURA, HORA_CIERRE);
}

/** "Abierto ahora · hasta las 10:00 p. m." o "Cerrado ahora · abrimos a las 7:00 a. m." */
function estado_local(): string
{
    return local_abierto()
        ? 'Abierto ahora · hasta las ' . hora_legible(HORA_CIERRE)
        : 'Cerrado ahora · abrimos a las ' . hora_legible(HORA_APERTURA);
}

/**
 * Motivo por el que un producto NO se puede pedir ahora, o null si sí se puede.
 * $producto debe traer: activo, disponible_domicilio, hora_inicio y hora_fin
 * efectivas (las del producto si existen; si no, las de su categoría).
 */
function motivo_no_pedible(array $producto): ?string
{
    if (!$producto['activo']) {
        return 'Este producto ya no está disponible';
    }
    if (!$producto['disponible_domicilio']) {
        return 'Solo para consumo en el local';
    }
    if (!local_abierto()) {
        return 'El local está cerrado · abrimos a las ' . hora_legible(HORA_APERTURA);
    }
    if (!en_franja($producto['hora_inicio'], $producto['hora_fin'])) {
        return 'Disponible de ' . hora_legible($producto['hora_inicio']) . ' a ' . hora_legible($producto['hora_fin']);
    }
    return null;
}

function producto_pedible(array $producto): bool
{
    return motivo_no_pedible($producto) === null;
}

/**
 * Fragmento SQL con la franja efectiva de un producto.
 * Uso: SELECT p.*, " . SQL_FRANJA_PRODUCTO . " FROM productos p JOIN categorias c ...
 */
const SQL_FRANJA_PRODUCTO = 'COALESCE(p.hora_inicio, c.hora_inicio) AS hora_inicio,
                             COALESCE(p.hora_fin, c.hora_fin) AS hora_fin';

// ---------------------------------------------------------------- Validación

/** Recorta espacios y limita longitud. Devuelve null si queda vacío. */
function limpiar_texto(mixed $valor, int $max): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $valor = trim(preg_replace('/\s+/u', ' ', $valor));
    if ($valor === '') {
        return null;
    }
    return mb_substr($valor, 0, $max);
}

/** Texto largo (observaciones): conserva saltos de línea. */
function limpiar_texto_largo(mixed $valor, int $max): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $valor = trim(str_replace("\r\n", "\n", $valor));
    return $valor === '' ? null : mb_substr($valor, 0, $max);
}

/**
 * Normaliza un teléfono colombiano: acepta espacios, guiones y +57.
 * Devuelve los 10 dígitos o null si no es válido.
 */
function normalizar_telefono(mixed $valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $digitos = preg_replace('/\D/', '', $valor);
    if (strlen($digitos) === 12 && str_starts_with($digitos, '57')) {
        $digitos = substr($digitos, 2);
    }
    return preg_match('/^[36]\d{9}$/', $digitos) ? $digitos : null;
}

function validar_email(mixed $valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $valor = trim(mb_strtolower($valor));
    return filter_var($valor, FILTER_VALIDATE_EMAIL) && mb_strlen($valor) <= 150 ? $valor : null;
}

/** Atributos de accesibilidad para un campo de formulario con error. */
function attr_error(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' aria-invalid="true" aria-describedby="error-' . $campo . '"' : '';
}

/** Mensaje de error debajo de un campo de formulario. */
function mensaje_error(array $errores, string $campo): string
{
    return isset($errores[$campo])
        ? '<p class="error-campo" id="error-' . $campo . '">' . e($errores[$campo]) . '</p>'
        : '';
}

/** "3123760524" → "312 376 0524" */
function telefono_legible(string $telefono): string
{
    return preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1 $2 $3', $telefono);
}

// ---------------------------------------------------------------- WhatsApp

/** Enlace wa.me con un mensaje ya escrito. $numero en formato 57XXXXXXXXXX. */
function enlace_whatsapp(string $numero, string $mensaje = ''): string
{
    $enlace = 'https://wa.me/' . preg_replace('/\D/', '', $numero);
    return $mensaje === '' ? $enlace : $enlace . '?text=' . rawurlencode($mensaje);
}

// ---------------------------------------------------------------- Consultas comunes

/** Sedes activas, para formularios, pie de página y cotizaciones. */
function sedes_activas(): array
{
    return db()->query('SELECT id, nombre, direccion, telefono, whatsapp, horario, url_mapa
                        FROM sedes WHERE activo = 1 ORDER BY id')->fetchAll();
}

// Estados de pedidos y reservas (etiquetas para el cliente y el panel).
const ESTADOS_PEDIDO = [
    'pendiente_confirmacion' => 'Pendiente de confirmación',
    'confirmado'             => 'Confirmado',
    'en_preparacion'         => 'En preparación',
    'en_camino'              => 'En camino',
    'entregado'              => 'Entregado',
    'cancelado'              => 'Cancelado',
];

const ESTADOS_RESERVA = [
    'pendiente'  => 'Pendiente',
    'confirmada' => 'Confirmada',
    'rechazada'  => 'Rechazada',
    'cancelada'  => 'Cancelada',
    'atendida'   => 'Atendida',
];
