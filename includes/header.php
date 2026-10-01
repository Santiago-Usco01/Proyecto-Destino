<?php
/**
 * Encabezado público.
 * Antes de incluirlo, cada página define:
 *   $titulo_pagina  (string) título de la pestaña
 *   $pagina_actual  (string) 'inicio', 'menu', 'reservas', 'cotizaciones', 'carrito', 'cuenta'
 */
$titulo_pagina ??= NEGOCIO_NOMBRE;
$pagina_actual ??= '';
$usuario = usuario_actual();

$enlaces = [
    'inicio'       => ['Inicio', 'index.php'],
    'menu'         => ['Menú', 'menu.php'],
    'reservas'     => ['Reservas', 'reservas.php'],
    'cotizaciones' => ['Cotizaciones', 'cotizaciones.php'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?> · <?= e(NEGOCIO_NOMBRE_CORTO) ?></title>
    <meta name="description" content="<?= e(NEGOCIO_NOMBRE) ?>: café, panadería y restaurante en <?= e(NEGOCIO_CIUDAD) ?>. Pide a domicilio o reserva tu mesa.">
    <link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/estilos.css') ?>">
</head>
<body>

<a class="saltar-contenido" href="#contenido">Saltar al contenido</a>

<header class="encabezado">
    <div class="contenedor encabezado__barra">
        <a class="logo" href="<?= url('index.php') ?>">
            <img src="<?= url('assets/img/logo-oscuro.png') ?>" alt="<?= e(NEGOCIO_NOMBRE) ?>, ir al inicio" width="389" height="196">
        </a>

        <button class="menu-movil" type="button" aria-expanded="false" aria-controls="navegacion">
            <span class="menu-movil__icono" aria-hidden="true"></span>
            <span class="sr-only">Abrir menú</span>
        </button>

        <nav id="navegacion" class="navegacion" aria-label="Principal">
            <ul class="navegacion__lista">
                <?php foreach ($enlaces as $clave => [$texto, $ruta]): ?>
                    <li>
                        <a href="<?= url($ruta) ?>"
                           class="navegacion__enlace<?= $pagina_actual === $clave ? ' activo' : '' ?>"
                           <?= $pagina_actual === $clave ? 'aria-current="page"' : '' ?>><?= e($texto) ?></a>
                    </li>
                <?php endforeach; ?>

                <?php if ($usuario): ?>
                    <?php if (es_admin()): ?>
                        <li><a href="<?= url('admin/index.php') ?>" class="navegacion__enlace">Panel</a></li>
                    <?php else: ?>
                        <li>
                            <a href="<?= url('mi-cuenta.php') ?>"
                               class="navegacion__enlace<?= $pagina_actual === 'cuenta' ? ' activo' : '' ?>">Mi cuenta</a>
                        </li>
                    <?php endif; ?>
                    <li><a href="<?= url('logout.php') ?>?csrf=<?= csrf_token() ?>" class="navegacion__enlace">Salir</a></li>
                <?php else: ?>
                    <li><a href="<?= url('login.php') ?>" class="navegacion__enlace">Ingresar</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <a href="<?= url('carrito.php') ?>"
           class="boton-carrito<?= $pagina_actual === 'carrito' ? ' activo' : '' ?>"
           aria-label="Ver carrito">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
                <path fill="currentColor" d="M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4ZM6.2 6l.9 5.3c.2 1 1 1.7 2 1.7h7.6c.9 0 1.7-.6 1.9-1.5L20 6H6.2ZM5.8 4H21a1 1 0 0 1 1 1.2l-1.6 7.7A4 4 0 0 1 16.7 16H9.1a4 4 0 0 1-3.9-3.3L3.4 3H2V1h2.2c.5 0 .9.3 1 .8L5.8 4Z"/>
            </svg>
            <span class="boton-carrito__contador" id="contador-carrito" hidden>0</span>
        </a>
    </div>
</header>

<main id="contenido">
    <?php $mensajes_flash = obtener_flash(); ?>
    <?php if ($mensajes_flash): ?>
        <div class="contenedor avisos">
            <?php foreach ($mensajes_flash as $m): ?>
                <div class="aviso aviso--<?= e($m['tipo']) ?>" role="status"><span class="aviso__texto"><?= e($m['mensaje']) ?></span></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
