<?php
/**
 * Inicio de sesión.
 * Limita los intentos fallidos por sesión y no revela si el correo existe.
 */
require __DIR__ . '/includes/init.php';

$volver = destino_seguro($_GET['volver'] ?? $_POST['volver'] ?? null);

if (esta_logueado()) {
    redirigir($volver ?? (es_admin() ? 'admin/index.php' : 'mi-cuenta.php'));
}

const LOGIN_MAX_INTENTOS = 5;
const LOGIN_BLOQUEO_SEG  = 300;
// Hash de relleno: se verifica cuando el correo no existe para no filtrarlo por tiempo de respuesta.
const HASH_RELLENO = '$2y$10$abcdefghijklmnopqrstuuYhQ1p5Z0m3q6yWnq3b5eJ8nXvR2sT4K';

$errores = [];
$email_valor = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $email_valor = limpiar_texto($_POST['email'] ?? null, 150) ?? '';
    $email = validar_email($email_valor);
    $clave = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (($_SESSION['login_bloqueo'] ?? 0) > time()) {
        $errores['general'] = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
    } else {
        $usuario_bd = null;
        if ($email !== null) {
            $consulta = db()->prepare('SELECT u.id, u.nombre, u.email, u.password_hash, u.activo, r.nombre AS rol
                                       FROM usuarios u JOIN roles r ON r.id = u.rol_id
                                       WHERE u.email = ?');
            $consulta->execute([$email]);
            $usuario_bd = $consulta->fetch() ?: null;
        }

        $correcta = password_verify($clave, $usuario_bd['password_hash'] ?? HASH_RELLENO);

        if ($usuario_bd && $correcta && (int) $usuario_bd['activo'] === 1) {
            if (password_needs_rehash($usuario_bd['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($clave, PASSWORD_DEFAULT), $usuario_bd['id']]);
            }
            iniciar_sesion($usuario_bd);
            unset($_SESSION['login_fallos'], $_SESSION['login_bloqueo']);
            flash('exito', 'Bienvenido, ' . $usuario_bd['nombre'] . '.');
            redirigir($volver ?? ($usuario_bd['rol'] === 'administrador' ? 'admin/index.php' : 'mi-cuenta.php'));
        }

        $_SESSION['login_fallos'] = ($_SESSION['login_fallos'] ?? 0) + 1;
        if ($_SESSION['login_fallos'] >= LOGIN_MAX_INTENTOS) {
            $_SESSION['login_bloqueo'] = time() + LOGIN_BLOQUEO_SEG;
            $_SESSION['login_fallos'] = 0;
        }
        $errores['general'] = 'Correo o contraseña incorrectos.';
    }
}

$titulo_pagina = 'Ingresar';
$pagina_actual = 'cuenta';
require __DIR__ . '/includes/header.php';
?>

<section class="cabecera-pagina">
    <div class="contenedor">
        <span class="antetitulo">Tu cuenta</span>
        <h1>Ingresar</h1>
        <p class="texto-suave">Entra para ver tus pedidos y reservas y agilizar tus solicitudes.</p>
    </div>
</section>

<div class="contenedor pagina-formulario">
    <?php if (isset($errores['general'])): ?>
        <div class="aviso aviso--error formulario--angosto" role="alert"><?= e($errores['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="formulario tarjeta formulario--angosto" novalidate>
        <?= csrf_campo() ?>
        <?php if ($volver): ?><input type="hidden" name="volver" value="<?= e($volver) ?>"><?php endif; ?>

        <div class="campo">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" maxlength="150" required autocomplete="email"
                   value="<?= e($email_valor) ?>">
        </div>
        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>

        <button type="submit" class="boton boton--primario boton--bloque">Ingresar</button>
        <p class="texto-suave">¿No tienes cuenta? <a href="<?= url('registro.php' . ($volver ? '?volver=' . urlencode($volver) : '')) ?>">Regístrate</a></p>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
