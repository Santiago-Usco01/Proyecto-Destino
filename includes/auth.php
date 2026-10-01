<?php
/**
 * Sesiones, autenticación, roles y protección CSRF.
 */

// ---------------------------------------------------------------- Sesión segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL . '/',
        'httponly' => true,       // JavaScript no puede leer la cookie de sesión
        'samesite' => 'Lax',
    ]);
    session_name('DESTINOSESSID');
    session_start();
}

// ---------------------------------------------------------------- Usuario actual

/** Devuelve el usuario en sesión (id, nombre, email, rol) o null. */
function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function esta_logueado(): bool
{
    return usuario_actual() !== null;
}

function es_admin(): bool
{
    return (usuario_actual()['rol'] ?? null) === 'administrador';
}

/** Guarda al usuario en la sesión tras verificar su contraseña. */
function iniciar_sesion(array $usuario): void
{
    // Nuevo identificador de sesión para evitar la fijación de sesión.
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'     => (int) $usuario['id'],
        'nombre' => $usuario['nombre'],
        'email'  => $usuario['email'],
        'rol'    => $usuario['rol'],
    ];
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    session_destroy();
}

/** Ruta interna segura (sin esquema ni host) para volver tras iniciar sesión; si no, null. */
function destino_seguro(mixed $ruta): ?string
{
    if (!is_string($ruta) || $ruta === '' || strlen($ruta) > 200) {
        return null;
    }
    return preg_match('/^[a-z0-9\-]+(\/[a-z0-9\-]+)*\.php(\?[\w=&%\-]*)?$/i', $ruta) ? $ruta : null;
}

// ---------------------------------------------------------------- Control de acceso

function requerir_login(): void
{
    if (!esta_logueado()) {
        flash('info', 'Inicia sesión para continuar.');
        $actual = ltrim(substr($_SERVER['REQUEST_URI'] ?? '', strlen(BASE_URL)), '/');
        redirigir('login.php' . (destino_seguro($actual) ? '?volver=' . urlencode($actual) : ''));
    }
}

function requerir_admin(): void
{
    if (!es_admin()) {
        flash('error', 'No tienes permiso para acceder a esa sección.');
        redirigir(esta_logueado() ? 'index.php' : 'login.php');
    }
}

// ---------------------------------------------------------------- CSRF

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Campo oculto para incluir dentro de cada <form method="post">. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/** Detiene la petición si el token del formulario no coincide. */
function verificar_csrf(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        die('La solicitud no es válida. Recarga la página e inténtalo de nuevo.');
    }
}

// ---------------------------------------------------------------- Mensajes flash

/** Guarda un mensaje para mostrarlo en la siguiente página (tipo: exito, error, info). */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve y borra los mensajes pendientes. */
function obtener_flash(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}
