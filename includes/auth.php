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

// ---------------------------------------------------------------- Límite de intentos (login y registro)
// Tabla intentos_login: la columna tipo separa los fallos de login de los registros.

const LOGIN_MAX_POR_EMAIL = 5;     // fallos permitidos por correo dentro de la ventana
const LOGIN_MAX_POR_IP    = 20;    // fallos permitidos por IP dentro de la ventana
const LOGIN_VENTANA_MIN   = 15;    // minutos que cuenta cada fallo

const REGISTRO_MAX_POR_IP  = 5;    // cuentas (o intentos con correo repetido) por IP dentro de la ventana
const REGISTRO_VENTANA_MIN = 60;

/** Cuenta los intentos de un tipo hechos por una IP o un correo desde hace $minutos. */
function contar_intentos(string $tipo, string $campo, string $valor, int $minutos): int
{
    $consulta = db()->prepare("SELECT COUNT(*) FROM intentos_login WHERE tipo = ? AND $campo = ? AND created_at >= ?");
    $consulta->execute([$tipo, $valor, date('Y-m-d H:i:s', time() - $minutos * 60)]);
    return (int) $consulta->fetchColumn();
}

/** Guarda un intento y, de vez en cuando, purga los de más de un día. */
function registrar_intento(string $tipo, ?string $email): void
{
    db()->prepare('INSERT INTO intentos_login (tipo, email, ip, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$tipo, $email, $_SERVER['REMOTE_ADDR'] ?? '', date('Y-m-d H:i:s')]);
    if (random_int(1, 20) === 1) {
        db()->prepare('DELETE FROM intentos_login WHERE created_at < ?')
            ->execute([date('Y-m-d H:i:s', time() - 86400)]);
    }
}

/** True si el correo o la IP superaron el límite de fallos de login recientes. */
function login_bloqueado(?string $email): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (contar_intentos('login', 'ip', $ip, LOGIN_VENTANA_MIN) >= LOGIN_MAX_POR_IP) {
        return true;
    }
    return $email !== null
        && contar_intentos('login', 'email', $email, LOGIN_VENTANA_MIN) >= LOGIN_MAX_POR_EMAIL;
}

function login_registrar_fallo(?string $email): void
{
    registrar_intento('login', $email);
}

/** Borra los fallos de un correo tras un inicio de sesión correcto. */
function login_limpiar(string $email): void
{
    db()->prepare("DELETE FROM intentos_login WHERE tipo = 'login' AND email = ?")->execute([$email]);
}

/** True si esta IP ya creó demasiadas cuentas en la última hora. */
function registro_bloqueado(): bool
{
    return contar_intentos('registro', 'ip', $_SERVER['REMOTE_ADDR'] ?? '', REGISTRO_VENTANA_MIN) >= REGISTRO_MAX_POR_IP;
}

function registro_registrar_intento(?string $email): void
{
    registrar_intento('registro', $email);
}

// ---------------------------------------------------------------- Control de acceso

/**
 * Comprueba en la BD que el usuario de la sesión siga activo y refresca su rol,
 * para que desactivar o cambiar de rol a una cuenta surta efecto de inmediato.
 */
function refrescar_usuario_sesion(): void
{
    $u = usuario_actual();
    if ($u === null) {
        return;
    }
    $consulta = db()->prepare('SELECT u.nombre, u.email, u.activo, r.nombre AS rol
                               FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?');
    $consulta->execute([$u['id']]);
    $bd = $consulta->fetch();
    if (!$bd || (int) $bd['activo'] !== 1) {
        unset($_SESSION['usuario']);
        flash('error', 'Tu cuenta está desactivada.');
        return;
    }
    $_SESSION['usuario'] = ['id' => $u['id'], 'nombre' => $bd['nombre'], 'email' => $bd['email'], 'rol' => $bd['rol']];
}

function requerir_login(): void
{
    refrescar_usuario_sesion();
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
