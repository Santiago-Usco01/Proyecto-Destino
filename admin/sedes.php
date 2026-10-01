<?php
/**
 * Sedes: listado y edición (teléfono, WhatsApp, horario, mapa). ?id=N muestra el formulario.
 * No se crean ni borran sedes desde el panel.
 */
require __DIR__ . '/../includes/admin.php';

$id = id_recibido();
$sede = null;
if ($id) {
    $consulta = db()->prepare('SELECT * FROM sedes WHERE id = ?');
    $consulta->execute([$id]);
    $sede = $consulta->fetch();
    if (!$sede) {
        flash('error', 'La sede no existe.');
        redirigir('admin/sedes.php');
    }
}

$errores = [];
$valores = $sede ? array_intersect_key($sede, array_flip(['nombre', 'direccion', 'telefono', 'whatsapp', 'horario', 'url_mapa'])) : [];
$valores['activo'] = $sede ? (int) $sede['activo'] : 1;

if ($sede && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    foreach (['nombre', 'direccion', 'telefono', 'whatsapp', 'horario', 'url_mapa'] as $campo) {
        $valores[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }
    $valores['activo'] = isset($_POST['activo']) ? 1 : 0;

    $nombre    = limpiar_texto($_POST['nombre'] ?? null, 80);
    $direccion = limpiar_texto($_POST['direccion'] ?? null, 200);
    $telefono  = normalizar_telefono($_POST['telefono'] ?? null);
    $whatsapp  = normalizar_telefono($_POST['whatsapp'] ?? null);
    $horario   = limpiar_texto($_POST['horario'] ?? null, 255);
    $mapa      = limpiar_texto($_POST['url_mapa'] ?? null, 500);

    if ($nombre === null)    { $errores['nombre'] = 'Escribe el nombre.'; }
    if ($direccion === null) { $errores['direccion'] = 'Escribe la dirección.'; }
    if ($telefono === null)  { $errores['telefono'] = 'Escribe un número de 10 dígitos.'; }
    if ($whatsapp === null)  { $errores['whatsapp'] = 'Escribe un número de 10 dígitos (se le antepone 57).'; }
    if ($horario === null)   { $errores['horario'] = 'Escribe el horario.'; }
    if ($mapa !== null && !preg_match('#^https://#i', $mapa)) {
        $errores['url_mapa'] = 'El enlace debe empezar por https://';
    }

    if (!$errores) {
        try {
            db()->prepare('UPDATE sedes SET nombre = ?, direccion = ?, telefono = ?, whatsapp = ?, horario = ?, url_mapa = ?, activo = ? WHERE id = ?')
                ->execute([$nombre, $direccion, $telefono, '57' . $whatsapp, $horario, $mapa, $valores['activo'], $id]);
            flash('exito', 'Sede actualizada.');
            redirigir('admin/sedes.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $errores['nombre'] = 'Ya existe una sede con ese nombre.';
        }
    }
}

// WhatsApp se guarda como 57XXXXXXXXXX; en el formulario se muestran solo los 10 dígitos.
if ($sede && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $valores['whatsapp'] = preg_replace('/^57/', '', $valores['whatsapp']);
}

$titulo_pagina = 'Sedes';
$seccion = 'sedes';

if (!$sede) {
    $sedes = db()->query('SELECT * FROM sedes ORDER BY id')->fetchAll();
    require __DIR__ . '/../includes/admin-header.php';
    ?>
    <div class="admin__titulo"><h1>Sedes</h1></div>
    <div class="tabla-envoltorio"><table class="tabla">
        <thead><tr><th>Sede</th><th>Contacto</th><th>Horario</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($sedes as $s): ?>
            <tr class="<?= $s['activo'] ? '' : 'inactivo' ?>">
                <td><?= e($s['nombre']) ?><br><small><?= e($s['direccion']) ?></small></td>
                <td>Tel. <?= e(telefono_legible($s['telefono'])) ?><br><small>WhatsApp +<?= e($s['whatsapp']) ?></small></td>
                <td><?= e($s['horario']) ?></td>
                <td><?= $s['activo'] ? '<span class="etiqueta etiqueta--exito">Activa</span>' : '<span class="etiqueta etiqueta--error">Inactiva</span>' ?></td>
                <td><a class="boton boton--secundario boton--pequeno" href="<?= url('admin/sedes.php?id=' . (int) $s['id']) ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php
    require __DIR__ . '/../includes/admin-footer.php';
    exit;
}

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin__titulo">
    <h1>Editar sede</h1>
    <a href="<?= url('admin/sedes.php') ?>" class="boton boton--secundario boton--pequeno">← Volver</a>
</div>

<?php if ($errores): ?>
    <div class="aviso aviso--error" role="alert">Revisa los campos marcados.</div>
<?php endif; ?>

<form method="post" class="formulario tarjeta admin__formulario" novalidate>
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="campo">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" maxlength="80" required value="<?= e($valores['nombre']) ?>"<?= attr_error($errores, 'nombre') ?>>
        <?= mensaje_error($errores, 'nombre') ?>
    </div>
    <div class="campo">
        <label for="direccion">Dirección</label>
        <input type="text" id="direccion" name="direccion" maxlength="200" required value="<?= e($valores['direccion']) ?>"<?= attr_error($errores, 'direccion') ?>>
        <?= mensaje_error($errores, 'direccion') ?>
    </div>
    <div class="campo--doble">
        <div class="campo">
            <label for="telefono">Teléfono</label>
            <input type="tel" id="telefono" name="telefono" inputmode="tel" required value="<?= e($valores['telefono']) ?>"<?= attr_error($errores, 'telefono') ?>>
            <?= mensaje_error($errores, 'telefono') ?>
        </div>
        <div class="campo">
            <label for="whatsapp">WhatsApp (recibe los pedidos y reservas)</label>
            <input type="tel" id="whatsapp" name="whatsapp" inputmode="tel" required value="<?= e($valores['whatsapp']) ?>"<?= attr_error($errores, 'whatsapp') ?>>
            <?= mensaje_error($errores, 'whatsapp') ?>
        </div>
    </div>
    <div class="campo">
        <label for="horario">Horario</label>
        <input type="text" id="horario" name="horario" maxlength="255" required value="<?= e($valores['horario']) ?>"<?= attr_error($errores, 'horario') ?>>
        <?= mensaje_error($errores, 'horario') ?>
    </div>
    <div class="campo">
        <label for="url_mapa">Enlace del mapa <span class="opcional">(opcional)</span></label>
        <input type="url" id="url_mapa" name="url_mapa" maxlength="500" value="<?= e($valores['url_mapa'] ?? '') ?>"<?= attr_error($errores, 'url_mapa') ?>>
        <?= mensaje_error($errores, 'url_mapa') ?>
    </div>
    <div class="campo--check">
        <input type="checkbox" id="activo" name="activo" value="1"<?= $valores['activo'] ? ' checked' : '' ?>>
        <label for="activo">Sede activa (aparece en reservas y pedidos)</label>
    </div>

    <button type="submit" class="boton boton--primario">Guardar cambios</button>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
