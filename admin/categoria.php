<?php
/**
 * Crear o editar una categoría. ?id=N edita; sin id crea.
 */
require __DIR__ . '/../includes/admin.php';

$id = id_recibido();
$categoria = null;
if ($id) {
    $consulta = db()->prepare('SELECT * FROM categorias WHERE id = ?');
    $consulta->execute([$id]);
    $categoria = $consulta->fetch();
    if (!$categoria) {
        flash('error', 'La categoría no existe.');
        redirigir('admin/categorias.php');
    }
}

$valores = [
    'nombre'      => $categoria['nombre'] ?? '',
    'descripcion' => $categoria['descripcion'] ?? '',
    'orden'       => isset($categoria['orden']) ? (string) $categoria['orden'] : '0',
    'hora_inicio' => hora_para_input($categoria['hora_inicio'] ?? HORA_APERTURA),
    'hora_fin'    => hora_para_input($categoria['hora_fin'] ?? HORA_CIERRE),
    'activo'      => $categoria ? (int) $categoria['activo'] : 1,
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    foreach (['nombre', 'descripcion', 'orden', 'hora_inicio', 'hora_fin'] as $campo) {
        $valores[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }
    $valores['activo'] = isset($_POST['activo']) ? 1 : 0;

    $nombre      = limpiar_texto($_POST['nombre'] ?? null, 80);
    $descripcion = limpiar_texto($_POST['descripcion'] ?? null, 255);
    $orden       = filter_var($_POST['orden'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 65535]]);
    $inicio      = hora_desde_input($_POST['hora_inicio'] ?? null);
    $fin         = hora_desde_input($_POST['hora_fin'] ?? null);

    if ($nombre === null) {
        $errores['nombre'] = 'Escribe el nombre.';
    }
    if ($orden === false) {
        $errores['orden'] = 'Escribe un número entre 0 y 65535.';
    }
    if ($inicio === null || $fin === null) {
        $errores['hora_inicio'] = 'Indica la hora de inicio y la de fin.';
    } elseif ($inicio >= $fin) {
        $errores['hora_inicio'] = 'La hora de inicio debe ser anterior a la de fin.';
    }

    if (!$errores) {
        $datos = [$nombre, $descripcion, $orden, $inicio, $fin, $valores['activo']];
        try {
            if ($categoria) {
                db()->prepare('UPDATE categorias SET nombre = ?, descripcion = ?, orden = ?, hora_inicio = ?, hora_fin = ?, activo = ? WHERE id = ?')
                    ->execute([...$datos, $id]);
            } else {
                db()->prepare('INSERT INTO categorias (nombre, descripcion, orden, hora_inicio, hora_fin, activo) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute($datos);
            }
            flash('exito', $categoria ? 'Categoría actualizada.' : 'Categoría creada.');
            redirigir('admin/categorias.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $errores['nombre'] = 'Ya existe una categoría con ese nombre.';
        }
    }
}

$titulo_pagina = $categoria ? 'Editar categoría' : 'Nueva categoría';
$seccion = 'categorias';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin__titulo">
    <h1><?= e($titulo_pagina) ?></h1>
    <a href="<?= url('admin/categorias.php') ?>" class="boton boton--secundario boton--pequeno">← Volver</a>
</div>

<?php if ($errores): ?>
    <div class="aviso aviso--error" role="alert">Revisa los campos marcados.</div>
<?php endif; ?>

<form method="post" class="formulario tarjeta admin__formulario" novalidate>
    <?= csrf_campo() ?>
    <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <div class="campo--doble">
        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="80" required
                   value="<?= e($valores['nombre']) ?>"<?= attr_error($errores, 'nombre') ?>>
            <?= mensaje_error($errores, 'nombre') ?>
        </div>
        <div class="campo">
            <label for="orden">Orden en el menú</label>
            <input type="text" id="orden" name="orden" inputmode="numeric"
                   value="<?= e($valores['orden']) ?>"<?= attr_error($errores, 'orden') ?>>
            <?= mensaje_error($errores, 'orden') ?>
        </div>
    </div>

    <div class="campo">
        <label for="descripcion">Descripción <span class="opcional">(opcional)</span></label>
        <input type="text" id="descripcion" name="descripcion" maxlength="255" value="<?= e($valores['descripcion']) ?>">
    </div>

    <div class="campo--doble">
        <div class="campo">
            <label for="hora_inicio">Se pide desde</label>
            <input type="time" id="hora_inicio" name="hora_inicio" required value="<?= e($valores['hora_inicio']) ?>"<?= attr_error($errores, 'hora_inicio') ?>>
            <?= mensaje_error($errores, 'hora_inicio') ?>
        </div>
        <div class="campo">
            <label for="hora_fin">Se pide hasta</label>
            <input type="time" id="hora_fin" name="hora_fin" required value="<?= e($valores['hora_fin']) ?>">
        </div>
    </div>

    <div class="campo--check">
        <input type="checkbox" id="activo" name="activo" value="1"<?= $valores['activo'] ? ' checked' : '' ?>>
        <label for="activo">Visible en el menú</label>
    </div>

    <button type="submit" class="boton boton--primario"><?= $categoria ? 'Guardar cambios' : 'Crear categoría' ?></button>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
