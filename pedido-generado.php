<?php
/**
 * Solicitud generada: muestra el código del pedido y el botón para enviarlo por WhatsApp.
 * Los datos llegan por la sesión (ver pedido.php).
 */
require __DIR__ . '/includes/init.php';

$pedido = $_SESSION['ultimo_pedido'] ?? null;
if (!$pedido) {
    redirigir('menu.php');
}

$titulo_pagina = 'Pedido ' . $pedido['codigo'];
$pagina_actual = 'carrito';
$scripts_pagina = ['assets/js/pedido-generado.js'];
require __DIR__ . '/includes/header.php';
?>

<section class="cabecera-pagina">
    <div class="contenedor">
        <span class="antetitulo">Paso 3 de 3</span>
        <h1>Envía tu pedido por WhatsApp</h1>
    </div>
</section>

<div class="contenedor generado">
    <div class="generado__tarjeta tarjeta" data-codigo-pedido="<?= e($pedido['codigo']) ?>">
        <p class="generado__codigo">Pedido <strong><?= e($pedido['codigo']) ?></strong> · <?= e($pedido['sede']) ?></p>

        <div class="aviso aviso--info">
            Tu pedido todavía <strong>no está confirmado</strong>. Envía el mensaje por WhatsApp:
            el encargado de la sede te confirmará el pedido y el valor del domicilio.
        </div>

        <a href="<?= e($pedido['enlace']) ?>" class="boton boton--whatsapp boton--bloque boton--grande"
           target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5.3-.5c.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.2-.6-.4ZM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8Zm8.4-18.2A11.8 11.8 0 0 0 1.8 17.9L.1 24l6.3-1.7A11.8 11.8 0 0 0 12 23.7 11.8 11.8 0 0 0 20.4 3.6Z"/></svg>
            Enviar pedido por WhatsApp
        </a>
        <p class="generado__ayuda texto-suave">
            ¿No se abrió WhatsApp? Escríbele a <?= e($pedido['sede']) ?> al
            <strong><?= e(telefono_legible($pedido['telefono'])) ?></strong> e indica el código <?= e($pedido['codigo']) ?>.
        </p>

        <h2 class="generado__titulo">Resumen</h2>
        <ul class="resumen__lineas">
            <?php foreach ($pedido['lineas'] as $l): ?>
                <li>
                    <span><?= (int) $l['cantidad'] ?> × <?= e($l['nombre_producto']) ?></span>
                    <span><?= precio($l['subtotal_linea']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <dl class="resumen__filas">
            <div>
                <dt>Subtotal</dt>
                <dd><?= precio($pedido['subtotal']) ?></dd>
            </div>
            <div>
                <dt>Domicilio</dt>
                <dd class="texto-suave">Por confirmar</dd>
            </div>
        </dl>

        <a href="<?= url('menu.php') ?>" class="resumen__volver">Volver al menú</a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
