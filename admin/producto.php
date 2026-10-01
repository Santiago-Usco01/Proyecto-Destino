<?php
/**
 * Crear o editar un producto (con imagen opcional). ?id=N edita; sin id crea.
 */
require __DIR__ . '/../includes/admin.php';

$id = id_recibido();
$categorias = db()->query('SELECT id, nombre FROM categorias ORDER BY orden, nombre')->fetchAll();
$ids_categoria = array_column($categorias, 'id');

$producto = null;
if ($id) {
    $consulta = db()->prepare('SELECT * FROM productos WHERE id = ?');
    $consulta->execute([$id]);
    $producto = $consulta->fetch();
    if (!$producto) {
        flash('error', 'El producto no existe.');
        redirigir('admin/productos.php');
    }
}

$valores = [
    'nombre'               => $producto['nombre'] ?? '',
    'categoria_id'         => (string) ($producto['categoria_id'] ?? ''),
    'descripcion'          => $producto['descripcion'] ?? '',
    'precio'               => isset($producto['precio']) ? (string) $producto['precio'] : '',
    'hora_inicio'          => hora_para_input($producto['hora_inicio'] ?? null),
    'hora_fin'             => hora_para_input($producto['hora_fin'] ?? null),
    'disponible_domicilio' => $producto ? (int) $producto['disponible_domicilio'] : 1,
    'activo'               => $producto ? (int) $producto['activo'] : 1,
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    foreach (['nombre', 'categoria_id', 'descripcion', 'precio', 'hora_inicio', 'hora_fin'] as $campo) {
        $valores[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }
    $valores['disponible_domicilio'] = isset($_POST['disponible_domicilio']) ? 1 : 0;
    $valores['activo'] = isset($_POST['activo']) ? 1 : 0;

    $nombre       = limpiar_texto($_POST['nombre'] ?? null, 120);
    $categoria_id = filter_var($_POST['categoria_id'] ?? null, FILTER_VALIDATE_INT);
    $descripcion  = limpiar_texto_largo($_POST['descripcion'] ?? null, 2000);
    $precio       = entero_desde_texto($_POST['precio'] ?? null);
    $inicio       = hora_desde_input($_POST['hora_inicio'] ?? null);
    $fin          = hora_desde_input($_POST['hora_fin'] ?? null);

    if ($nombre === null) {
        $errores['nombre'] = 'Escribe el nombre.';
    }
    if (!in_array($categoria_id, $ids_categoria, true)) {
        $errores['categoria_id'] = 'Elige una categoría.';
    }
    if ($precio === null || $precio < 1) {
        $errores['precio'] = 'Escribe un precio mayor a cero, en pesos.';
    }
    if (($inicio === null) !== ($fin === null) || ($valores['hora_inicio'] !== '' && $inicio === null) || ($valores['hora_fin'] !== '' && $fin === null)) {
        $errores['hora_inicio'] = 'Indica hora de inicio y de fin, o deja ambas vacías para usar la franja de la categoría.';
    } elseif ($inicio !== null && $inicio >= $fin) {
        $errores['hora_inicio'] = 'La hora de inicio debe ser anterior a la de fin.';
    }

    $imagen_nueva = null;
    if (!$errores) {
        try {
            $imagen_nueva = guardar_imagen_producto($_FILES['imagen'] ?? []);
        } catch (RuntimeException $ex) {
            $errores['imagen'] = $ex->getMessage();
        }
    }

    if (!$errores) {
        $imagen = $imagen_nueva ?? ($producto['imagen'] ?? null);
        if (isset($_POST['quitar_imagen']) && $imagen_nueva === null) {
            $imagen = null;
        }
        $datos = [$categoria_id, $nombre, $descripcion, $precio, $imagen, $inicio, $fin,
                  $valores['disponible_domicilio'], $valores['activo']];
        try {
            if ($producto) {
                db()->prepare('UPDATE productos SET categoria_id = ?, nombre = ?, descripcion = ?, precio = ?, imagen = ?,
                                      hora_inicio = ?, hora_fin = ?, disponible_domicilio = ?, activo = ? WHERE id = ?')
                    ->execute([...$datos, $id]);
                if (($producto['imagen'] ?? null) !== $imagen) {
                    borrar_imagen_producto($producto['imagen']);
                }
            } else {
                db()->prepare('INSERT INTO productos (categoria_id, nombre, descripcion, precio, imagen,
                                      hora_inicio, hora_fin, disponible_domicilio, activo)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute($datos);
            }
            flash('exito', $producto ? 'Producto actualizado.' : 'Producto creado.');
            redirigir('admin/productos.php');
        } catch (PDOException $ex) {
            borrar_imagen_producto($imagen_nueva);
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $errores['nombre'] = 'Ya existe un producto con ese nombre en esa categoría.';
        }
    }
}

$titulo_pagina = $producto ? 'Editar producto' : 'Nuevo producto';
$seccion = 'productos';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin__titulo">
    <h1><?= e($titulo_pagina) ?></h1>
    <a href="<?= url('admin/productos.php') ?>" class="boton boton--secundario boton--pequeno">← Volver</a>
</div>

<?php if ($errores): ?>
    <div class="aviso aviso--error" role="alert">Revisa los campos marcados.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="formulario tarjeta admin__formulario" novalidate>
    <?= csrf_campo() ?>
    <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <div class="campo--doble">
        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="120" required
                   value="<?= e($valores['nombre']) ?>"<?= attr_error($errores, 'nombre') ?>>
            <?= mensaje_error($errores, 'nombre') ?>
        </div>
        <div class="campo">
            <label for="categoria_id">Categoría</label>
            <select id="categoria_id" name="categoria_id" required<?= attr_error($errores, 'categoria_id') ?>>
                <option value="">Elige una categoría</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= $valores['categoria_id'] === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= mensaje_error($errores, 'categoria_id') ?>
        </div>
    </div>

    <div class="campo">
        <label for="descripcion">Descripción <span class="opcional">(opcional)</span></label>
        <textarea id="descripcion" name="descripcion" maxlength="2000"><?= e($valores['descripcion']) ?></textarea>
    </div>

    <div class="campo">
        <label for="precio">Precio (COP)</label>
        <input type="text" id="precio" name="precio" inputmode="numeric" required placeholder="15000"
               value="<?= e($valores['precio']) ?>"<?= attr_error($errores, 'precio') ?>>
        <?= mensaje_error($errores, 'precio') ?>
    </div>

    <div class="campo--doble">
        <div class="campo">
            <label for="hora_inicio">Disponible desde <span class="opcional">(opcional)</span></label>
            <input type="time" id="hora_inicio" name="hora_inicio" value="<?= e($valores['hora_inicio']) ?>"<?= attr_error($errores, 'hora_inicio') ?>>
            <?= mensaje_error($errores, 'hora_inicio') ?>
        </div>
        <div class="campo">
            <label for="hora_fin">Disponible hasta <span class="opcional">(opcional)</span></label>
            <input type="time" id="hora_fin" name="hora_fin" value="<?= e($valores['hora_fin']) ?>">
        </div>
    </div>
    <p class="texto-suave" style="margin:-8px 0 0">Si dejas las horas vacías, el producto usa la franja de su categoría.</p>

    <div class="campo">
        <label for="imagen">Imagen <span class="opcional">(JPG, PNG o WebP, máx. 2 MB)</span></label>
        <?php if (!empty($producto['imagen'])): ?>
            <img class="admin__vista-imagen" src="<?= e(imagen_producto($producto['imagen'])) ?>" alt="Imagen actual">
            <div class="campo--check">
                <input type="checkbox" id="quitar_imagen" name="quitar_imagen" value="1">
                <label for="quitar_imagen">Quitar la imagen actual</label>
            </div>
        <?php endif; ?>
        <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp"<?= attr_error($errores, 'imagen') ?>>
        <?= mensaje_error($errores, 'imagen') ?>
    </div>

    <div class="campo--check">
        <input type="checkbox" id="disponible_domicilio" name="disponible_domicilio" value="1"<?= $valores['disponible_domicilio'] ? ' checked' : '' ?>>
        <label for="disponible_domicilio">Se puede pedir a domicilio</label>
    </div>
    <div class="campo--check">
        <input type="checkbox" id="activo" name="activo" value="1"<?= $valores['activo'] ? ' checked' : '' ?>>
        <label for="activo">Visible en el menú</label>
    </div>

    <button type="submit" class="boton boton--primario"><?= $producto ? 'Guardar cambios' : 'Crear producto' ?></button>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
