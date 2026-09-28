<?php
/**
 * Carta / menú: productos activos agrupados por categoría.
 * El botón "Agregar" solo aparece si el producto se puede pedir en este momento.
 */
require __DIR__ . '/includes/init.php';

$categorias = db()->query('SELECT c.id, c.nombre, c.descripcion, c.hora_inicio, c.hora_fin
                           FROM categorias c
                           WHERE c.activo = 1
                             AND EXISTS (SELECT 1 FROM productos p WHERE p.categoria_id = c.id AND p.activo = 1)
                           ORDER BY c.orden, c.nombre')->fetchAll();

$productos = db()->query('SELECT p.id, p.categoria_id, p.nombre, p.descripcion, p.precio, p.imagen,
                                 p.disponible_domicilio, p.activo, ' . SQL_FRANJA_PRODUCTO . '
                          FROM productos p
                          JOIN categorias c ON c.id = p.categoria_id
                          WHERE p.activo = 1 AND c.activo = 1
                          ORDER BY c.orden, p.id')->fetchAll();

$productos_por_categoria = [];
foreach ($productos as $p) {
    $productos_por_categoria[$p['categoria_id']][] = $p;
}

$titulo_pagina = 'Menú';
$pagina_actual = 'menu';
$scripts_pagina = ['assets/js/menu.js'];
require __DIR__ . '/includes/header.php';
?>

<section class="cabecera-pagina">
    <div class="contenedor">
        <span class="antetitulo">Nuestra carta</span>
        <h1>Menú</h1>
        <p class="texto-suave">
            <?= e(estado_local()) ?>.
            Cada producto se puede pedir a domicilio dentro de su horario.
        </p>

        <div class="buscador">
            <label for="buscar-producto" class="sr-only">Buscar en el menú</label>
            <input type="search" id="buscar-producto" placeholder="Buscar: pizza, latte, almojábana…" autocomplete="off">
        </div>
    </div>
</section>

<nav class="indice-categorias" aria-label="Categorías del menú">
    <div class="contenedor">
        <ul>
            <?php foreach ($categorias as $c): ?>
                <li><a href="#categoria-<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>

<div class="contenedor menu">
    <?php foreach ($categorias as $c): ?>
        <?php $en_horario = local_abierto() && en_franja($c['hora_inicio'], $c['hora_fin']); ?>
        <section class="categoria" id="categoria-<?= (int) $c['id'] ?>" data-categoria>
            <header class="categoria__encabezado">
                <h2><?= e($c['nombre']) ?></h2>
                <p class="categoria__horario">
                    <?= e(hora_legible($c['hora_inicio'])) ?> – <?= e(hora_legible($c['hora_fin'])) ?>
                    <span class="etiqueta<?= $en_horario ? ' etiqueta--exito' : '' ?>">
                        <?= $en_horario ? 'Disponible ahora' : 'Fuera de horario' ?>
                    </span>
                </p>
                <?php if ($c['descripcion']): ?>
                    <p class="texto-suave"><?= e($c['descripcion']) ?></p>
                <?php endif; ?>
            </header>

            <div class="productos">
                <?php foreach ($productos_por_categoria[$c['id']] ?? [] as $p): ?>
                    <?php $motivo = motivo_no_pedible($p); ?>
                    <article class="producto<?= $p['imagen'] ? ' producto--con-imagen' : '' ?>"
                             data-producto
                             data-busqueda="<?= e($p['nombre'] . ' ' . $p['descripcion'] . ' ' . $c['nombre']) ?>">
                        <?php if ($p['imagen']): ?>
                            <img class="producto__imagen" src="<?= e(imagen_producto($p['imagen'])) ?>"
                                 alt="" loading="lazy" width="400" height="300">
                        <?php endif; ?>
                        <div class="producto__cuerpo">
                            <div class="producto__titulo">
                                <h3><?= e($p['nombre']) ?></h3>
                                <span class="producto__precio"><?= precio((int) $p['precio']) ?></span>
                            </div>
                            <?php if ($p['descripcion']): ?>
                                <p class="producto__descripcion"><?= e($p['descripcion']) ?></p>
                            <?php endif; ?>
                            <div class="producto__accion">
                                <?php if ($motivo === null): ?>
                                    <button type="button" class="boton boton--primario boton--pequeno"
                                            data-agregar="<?= (int) $p['id'] ?>"
                                            data-nombre="<?= e($p['nombre']) ?>">Agregar</button>
                                <?php else: ?>
                                    <span class="producto__no-disponible"><?= e($motivo) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <p class="sin-resultados" id="sin-resultados" hidden>No encontramos productos con esa búsqueda.</p>
</div>

<!-- Aviso al agregar un producto -->
<div class="aviso-flotante" id="aviso-carrito" role="status" aria-live="polite" hidden>
    <span id="aviso-carrito-texto"></span>
    <a href="<?= url('carrito.php') ?>">Ver carrito</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
