<?php
/**
 * Categorías: listado y activar/ocultar. La edición está en categoria.php.
 */
require __DIR__ . '/../includes/admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $id = id_recibido();
    if ($id && ($_POST['accion'] ?? '') === 'alternar') {
        db()->prepare('UPDATE categorias SET activo = 1 - activo WHERE id = ?')->execute([$id]);
        flash('exito', 'Categoría actualizada.');
    }
    redirigir('admin/categorias.php');
}

$categorias = db()->query('SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id) AS total_productos
                           FROM categorias c ORDER BY c.orden, c.nombre')->fetchAll();

$titulo_pagina = 'Categorías';
$seccion = 'categorias';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin__titulo">
    <h1>Categorías</h1>
    <a href="<?= url('admin/categoria.php') ?>" class="boton boton--primario boton--pequeno">+ Nueva categoría</a>
</div>

<div class="tabla-envoltorio"><table class="tabla">
    <thead><tr><th class="num">Orden</th><th>Categoría</th><th>Franja horaria</th><th class="num">Productos</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categorias as $c): ?>
        <tr class="<?= $c['activo'] ? '' : 'inactivo' ?>">
            <td class="num"><?= (int) $c['orden'] ?></td>
            <td><?= e($c['nombre']) ?><?php if ($c['descripcion']): ?><br><small><?= e($c['descripcion']) ?></small><?php endif; ?></td>
            <td><?= e(hora_legible($c['hora_inicio'])) ?> – <?= e(hora_legible($c['hora_fin'])) ?></td>
            <td class="num"><?= (int) $c['total_productos'] ?></td>
            <td><?= $c['activo'] ? '<span class="etiqueta etiqueta--exito">Visible</span>' : '<span class="etiqueta etiqueta--error">Oculta</span>' ?></td>
            <td>
                <div class="acciones">
                    <a class="boton boton--secundario boton--pequeno" href="<?= url('admin/categoria.php?id=' . (int) $c['id']) ?>">Editar</a>
                    <form method="post">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="accion" value="alternar">
                        <button type="submit" class="boton boton--secundario boton--pequeno"><?= $c['activo'] ? 'Ocultar' : 'Mostrar' ?></button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
