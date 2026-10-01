<?php
/**
 * Mi cuenta: datos personales, cambio de contraseña e historial de pedidos y reservas.
 */
require __DIR__ . '/includes/init.php';
requerir_login();

$id = usuario_actual()['id'];

$consulta = db()->prepare('SELECT nombre, email, telefono FROM usuarios WHERE id = ? AND activo = 1');
$consulta->execute([$id]);
$perfil = $consulta->fetch();
if (!$perfil) {   // cuenta desactivada mientras había sesión
    cerrar_sesion();
    session_start();
    flash('error', 'Tu cuenta no está disponible.');
    redirigir('login.php');
}

$errores = [];
$valores = ['nombre' => $perfil['nombre'], 'telefono' => $perfil['telefono'] ?? ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        $valores = ['nombre' => (string) ($_POST['nombre'] ?? ''), 'telefono' => (string) ($_POST['telefono'] ?? '')];
        $nombre    = limpiar_texto($_POST['nombre'] ?? null, 100);
        $tel_crudo = limpiar_texto($_POST['telefono'] ?? null, 20);
        $telefono  = $tel_crudo === null ? null : normalizar_telefono($tel_crudo);

        if ($nombre === null || mb_strlen($nombre) < 3) {
            $errores['nombre'] = 'Escribe tu nombre.';
        }
        if ($tel_crudo !== null && $telefono === null) {
            $errores['telefono'] = 'Escribe un número de 10 dígitos, por ejemplo 312 345 6789.';
        }
        if (!$errores) {
            db()->prepare('UPDATE usuarios SET nombre = ?, telefono = ? WHERE id = ?')->execute([$nombre, $telefono, $id]);
            $_SESSION['usuario']['nombre'] = $nombre;
            flash('exito', 'Datos actualizados.');
            redirigir('mi-cuenta.php');
        }
    } elseif ($accion === 'clave') {
        $actual   = is_string($_POST['actual'] ?? null) ? $_POST['actual'] : '';
        $nueva    = is_string($_POST['nueva'] ?? null) ? $_POST['nueva'] : '';
        $confirma = is_string($_POST['nueva2'] ?? null) ? $_POST['nueva2'] : '';

        $hash = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
        $hash->execute([$id]);
        if (!password_verify($actual, (string) $hash->fetchColumn())) {
            $errores['actual'] = 'La contraseña actual no es correcta.';
        }
        if (strlen($nueva) < 8 || strlen($nueva) > 72) {
            $errores['nueva'] = 'La nueva contraseña debe tener entre 8 y 72 caracteres.';
        } elseif ($nueva !== $confirma) {
            $errores['nueva2'] = 'Las contraseñas no coinciden.';
        }
        if (!$errores) {
            db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($nueva, PASSWORD_DEFAULT), $id]);
            session_regenerate_id(true);
            flash('exito', 'Contraseña actualizada.');
            redirigir('mi-cuenta.php');
        }
    }
}

$pedidos = db()->prepare('SELECT p.id, p.subtotal, p.total, p.estado, p.created_at, s.nombre AS sede
                          FROM pedidos p JOIN sedes s ON s.id = p.sede_id
                          WHERE p.usuario_id = ? ORDER BY p.created_at DESC LIMIT 10');
$pedidos->execute([$id]);
$pedidos = $pedidos->fetchAll();

$reservas = db()->prepare('SELECT r.id, r.fecha, r.hora, r.num_personas, r.estado, s.nombre AS sede
                           FROM reservas r JOIN sedes s ON s.id = r.sede_id
                           WHERE r.usuario_id = ? ORDER BY r.fecha DESC, r.hora DESC LIMIT 10');
$reservas->execute([$id]);
$reservas = $reservas->fetchAll();

$etiqueta = fn(string $estado, array $catalogo): string => $catalogo[$estado] ?? ucfirst(str_replace('_', ' ', $estado));

$titulo_pagina = 'Mi cuenta';
$pagina_actual = 'cuenta';
require __DIR__ . '/includes/header.php';
?>

<section class="cabecera-pagina">
    <div class="contenedor">
        <span class="antetitulo">Tu cuenta</span>
        <h1>Hola, <?= e($perfil['nombre']) ?></h1>
        <p class="texto-suave"><?= e($perfil['email']) ?></p>
    </div>
</section>

<div class="contenedor pagina-formulario">
    <form method="post" class="formulario tarjeta formulario--angosto" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="datos">
        <h2>Mis datos</h2>
        <div class="campo--doble">
            <div class="campo">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" maxlength="100" required autocomplete="name"
                       value="<?= e($valores['nombre']) ?>"<?= attr_error($errores, 'nombre') ?>>
                <?= mensaje_error($errores, 'nombre') ?>
            </div>
            <div class="campo">
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="20" autocomplete="tel" inputmode="tel"
                       placeholder="312 345 6789" value="<?= e($valores['telefono']) ?>"<?= attr_error($errores, 'telefono') ?>>
                <?= mensaje_error($errores, 'telefono') ?>
            </div>
        </div>
        <button type="submit" class="boton boton--primario">Guardar datos</button>
    </form>

    <form method="post" class="formulario tarjeta formulario--angosto" novalidate style="margin-top:24px">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="clave">
        <h2>Cambiar contraseña</h2>
        <div class="campo">
            <label for="actual">Contraseña actual</label>
            <input type="password" id="actual" name="actual" required autocomplete="current-password"<?= attr_error($errores, 'actual') ?>>
            <?= mensaje_error($errores, 'actual') ?>
        </div>
        <div class="campo--doble">
            <div class="campo">
                <label for="nueva">Nueva contraseña</label>
                <input type="password" id="nueva" name="nueva" required minlength="8" autocomplete="new-password"<?= attr_error($errores, 'nueva') ?>>
                <?= mensaje_error($errores, 'nueva') ?>
            </div>
            <div class="campo">
                <label for="nueva2">Repite la nueva</label>
                <input type="password" id="nueva2" name="nueva2" required autocomplete="new-password"<?= attr_error($errores, 'nueva2') ?>>
                <?= mensaje_error($errores, 'nueva2') ?>
            </div>
        </div>
        <button type="submit" class="boton boton--primario">Cambiar contraseña</button>
    </form>

    <section class="tarjeta formulario--angosto" style="margin-top:24px;padding:24px">
        <h2>Mis pedidos</h2>
        <?php if (!$pedidos): ?>
            <p class="texto-suave">Aún no tienes pedidos. <a href="<?= url('menu.php') ?>">Ver el menú</a></p>
        <?php else: ?>
            <dl class="resumen__filas">
                <?php foreach ($pedidos as $p): ?>
                    <div>
                        <dt><?= e(codigo_pedido((int) $p['id'])) ?> · <?= e($p['sede']) ?> · <?= e(fecha_legible(substr($p['created_at'], 0, 10))) ?></dt>
                        <dd><?= $p['total'] === null ? e(precio((int) $p['subtotal'])) . ' + domicilio' : e(precio((int) $p['total'])) ?> · <?= e($etiqueta($p['estado'], ESTADOS_PEDIDO)) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </section>

    <section class="tarjeta formulario--angosto" style="margin-top:24px;padding:24px">
        <h2>Mis reservas</h2>
        <?php if (!$reservas): ?>
            <p class="texto-suave">Aún no tienes reservas. <a href="<?= url('reservas.php') ?>">Reservar una mesa</a></p>
        <?php else: ?>
            <dl class="resumen__filas">
                <?php foreach ($reservas as $r): ?>
                    <div>
                        <dt><?= e(codigo_reserva((int) $r['id'])) ?> · <?= e($r['sede']) ?> · <?= e(fecha_legible($r['fecha'])) ?>, <?= e(hora_legible($r['hora'])) ?></dt>
                        <dd><?= (int) $r['num_personas'] ?> pers. · <?= e($etiqueta($r['estado'], ESTADOS_RESERVA)) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
