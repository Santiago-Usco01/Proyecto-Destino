<?php
/**
 * Encabezado del panel. Antes de incluirlo, la página define:
 *   $titulo_pagina (string)
 *   $seccion       ('inicio', 'pedidos', 'reservas', 'productos', 'categorias', 'sedes', 'usuarios')
 */
$titulo_pagina ??= 'Panel';
$seccion ??= '';

$secciones = [
    'inicio'     => ['Resumen', 'admin/index.php'],
    'pedidos'    => ['Pedidos', 'admin/pedidos.php'],
    'reservas'   => ['Reservas', 'admin/reservas.php'],
    'productos'  => ['Productos', 'admin/productos.php'],
    'categorias' => ['Categorías', 'admin/categorias.php'],
    'sedes'      => ['Sedes', 'admin/sedes.php'],
    'usuarios'   => ['Usuarios', 'admin/usuarios.php'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titulo_pagina) ?> · Panel · <?= e(NEGOCIO_NOMBRE_CORTO) ?></title>
    <link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/estilos.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>">
</head>
<body class="admin">

<header class="admin__barra">
    <div class="contenedor admin__barra-contenido">
        <a class="admin__marca" href="<?= url('admin/index.php') ?>">Panel · <?= e(NEGOCIO_NOMBRE_CORTO) ?></a>
        <nav aria-label="Panel de administración">
            <ul class="admin__nav">
                <?php foreach ($secciones as $clave => [$texto, $ruta]): ?>
                    <li><a href="<?= url($ruta) ?>"<?= $seccion === $clave ? ' class="activo" aria-current="page"' : '' ?>><?= e($texto) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="admin__usuario">
            <a href="<?= url('index.php') ?>">Ver sitio</a>
            <a href="<?= url('logout.php') ?>?csrf=<?= csrf_token() ?>">Salir</a>
        </div>
    </div>
</header>

<main class="contenedor admin__contenido">
    <?php foreach (obtener_flash() as $m): ?>
        <div class="aviso aviso--<?= e($m['tipo']) ?>" role="status"><?= e($m['mensaje']) ?></div>
    <?php endforeach; ?>
