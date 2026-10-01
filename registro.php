<?php
/**
 * Registro de clientes. Siempre crea cuentas con rol 'cliente'.
 */
require __DIR__ . '/includes/init.php';

$volver = destino_seguro($_GET['volver'] ?? $_POST['volver'] ?? null);

if (esta_logueado()) {
    redirigir('mi-cuenta.php');
}

const CLAVE_MIN = 8;

$valores = ['nombre' => '', 'email' => '', 'telefono' => ''];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $valores = array_merge($valores, array_map(fn($v) => is_string($v) ? $v : '', array_intersect_key($_POST, $valores)));

    $nombre    = limpiar_texto($_POST['nombre'] ?? null, 100);
    $email     = validar_email($_POST['email'] ?? null);
    $tel_crudo = limpiar_texto($_POST['telefono'] ?? null, 20);
    $telefono  = $tel_crudo === null ? null : normalizar_telefono($tel_crudo);
    $clave     = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirma  = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';

    if ($nombre === null || mb_strlen($nombre) < 3) {
        $errores['nombre'] = 'Escribe tu nombre.';
    }
    if ($email === null) {
        $errores['email'] = 'Escribe un correo válido.';
    }
    if ($tel_crudo !== null && $telefono === null) {
        $errores['telefono'] = 'Escribe un número de 10 dígitos, por ejemplo 312 345 6789. Puedes dejarlo vacío.';
    }
    if (strlen($clave) < CLAVE_MIN) {
        $errores['password'] = 'La contraseña debe tener al menos ' . CLAVE_MIN . ' caracteres.';
    } elseif (strlen($clave) > 72) {
        $errores['password'] = 'La contraseña es demasiado larga (máximo 72 caracteres).';
    } elseif ($clave !== $confirma) {
        $errores['password2'] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        $rol = db()->query("SELECT id FROM roles WHERE nombre = 'cliente'")->fetchColumn();
        try {
            db()->prepare('INSERT INTO usuarios (rol_id, nombre, email, telefono, password_hash) VALUES (?, ?, ?, ?, ?)')
                ->execute([$rol, $nombre, $email, $telefono, password_hash($clave, PASSWORD_DEFAULT)]);
            $id = (int) db()->lastInsertId();
            iniciar_sesion(['id' => $id, 'nombre' => $nombre, 'email' => $email, 'rol' => 'cliente']);
            flash('exito', '¡Cuenta creada! Bienvenido, ' . $nombre . '.');
            redirigir($volver ?? 'mi-cuenta.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $errores['email'] = 'Ya existe una cuenta con ese correo. ¿Quieres ingresar?';
        }
    }
}

$titulo_pagina = 'Crear cuenta';
$pagina_actual = 'cuenta';
require __DIR__ . '/includes/header.php';
?>

<section class="cabecera-pagina">
    <div class="contenedor">
        <span class="antetitulo">Tu cuenta</span>
        <h1>Crear cuenta</h1>
        <p class="texto-suave">Guarda tus datos y consulta el estado de tus pedidos y reservas.</p>
    </div>
</section>

<div class="contenedor pagina-formulario">
    <?php if ($errores): ?>
        <div class="aviso aviso--error formulario--angosto" role="alert">Revisa los campos marcados.</div>
    <?php endif; ?>

    <form method="post" class="formulario tarjeta formulario--angosto" novalidate>
        <?= csrf_campo() ?>
        <?php if ($volver): ?><input type="hidden" name="volver" value="<?= e($volver) ?>"><?php endif; ?>

        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="100" required autocomplete="name"
                   value="<?= e($valores['nombre']) ?>"<?= attr_error($errores, 'nombre') ?>>
            <?= mensaje_error($errores, 'nombre') ?>
        </div>
        <div class="campo--doble">
            <div class="campo">
                <label for="email">Correo</label>
                <input type="email" id="email" name="email" maxlength="150" required autocomplete="email"
                       value="<?= e($valores['email']) ?>"<?= attr_error($errores, 'email') ?>>
                <?= mensaje_error($errores, 'email') ?>
            </div>
            <div class="campo">
                <label for="telefono">Teléfono <span class="opcional">(opcional)</span></label>
                <input type="tel" id="telefono" name="telefono" maxlength="20" autocomplete="tel" inputmode="tel"
                       placeholder="312 345 6789" value="<?= e($valores['telefono']) ?>"<?= attr_error($errores, 'telefono') ?>>
                <?= mensaje_error($errores, 'telefono') ?>
            </div>
        </div>
        <div class="campo--doble">
            <div class="campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required minlength="<?= CLAVE_MIN ?>"
                       autocomplete="new-password"<?= attr_error($errores, 'password') ?>>
                <?= mensaje_error($errores, 'password') ?>
            </div>
            <div class="campo">
                <label for="password2">Repite la contraseña</label>
                <input type="password" id="password2" name="password2" required
                       autocomplete="new-password"<?= attr_error($errores, 'password2') ?>>
                <?= mensaje_error($errores, 'password2') ?>
            </div>
        </div>

        <button type="submit" class="boton boton--primario boton--bloque">Crear cuenta</button>
        <p class="texto-suave">¿Ya tienes cuenta? <a href="<?= url('login.php' . ($volver ? '?volver=' . urlencode($volver) : '')) ?>">Ingresa</a></p>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
