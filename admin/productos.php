<?php
/**
 * Productos: listado y activar/ocultar. La edición está en producto.php.
 * Los productos no se borran (tienen pedidos asociados): se ocultan.
 */
require __DIR__ . '/../includes/admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $id = id_recibido();
    if ($id && ($_POST['accion'] ?? '') === 'alternar') {
        db()->prepare('UPDATE productos SET activo = 1 - activo WHERE id = ?')->execute([$id]);
        flash('exito', 'Producto actualizado.');
    }
    redirigir('admin/productos.php' . (isset($_POST['categoria']) ? '?categoria=' . (int) $_POST['categoria'] : ''));
}

$categorias = db()->query('SELECT id, nombre FROM categorias ORDER BY orden, nombre')->fetchAll();
$categoria_id = filter_var($_GET['categoria'] ?? null, FILTER_VALIDATE_INT) ?: null;

$sql = 'SELECT p.id, p.nombre, p.precio, p.imagen, p.activo, p.disponible_domicilio, c.nombre AS categoria
        FROM productos p JOIN categorias c ON c.id = p.categoria_id'
     . ($categoria_id ? ' WHERE p.categoria_id = ?' : '')
     . ' ORDER BY c.orden, c.nombre, p.nombre';
$consulta = db()->prepare($sql);
$consulta->execute($categoria_id ? [$categoria_id] : []);
$productos = $consulta->fetchAll();

$titulo_pagina = 'Productos';
$seccion = 'productos';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin__titulo">
    <h1>Productos</h1>
    <a href="<?= url('admin/producto.php') ?>" class="boton boton--primario boton--pequeno">+ Nuevo producto</a>
</div>

<nav class="admin__filtros" aria-label="Filtrar por categoría">
    <a href="<?= url('admin/productos.php') ?>"<?= !$categoria_id ? ' class="activo"' : '' ?>>Todas</a>
    <?php foreach ($categorias as $c): ?>
        <a href="<?= url('admin/productos.php?categoria=' . (int) $c['id']) ?>"<?= $categoria_id === (int) $c['id'] ? ' class="activo"' : '' ?>><?= e($c['nombre']) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$productos): ?>
    <p class="tarjeta admin__vacio">No hay productos.</p>
<?php else: ?>
    <div class="tabla-envoltorio"><table class="tabla">
        <thead><tr><th></th><th>Producto</th><th>Categoría</th><th class="num">Precio</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($productos as $p): ?>
            <tr class="<?= $p['activo'] ? '' : 'inactivo' ?>">
                <td><img class="miniatura" src="<?= e(imagen_producto($p['imagen'])) ?>" alt="" loading="lazy"></td>
                <td><?= e($p['nombre']) ?></td>
                <td><?= e($p['categoria']) ?></td>
                <td class="num"><?= e(precio((int) $p['precio'])) ?></td>
                <td>
                    <?= $p['activo'] ? '<span class="etiqueta etiqueta--exito">Visible</span>' : '<span class="etiqueta etiqueta--error">Oculto</span>' ?>
                    <?php if (!$p['disponible_domicilio']): ?><br><small>Sin domicilio</small><?php endif; ?>
                </td>
                <td>
                    <div class="acciones">
                        <a class="boton boton--secundario boton--pequeno" href="<?= url('admin/producto.php?id=' . (int) $p['id']) ?>">Editar</a>
                        <form method="post">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="accion" value="alternar">
                            <?php if ($categoria_id): ?><input type="hidden" name="categoria" value="<?= $categoria_id ?>"><?php endif; ?>
                            <button type="submit" class="boton boton--secundario boton--pequeno"><?= $p['activo'] ? 'Ocultar' : 'Mostrar' ?></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
