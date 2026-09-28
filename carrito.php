<?php
/**
 * Carrito. Los productos viven en el navegador (localStorage); carrito.js
 * consulta api/carrito.php para mostrar precios y disponibilidad reales.
 */
require __DIR__ . '/includes/init.php';

$titulo_pagina = 'Carrito';
$pagina_actual = 'carrito';
$scripts_pagina = ['assets/js/carrito.js'];
require __DIR__ . '/includes/header.php';
?>

<section class="cabecera-pagina">
    <div class="contenedor">
        <span class="antetitulo">Tu pedido</span>
        <h1>Carrito</h1>
    </div>
</section>

<div class="contenedor carrito" id="carrito">
    <noscript>
        <div class="aviso aviso--error">Para usar el carrito necesitas activar JavaScript en tu navegador.</div>
    </noscript>

    <p class="carrito__cargando" id="carrito-cargando">Cargando tu carrito…</p>

    <!-- Carrito vacío -->
    <div class="carrito-vacio tarjeta" id="carrito-vacio" hidden>
        <h2>Tu carrito está vacío</h2>
        <p class="texto-suave">Agrega productos desde el menú para armar tu pedido.</p>
        <a href="<?= url('menu.php') ?>" class="boton boton--primario">Ir al menú</a>
    </div>

    <!-- Error al consultar el servidor -->
    <div class="aviso aviso--error" id="carrito-error" hidden>
        No pudimos cargar los precios actualizados. Revisa tu conexión y recarga la página.
    </div>

    <!-- Carrito con productos -->
    <div class="carrito__rejilla" id="carrito-contenido" hidden>
        <section class="carrito__lista" aria-labelledby="titulo-productos">
            <h2 id="titulo-productos" class="sr-only">Productos</h2>
            <div class="aviso aviso--info" id="carrito-aviso" hidden></div>
            <ul class="lineas" id="carrito-lineas"></ul>
            <div class="carrito__acciones-lista">
                <a href="<?= url('menu.php') ?>" class="boton boton--secundario boton--pequeno">Seguir comprando</a>
                <button type="button" class="enlace-boton" id="vaciar-carrito">Vaciar carrito</button>
            </div>
        </section>

        <aside class="resumen tarjeta" aria-labelledby="titulo-resumen">
            <h2 id="titulo-resumen">Resumen</h2>
            <dl class="resumen__filas">
                <div>
                    <dt>Subtotal</dt>
                    <dd id="resumen-subtotal">$0</dd>
                </div>
                <div>
                    <dt>Domicilio</dt>
                    <dd class="texto-suave">Por confirmar</dd>
                </div>
                <div class="resumen__total">
                    <dt>Total</dt>
                    <dd><span id="resumen-total">$0</span> <small>+ domicilio</small></dd>
                </div>
            </dl>
            <p class="resumen__nota">
                El encargado de la sede te confirmará por WhatsApp el valor del domicilio y tu pedido.
            </p>
            <a href="<?= url('pedido.php') ?>" class="boton boton--primario boton--bloque" id="continuar-pedido">
                Continuar con el pedido
            </a>
            <p class="resumen__bloqueo" id="resumen-bloqueo" hidden></p>
        </aside>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
