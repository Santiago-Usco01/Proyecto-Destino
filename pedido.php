<?php
/**
 * Formulario de domicilio.
 * GET:  muestra el formulario y el resumen del carrito (pedido.js lo llena desde localStorage).
 * POST: valida, recalcula precios desde la BD, guarda el pedido y redirige a pedido-generado.php.
 */
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/pedidos.php';

$sedes = sedes_activas();
$sedes_por_id = array_column($sedes, null, 'id');
$usuario = usuario_actual();

// Valores del formulario (con datos del cliente registrado, si inició sesión).
$valores = [
    'nombre'        => $usuario['nombre'] ?? '',
    'telefono'      => '',
    'direccion'     => '',
    'barrio'        => '',
    'sede_id'       => count($sedes) === 1 ? $sedes[0]['id'] : '',
    'metodo_pago'   => '',
    'observaciones' => '',
];
if ($usuario) {
    $consulta = db()->prepare('SELECT telefono FROM usuarios WHERE id = ?');
    $consulta->execute([$usuario['id']]);
    $valores['telefono'] = $consulta->fetchColumn() ?: '';
}

$errores = [];          // errores por campo
$errores_carrito = [];  // problemas con los productos

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $valores = array_merge($valores, array_intersect_key($_POST, $valores));

    $nombre        = limpiar_texto($_POST['nombre'] ?? null, 100);
    $telefono      = normalizar_telefono($_POST['telefono'] ?? null);
    $direccion     = limpiar_texto($_POST['direccion'] ?? null, 255);
    $barrio        = limpiar_texto($_POST['barrio'] ?? null, 100);
    $sede_id       = filter_var($_POST['sede_id'] ?? null, FILTER_VALIDATE_INT);
    $metodo_pago   = $_POST['metodo_pago'] ?? '';
    $observaciones = limpiar_texto_largo($_POST['observaciones'] ?? null, 500);
    $carrito       = leer_carrito_enviado($_POST['carrito'] ?? null);

    if ($nombre === null || mb_strlen($nombre) < 3) {
        $errores['nombre'] = 'Escribe tu nombre.';
    }
    if ($telefono === null) {
        $errores['telefono'] = 'Escribe un número de 10 dígitos, por ejemplo 312 345 6789.';
    }
    if ($direccion === null || mb_strlen($direccion) < 5) {
        $errores['direccion'] = 'Escribe la dirección de entrega completa.';
    }
    if (!$sede_id || !isset($sedes_por_id[$sede_id])) {
        $errores['sede_id'] = 'Elige la sede desde la que quieres tu pedido.';
    }
    if (!is_string($metodo_pago) || !isset(METODOS_PAGO[$metodo_pago])) {
        $errores['metodo_pago'] = 'Elige cómo vas a pagar.';
    }

    if ($carrito === null) {
        $errores_carrito[] = 'Tu carrito está vacío o no es válido. Vuelve al menú y agrega los productos.';
    } elseif (!local_abierto()) {
        $errores_carrito[] = estado_local() . '. Tu carrito sigue guardado para cuando abramos.';
    } else {
        $preparado = preparar_lineas($carrito);
        $errores_carrito = $preparado['errores'];
    }

    if (!$errores && !$errores_carrito) {
        $datos = [
            'usuario_id'        => $usuario['id'] ?? null,
            'sede_id'           => $sede_id,
            'nombre_cliente'    => $nombre,
            'telefono_cliente'  => $telefono,
            'direccion_entrega' => $direccion,
            'barrio'            => $barrio,
            'metodo_pago'       => $metodo_pago,
            'observaciones'     => $observaciones,
            'subtotal'          => $preparado['subtotal'],
        ];

        $pedido_id = guardar_pedido($datos, $preparado['lineas']);
        $sede = $sedes_por_id[$sede_id];

        // El resumen viaja por la sesión, no por la URL: nadie puede ver pedidos ajenos cambiando un número.
        $_SESSION['ultimo_pedido'] = [
            'codigo'   => codigo_pedido($pedido_id),
            'sede'     => $sede['nombre'],
            'telefono' => $sede['telefono'],
            'lineas'   => $preparado['lineas'],
            'subtotal' => $preparado['subtotal'],
            'enlace'   => enlace_whatsapp($sede['whatsapp'], mensaje_whatsapp_pedido($pedido_id, $datos, $preparado['lineas'], $sede)),
        ];
        redirigir('pedido-generado.php');
    }
}

$titulo_pagina = 'Datos del domicilio';
$pagina_actual = 'carrito';
$scripts_pagina = ['assets/js/pedido.js'];
require __DIR__ . '/includes/header.php';
?>

<section class="cabecera-pagina">
    <div class="contenedor">
        <span class="antetitulo">Paso 2 de 3</span>
        <h1>Datos del domicilio</h1>
        <p class="texto-suave">Con estos datos armamos el mensaje de WhatsApp para la sede.</p>
    </div>
</section>

<div class="contenedor pedido">
    <?php if ($errores_carrito): ?>
        <div class="aviso aviso--error" role="alert">
            <p>No pudimos generar tu pedido:</p>
            <ul>
                <?php foreach ($errores_carrito as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="<?= url('carrito.php') ?>">Revisar el carrito</a>
        </div>
    <?php elseif ($errores): ?>
        <div class="aviso aviso--error" role="alert">Revisa los campos marcados.</div>
    <?php endif; ?>

    <div class="carrito-vacio tarjeta" id="pedido-vacio" hidden>
        <h2>Tu carrito está vacío</h2>
        <p class="texto-suave">Agrega productos desde el menú para hacer tu pedido.</p>
        <a href="<?= url('menu.php') ?>" class="boton boton--primario">Ir al menú</a>
    </div>

    <form method="post" class="pedido__rejilla" id="formulario-pedido" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="carrito" id="campo-carrito" value="">

        <div class="formulario tarjeta">
            <fieldset class="grupo">
                <legend>Tus datos</legend>

                <div class="campo">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" required autocomplete="name"
                           value="<?= e($valores['nombre']) ?>"<?= attr_error($errores, 'nombre') ?>>
                    <?php if (isset($errores['nombre'])): ?><p class="error-campo" id="error-nombre"><?= e($errores['nombre']) ?></p><?php endif; ?>
                </div>

                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" maxlength="20" required autocomplete="tel" inputmode="tel"
                           placeholder="312 345 6789" value="<?= e($valores['telefono']) ?>"<?= attr_error($errores, 'telefono') ?>>
                    <?php if (isset($errores['telefono'])): ?><p class="error-campo" id="error-telefono"><?= e($errores['telefono']) ?></p><?php endif; ?>
                </div>
            </fieldset>

            <fieldset class="grupo">
                <legend>Entrega</legend>

                <div class="campo">
                    <label for="direccion">Dirección de entrega</label>
                    <input type="text" id="direccion" name="direccion" maxlength="255" required autocomplete="street-address"
                           placeholder="Calle 10 # 5-20, apto 301" value="<?= e($valores['direccion']) ?>"<?= attr_error($errores, 'direccion') ?>>
                    <?php if (isset($errores['direccion'])): ?><p class="error-campo" id="error-direccion"><?= e($errores['direccion']) ?></p><?php endif; ?>
                </div>

                <div class="campo">
                    <label for="barrio">Barrio <span class="opcional">(opcional)</span></label>
                    <input type="text" id="barrio" name="barrio" maxlength="100" value="<?= e($valores['barrio']) ?>">
                </div>

                <div class="campo">
                    <span class="campo__etiqueta" id="etiqueta-sede">Sede que prepara tu pedido</span>
                    <div class="opciones opciones--tarjetas" role="radiogroup" aria-labelledby="etiqueta-sede"<?= attr_error($errores, 'sede_id') ?>>
                        <?php foreach ($sedes as $sede): ?>
                            <label class="opcion">
                                <input type="radio" name="sede_id" value="<?= (int) $sede['id'] ?>" required
                                       <?= (string) $valores['sede_id'] === (string) $sede['id'] ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($sede['nombre']) ?></strong>
                                    <small><?= e($sede['direccion']) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errores['sede_id'])): ?><p class="error-campo" id="error-sede_id"><?= e($errores['sede_id']) ?></p><?php endif; ?>
                </div>
            </fieldset>

            <fieldset class="grupo">
                <legend>Pago</legend>

                <div class="campo">
                    <span class="campo__etiqueta" id="etiqueta-pago">Método de pago</span>
                    <div class="opciones" role="radiogroup" aria-labelledby="etiqueta-pago"<?= attr_error($errores, 'metodo_pago') ?>>
                        <?php foreach (METODOS_PAGO as $clave => $texto): ?>
                            <label class="opcion">
                                <input type="radio" name="metodo_pago" value="<?= e($clave) ?>" required
                                       <?= $valores['metodo_pago'] === $clave ? 'checked' : '' ?>>
                                <span><?= e($texto) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="ayuda">No se paga en línea. Si eliges transferencia, el encargado te enviará los datos por WhatsApp.</p>
                    <?php if (isset($errores['metodo_pago'])): ?><p class="error-campo" id="error-metodo_pago"><?= e($errores['metodo_pago']) ?></p><?php endif; ?>
                </div>

                <div class="campo">
                    <label for="observaciones">Observaciones <span class="opcional">(opcional)</span></label>
                    <textarea id="observaciones" name="observaciones" maxlength="500"
                              placeholder="Ej.: pizza mitad hawaiana y mitad pepperoni, sin cebolla, timbre dañado…"><?= e($valores['observaciones']) ?></textarea>
                </div>
            </fieldset>
        </div>

        <aside class="resumen tarjeta" aria-labelledby="titulo-resumen">
            <h2 id="titulo-resumen">Tu pedido</h2>
            <p class="carrito__cargando" id="resumen-cargando">Cargando…</p>
            <ul class="resumen__lineas" id="resumen-lineas"></ul>
            <dl class="resumen__filas">
                <div>
                    <dt>Subtotal</dt>
                    <dd id="resumen-subtotal">$0</dd>
                </div>
                <div>
                    <dt>Domicilio</dt>
                    <dd class="texto-suave">Por confirmar</dd>
                </div>
            </dl>
            <p class="resumen__nota">
                Al continuar se abrirá WhatsApp con tu pedido escrito. <strong>El pedido queda confirmado
                solo cuando el encargado de la sede te responda</strong> con el valor del domicilio.
            </p>
            <button type="submit" class="boton boton--primario boton--bloque" id="enviar-pedido">
                Generar pedido
            </button>
            <p class="resumen__bloqueo" id="resumen-bloqueo" hidden></p>
            <a href="<?= url('carrito.php') ?>" class="resumen__volver">Volver al carrito</a>
        </aside>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
