<?php
/**
 * Cotizaciones: pan al por mayor y pastelería para eventos.
 * No se guarda nada en la base de datos: cotizaciones.js arma el mensaje
 * y abre WhatsApp de la sede elegida.
 */
require __DIR__ . '/includes/init.php';

$sedes = sedes_activas();
$fecha_minima_evento = fecha_mas_dias(PASTELERIA_DIAS_ANTELACION);

/** Opciones de sede para un formulario de cotización. */
function opciones_sede(array $sedes, string $nombre_campo): void
{
    foreach ($sedes as $i => $sede): ?>
        <label class="opcion">
            <input type="radio" name="<?= e($nombre_campo) ?>" value="<?= (int) $sede['id'] ?>" required
                   data-whatsapp="<?= e($sede['whatsapp']) ?>" data-sede="<?= e($sede['nombre']) ?>"
                   <?= $i === 0 ? 'checked' : '' ?>>
            <span>
                <strong><?= e($sede['nombre']) ?></strong>
                <small><?= e($sede['direccion']) ?></small>
            </span>
        </label>
    <?php endforeach;
}

$titulo_pagina = 'Cotizaciones';
$pagina_actual = 'cotizaciones';
$scripts_pagina = ['assets/js/cotizaciones.js'];
require __DIR__ . '/includes/header.php';
?>

<section class="cabecera-pagina">
    <div class="contenedor">
        <span class="antetitulo">Pedidos especiales</span>
        <h1>Cotizaciones</h1>
        <p class="texto-suave">
            Cuéntanos qué necesitas y te llevamos a WhatsApp con el mensaje listo.
            El encargado de la sede te responderá con la cotización.
        </p>
        <nav class="saltos" aria-label="Tipos de cotización">
            <a href="#mayoristas" class="boton boton--secundario boton--pequeno">Pan al por mayor</a>
            <a href="#pasteleria" class="boton boton--secundario boton--pequeno">Tortas y postres</a>
        </nav>
    </div>
</section>

<div class="contenedor pagina-formulario">
    <noscript>
        <div class="aviso aviso--error formulario--angosto">
            Para armar el mensaje automáticamente necesitas activar JavaScript. También puedes escribirnos directamente:
            <?php foreach ($sedes as $sede): ?>
                <a href="<?= e(enlace_whatsapp($sede['whatsapp'])) ?>"><?= e($sede['nombre']) ?></a>
            <?php endforeach; ?>
        </div>
    </noscript>

    <!-- ============================================================ Mayoristas -->
    <section class="cotizacion" id="mayoristas" aria-labelledby="titulo-mayoristas">
        <div class="cotizacion__info">
            <span class="antetitulo">Mayoristas</span>
            <h2 id="titulo-mayoristas">Pan al por mayor</h2>
            <p>
                ¿Tienes un restaurante, cafetería o negocio de comidas? Te ofrecemos pan de nuestra
                panadería al por mayor. Escríbenos qué productos y cantidades necesitas, y con qué
                frecuencia, y te enviamos la cotización.
            </p>
            <ul class="lista-check">
                <li>Cotización personalizada según tu pedido</li>
                <li>Atención directa con el encargado de la sede</li>
                <li>Sin formularios largos: lo hablamos por WhatsApp</li>
            </ul>
        </div>

        <form class="formulario tarjeta" data-cotizacion="mayoristas" novalidate>
            <div class="campo--doble">
                <div class="campo">
                    <label for="may-nombre">Tu nombre <span class="opcional">(opcional)</span></label>
                    <input type="text" id="may-nombre" name="nombre" maxlength="100" autocomplete="name">
                </div>
                <div class="campo">
                    <label for="may-negocio">Negocio <span class="opcional">(opcional)</span></label>
                    <input type="text" id="may-negocio" name="negocio" maxlength="100" autocomplete="organization">
                </div>
            </div>
            <div class="campo">
                <span class="campo__etiqueta" id="may-etiqueta-sede">Sede</span>
                <div class="opciones opciones--tarjetas" role="radiogroup" aria-labelledby="may-etiqueta-sede">
                    <?php opciones_sede($sedes, 'sede'); ?>
                </div>
            </div>
            <div class="campo">
                <label for="may-detalle">¿Qué necesitas? <span class="opcional">(opcional)</span></label>
                <textarea id="may-detalle" name="detalle" maxlength="500"
                          placeholder="Ej.: 100 panes rollo y 50 panes de queso diarios"></textarea>
            </div>
            <button type="submit" class="boton boton--whatsapp boton--bloque">Solicitar cotización por WhatsApp</button>
        </form>
    </section>

    <!-- ============================================================ Pastelería -->
    <section class="cotizacion" id="pasteleria" aria-labelledby="titulo-pasteleria">
        <div class="cotizacion__info">
            <span class="antetitulo">Pastelería</span>
            <h2 id="titulo-pasteleria">Tortas y postres para eventos</h2>
            <p>
                Cumpleaños, reuniones de trabajo, bautizos, grados o cualquier celebración.
                Preparamos tortas y postres a tu medida.
            </p>
            <ul class="lista-check">
                <li>Pídelos con al menos <strong>una semana</strong> de anticipación</li>
                <li>Cuéntanos la fecha, el número de invitados y la idea que tienes</li>
                <li>El encargado te confirma el precio y los detalles por WhatsApp</li>
            </ul>
        </div>

        <form class="formulario tarjeta" data-cotizacion="pasteleria" novalidate>
            <div class="campo--doble">
                <div class="campo">
                    <label for="pas-nombre">Tu nombre <span class="opcional">(opcional)</span></label>
                    <input type="text" id="pas-nombre" name="nombre" maxlength="100" autocomplete="name">
                </div>
                <div class="campo">
                    <label for="pas-fecha">Fecha del evento <span class="opcional">(opcional)</span></label>
                    <input type="date" id="pas-fecha" name="fecha" min="<?= e($fecha_minima_evento) ?>" aria-describedby="pas-fecha-ayuda">
                    <p class="ayuda" id="pas-fecha-ayuda">Desde el <?= e(fecha_legible($fecha_minima_evento)) ?>.</p>
                </div>
            </div>
            <div class="campo">
                <span class="campo__etiqueta" id="pas-etiqueta-sede">Sede</span>
                <div class="opciones opciones--tarjetas" role="radiogroup" aria-labelledby="pas-etiqueta-sede">
                    <?php opciones_sede($sedes, 'sede'); ?>
                </div>
            </div>
            <div class="campo">
                <label for="pas-detalle">Cuéntanos tu idea <span class="opcional">(opcional)</span></label>
                <textarea id="pas-detalle" name="detalle" maxlength="500"
                          placeholder="Ej.: torta de chocolate para 30 personas, tema de fútbol"></textarea>
            </div>
            <p class="error-campo" data-error hidden></p>
            <button type="submit" class="boton boton--whatsapp boton--bloque">Solicitar cotización por WhatsApp</button>
        </form>
    </section>
</div>

<script>
    // Datos que necesita cotizaciones.js para validar y escribir la fecha.
    window.COTIZACIONES = { fechaMinimaEvento: <?= json_encode($fecha_minima_evento) ?> };
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
