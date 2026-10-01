<?php
/**
 * Página de inicio.
 */
require __DIR__ . '/includes/init.php';

// Categorías activas agrupadas por franja horaria (Desayunos, Panadería y cafetería, Comidas rápidas).
$categorias = db()->query('SELECT c.id, c.nombre, c.hora_inicio, c.hora_fin, COUNT(p.id) AS total
                           FROM categorias c
                           JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
                           WHERE c.activo = 1
                           GROUP BY c.id
                           ORDER BY c.hora_inicio, c.hora_fin, c.orden')->fetchAll();

$franjas = [];
foreach ($categorias as $c) {
    $clave = $c['hora_inicio'] . '-' . $c['hora_fin'];
    $franjas[$clave] ??= [
        'nombre'      => FRANJAS_MENU[$clave] ?? $c['nombre'],
        'hora_inicio' => $c['hora_inicio'],
        'hora_fin'    => $c['hora_fin'],
        'categorias'  => [],
    ];
    $franjas[$clave]['categorias'][] = $c;
}

$total_productos = array_sum(array_column($categorias, 'total'));
$sedes = sedes_activas();

$titulo_pagina = 'Café, panadería y restaurante en Neiva';
$pagina_actual = 'inicio';
require __DIR__ . '/includes/header.php';
?>

<!-- ============================================================ Hero -->
<section class="hero">
    <div class="contenedor hero__contenido">
        <h1 class="hero__titulo">
            <img src="<?= url('assets/img/logo-claro.png') ?>" alt="<?= e(NEGOCIO_NOMBRE) ?>" width="389" height="196">
        </h1>
        <p class="hero__texto">
            Café de la mañana, pan recién horneado, desayunos con sabor huilense,
            y desde las 4 de la tarde, pizzas y hamburguesas. En <?= e(NEGOCIO_CIUDAD) ?>.
        </p>
        <div class="hero__acciones">
            <a href="<?= url('menu.php') ?>" class="boton boton--claro">Ver el menú</a>
            <a href="<?= url('reservas.php') ?>" class="boton boton--contorno-claro">Reservar mesa</a>
        </div>
        <p class="hero__estado">
            <span class="punto-estado<?= local_abierto() ? ' punto-estado--abierto' : '' ?>" aria-hidden="true"></span>
            <?= e(estado_local()) ?>
        </p>
    </div>
</section>

<!-- ============================================================ Sobre Destino -->
<section class="seccion" id="nosotros">
    <div class="contenedor nosotros">
        <div>
            <span class="antetitulo">Sobre Destino</span>
            <h2>Café, pizza y pan en un mismo lugar</h2>
            <p>
                En Destino el día empieza temprano: café, panadería y desayunos tradicionales
                como el tamal huilense, el caldo campesino o el calentado, servidos hasta el mediodía.
            </p>
            <p>
                En la tarde llegan las pizzas, las hamburguesas, los sándwiches y los panzerottis,
                acompañados de jugos naturales, sodas frutales y bebidas frías.
                Visítanos en cualquiera de nuestras dos sedes o pide a domicilio.
            </p>
        </div>
        <dl class="cifras">
            <div class="cifra">
                <dt>Sedes en Neiva</dt>
                <dd><?= count($sedes) ?></dd>
            </div>
            <div class="cifra">
                <dt>Productos en la carta</dt>
                <dd><?= (int) $total_productos ?></dd>
            </div>
            <div class="cifra">
                <dt>Abierto todos los días</dt>
                <dd class="cifra__texto"><?= e(hora_legible(HORA_APERTURA)) ?> a <?= e(hora_legible(HORA_CIERRE)) ?></dd>
            </div>
        </dl>
    </div>
</section>

<!-- ============================================================ La carta -->
<section class="seccion seccion--alterna" id="carta">
    <div class="contenedor">
        <div class="seccion__encabezado seccion__encabezado--centro">
            <span class="antetitulo">Nuestra carta</span>
            <h2>Algo para cada hora del día</h2>
            <p class="texto-suave">Los productos se pueden pedir a domicilio dentro de su horario.</p>
        </div>

        <div class="franjas">
            <?php foreach ($franjas as $franja): ?>
                <?php $disponible = local_abierto() && en_franja($franja['hora_inicio'], $franja['hora_fin']); ?>
                <article class="franja">
                    <header class="franja__encabezado">
                        <h3><?= e($franja['nombre']) ?></h3>
                        <p class="franja__horario">
                            <?= e(hora_legible($franja['hora_inicio'])) ?> – <?= e(hora_legible($franja['hora_fin'])) ?>
                        </p>
                        <span class="etiqueta<?= $disponible ? ' etiqueta--exito' : '' ?>">
                            <?= $disponible ? 'Disponible ahora' : 'Fuera de horario' ?>
                        </span>
                    </header>
                    <ul class="franja__categorias">
                        <?php foreach ($franja['categorias'] as $c): ?>
                            <li>
                                <a href="<?= url('menu.php#categoria-' . (int) $c['id']) ?>">
                                    <span><?= e($c['nombre']) ?></span>
                                    <span class="franja__cantidad"><?= (int) $c['total'] ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="centrado">
            <a href="<?= url('menu.php') ?>" class="boton boton--primario">Ver el menú completo</a>
        </p>
    </div>
</section>

<!-- ============================================================ Cómo pedir -->
<section class="seccion" id="como-pedir">
    <div class="contenedor">
        <div class="seccion__encabezado seccion__encabezado--centro">
            <span class="antetitulo">Domicilios</span>
            <h2>¿Cómo pedir?</h2>
        </div>

        <ol class="pasos">
            <li class="paso">
                <h3>Elige en el menú</h3>
                <p>Agrega al carrito los productos que quieras y ajusta las cantidades.</p>
            </li>
            <li class="paso">
                <h3>Completa tus datos</h3>
                <p>Indica tu dirección, la sede desde la que quieres el pedido y cómo vas a pagar: efectivo o transferencia.</p>
            </li>
            <li class="paso">
                <h3>Envía el mensaje</h3>
                <p>Te llevamos a WhatsApp con tu pedido ya escrito. Solo tienes que enviarlo.</p>
            </li>
            <li class="paso">
                <h3>Te confirmamos</h3>
                <p>El encargado de la sede confirma tu pedido y te dice el valor del domicilio.</p>
            </li>
        </ol>

        <p class="nota-centrada texto-suave">
            Tu pedido queda confirmado solo cuando el encargado te responda por WhatsApp.
        </p>
    </div>
</section>

<!-- ============================================================ Servicios -->
<section class="seccion seccion--alterna" id="servicios">
    <div class="contenedor">
        <div class="seccion__encabezado seccion__encabezado--centro">
            <span class="antetitulo">Más que un café</span>
            <h2>Reservas y pedidos especiales</h2>
        </div>

        <div class="servicios">
            <article class="servicio">
                <h3>Reserva tu mesa</h3>
                <p>Para grupos de hasta <?= RESERVA_MAX_PERSONAS ?> personas, con al menos un día de anticipación. Te confirmamos la disponibilidad.</p>
                <a href="<?= url('reservas.php') ?>" class="boton boton--secundario">Solicitar reserva</a>
            </article>
            <article class="servicio">
                <h3>Pan para restaurantes</h3>
                <p>¿Tienes un restaurante? Te cotizamos pan al por mayor. Escríbenos por WhatsApp y hablamos directamente.</p>
                <a href="<?= url('cotizaciones.php#mayoristas') ?>" class="boton boton--secundario">Cotizar al por mayor</a>
            </article>
            <article class="servicio">
                <h3>Tortas y postres para eventos</h3>
                <p>Cumpleaños, reuniones o celebraciones. Pídelos con al menos una semana de anticipación.</p>
                <a href="<?= url('cotizaciones.php#pasteleria') ?>" class="boton boton--secundario">Cotizar pastelería</a>
            </article>
        </div>
    </div>
</section>

<!-- ============================================================ Sedes -->
<section class="seccion" id="sedes">
    <div class="contenedor">
        <div class="seccion__encabezado seccion__encabezado--centro">
            <span class="antetitulo">Visítanos</span>
            <h2>Nuestras sedes</h2>
        </div>

        <div class="sedes">
            <?php foreach ($sedes as $sede): ?>
                <article class="sede">
                    <h3><?= e($sede['nombre']) ?></h3>
                    <dl class="sede__datos">
                        <div>
                            <dt>Dirección</dt>
                            <dd><?= e($sede['direccion']) ?></dd>
                        </div>
                        <div>
                            <dt>Teléfono</dt>
                            <dd><a href="tel:+57<?= e($sede['telefono']) ?>"><?= e(telefono_legible($sede['telefono'])) ?></a></dd>
                        </div>
                        <div>
                            <dt>Horario</dt>
                            <dd><?= e($sede['horario']) ?></dd>
                        </div>
                    </dl>
                    <div class="sede__acciones">
                        <a href="<?= e(enlace_whatsapp($sede['whatsapp'], 'Hola, Destino ' . $sede['nombre'] . '. ')) ?>"
                           class="boton boton--whatsapp boton--pequeno" target="_blank" rel="noopener">WhatsApp</a>
                        <?php if ($sede['url_mapa']): ?>
                            <a href="<?= e($sede['url_mapa']) ?>" class="boton boton--secundario boton--pequeno"
                               target="_blank" rel="noopener">Cómo llegar</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================ Redes -->
<section class="redes-banda">
    <div class="contenedor redes-banda__contenido">
        <div>
            <h2>Síguenos</h2>
            <p>Novedades, productos del día y promociones en nuestras redes.</p>
        </div>
        <div class="redes-banda__acciones">
            <a href="<?= e(URL_INSTAGRAM) ?>" class="boton boton--claro" target="_blank" rel="noopener">Instagram</a>
            <a href="<?= e(URL_FACEBOOK) ?>" class="boton boton--contorno-claro" target="_blank" rel="noopener">Facebook</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
