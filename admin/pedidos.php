<?php
/**
 * Pedidos: listado con filtro por estado y detalle (cambiar estado y costo de domicilio).
 */
require __DIR__ . '/../includes/admin.php';
require __DIR__ . '/../includes/pedidos.php';   // METODOS_PAGO

$detalle_id = id_recibido();

// ---------------------------------------------------------------- Acciones (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $detalle_id) {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'estado') {
        $estado = $_POST['estado'] ?? '';
        if (isset(ESTADOS_PEDIDO[$estado])) {
            db()->prepare('UPDATE pedidos SET estado = ? WHERE id = ?')->execute([$estado, $detalle_id]);
            flash('exito', 'Estado actualizado.');
        } else {
            flash('error', 'Estado no válido.');
        }
    } elseif ($accion === 'domicilio') {
        $costo = trim((string) ($_POST['costo_domicilio'] ?? ''));
        $valor = $costo === '' ? null : entero_desde_texto($costo);
        if ($costo !== '' && $valor === null) {
            flash('error', 'El costo de domicilio no es válido.');
        } else {
            db()->prepare('UPDATE pedidos SET costo_domicilio = ? WHERE id = ?')->execute([$valor, $detalle_id]);
            flash('exito', 'Costo de domicilio guardado.');
        }
    }
    redirigir('admin/pedidos.php?id=' . $detalle_id);
}

$titulo_pagina = 'Pedidos';
$seccion = 'pedidos';

// ---------------------------------------------------------------- Detalle
if ($detalle_id) {
    $consulta = db()->prepare('SELECT p.*, s.nombre AS sede FROM pedidos p JOIN sedes s ON s.id = p.sede_id WHERE p.id = ?');
    $consulta->execute([$detalle_id]);
    $pedido = $consulta->fetch();
    if (!$pedido) {
        flash('error', 'El pedido no existe.');
        redirigir('admin/pedidos.php');
    }
    $lineas = db()->prepare('SELECT nombre_producto, precio_unitario, cantidad, subtotal_linea FROM detalle_pedido WHERE pedido_id = ? ORDER BY id');
    $lineas->execute([$detalle_id]);
    $lineas = $lineas->fetchAll();

    $titulo_pagina = codigo_pedido($detalle_id);
    require __DIR__ . '/../includes/admin-header.php';
    ?>
    <div class="admin__titulo">
        <h1><?= e(codigo_pedido($detalle_id)) ?> <?= etiqueta_estado($pedido['estado'], ESTADOS_PEDIDO) ?></h1>
        <a href="<?= url('admin/pedidos.php') ?>" class="boton boton--secundario boton--pequeno">← Volver a pedidos</a>
    </div>

    <div class="admin__rejilla">
        <section class="tarjeta">
            <h2>Cliente y entrega</h2>
            <dl class="resumen__filas">
                <div><dt>Cliente</dt><dd><?= e($pedido['nombre_cliente']) ?></dd></div>
                <div><dt>Teléfono</dt><dd><?= e(telefono_legible($pedido['telefono_cliente'])) ?></dd></div>
                <div><dt>Dirección</dt><dd><?= e($pedido['direccion_entrega']) ?></dd></div>
                <?php if ($pedido['barrio']): ?><div><dt>Barrio</dt><dd><?= e($pedido['barrio']) ?></dd></div><?php endif; ?>
                <div><dt>Sede</dt><dd><?= e($pedido['sede']) ?></dd></div>
                <div><dt>Pago</dt><dd><?= e(METODOS_PAGO[$pedido['metodo_pago']] ?? $pedido['metodo_pago']) ?></dd></div>
                <div><dt>Solicitado</dt><dd><?= e(fecha_legible(substr($pedido['created_at'], 0, 10))) ?>, <?= e(hora_legible(substr($pedido['created_at'], 11))) ?></dd></div>
                <?php if ($pedido['observaciones']): ?><div><dt>Observaciones</dt><dd><?= e($pedido['observaciones']) ?></dd></div><?php endif; ?>
            </dl>
            <a class="boton boton--whatsapp boton--pequeno" target="_blank" rel="noopener"
               href="<?= e(enlace_whatsapp('57' . $pedido['telefono_cliente'], 'Hola ' . $pedido['nombre_cliente'] . ', te escribimos de ' . NEGOCIO_NOMBRE . ' sobre tu pedido ' . codigo_pedido($detalle_id) . '.')) ?>">
                Escribir al cliente
            </a>
        </section>

        <section class="tarjeta">
            <h2>Productos</h2>
            <div class="tabla-envoltorio"><table class="tabla">
                <thead><tr><th>Producto</th><th class="num">Cant.</th><th class="num">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($lineas as $l): ?>
                    <tr>
                        <td><?= e($l['nombre_producto']) ?><br><small><?= e(precio((int) $l['precio_unitario'])) ?> c/u</small></td>
                        <td class="num"><?= (int) $l['cantidad'] ?></td>
                        <td class="num"><?= e(precio((int) $l['subtotal_linea'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <dl class="resumen__filas" style="margin-top:16px">
                <div><dt>Subtotal</dt><dd><?= e(precio((int) $pedido['subtotal'])) ?></dd></div>
                <div><dt>Domicilio</dt><dd><?= $pedido['costo_domicilio'] === null ? 'Por definir' : e(precio((int) $pedido['costo_domicilio'])) ?></dd></div>
                <div class="resumen__total"><dt>Total</dt><dd><?= $pedido['total'] === null ? '—' : e(precio((int) $pedido['total'])) ?></dd></div>
            </dl>
        </section>

        <section class="tarjeta">
            <h2>Gestionar</h2>
            <form method="post" class="formulario">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= $detalle_id ?>">
                <input type="hidden" name="accion" value="estado">
                <div class="campo">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <?php foreach (ESTADOS_PEDIDO as $valor => $texto): ?>
                            <option value="<?= e($valor) ?>"<?= $pedido['estado'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="boton boton--primario boton--pequeno">Guardar estado</button>
            </form>
            <hr style="border:0;border-top:1px solid var(--borde);margin:20px 0">
            <form method="post" class="formulario">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= $detalle_id ?>">
                <input type="hidden" name="accion" value="domicilio">
                <div class="campo">
                    <label for="costo_domicilio">Costo de domicilio (COP)</label>
                    <input type="text" id="costo_domicilio" name="costo_domicilio" inputmode="numeric"
                           value="<?= $pedido['costo_domicilio'] === null ? '' : (int) $pedido['costo_domicilio'] ?>">
                    <span class="ayuda">Déjalo vacío si aún no se ha definido.</span>
                </div>
                <button type="submit" class="boton boton--primario boton--pequeno">Guardar costo</button>
            </form>
        </section>
    </div>
    <?php
    require __DIR__ . '/../includes/admin-footer.php';
    exit;
}

// ---------------------------------------------------------------- Listado
$filtro = $_GET['estado'] ?? '';
$filtro = isset(ESTADOS_PEDIDO[$filtro]) ? $filtro : '';

$sql = 'SELECT p.id, p.nombre_cliente, p.total, p.estado, p.created_at, s.nombre AS sede
        FROM pedidos p JOIN sedes s ON s.id = p.sede_id'
     . ($filtro ? ' WHERE p.estado = ?' : '')
     . ' ORDER BY p.created_at DESC LIMIT 100';
$consulta = db()->prepare($sql);
$consulta->execute($filtro ? [$filtro] : []);
$pedidos = $consulta->fetchAll();

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin__titulo"><h1>Pedidos</h1></div>

<nav class="admin__filtros" aria-label="Filtrar por estado">
    <a href="<?= url('admin/pedidos.php') ?>"<?= $filtro === '' ? ' class="activo"' : '' ?>>Todos</a>
    <?php foreach (ESTADOS_PEDIDO as $valor => $texto): ?>
        <a href="<?= url('admin/pedidos.php?estado=' . $valor) ?>"<?= $filtro === $valor ? ' class="activo"' : '' ?>><?= e($texto) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$pedidos): ?>
    <p class="tarjeta admin__vacio">No hay pedidos<?= $filtro ? ' con ese estado' : '' ?>.</p>
<?php else: ?>
    <div class="tabla-envoltorio"><table class="tabla">
        <thead><tr><th>Pedido</th><th>Cliente</th><th>Sede</th><th>Fecha</th><th class="num">Total</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($pedidos as $p): ?>
            <tr>
                <td><a href="<?= url('admin/pedidos.php?id=' . (int) $p['id']) ?>"><?= e(codigo_pedido((int) $p['id'])) ?></a></td>
                <td><?= e($p['nombre_cliente']) ?></td>
                <td><?= e($p['sede']) ?></td>
                <td><?= e(fecha_legible(substr($p['created_at'], 0, 10))) ?>, <?= e(hora_legible(substr($p['created_at'], 11))) ?></td>
                <td class="num"><?= $p['total'] === null ? 'Por definir' : e(precio((int) $p['total'])) ?></td>
                <td><?= etiqueta_estado($p['estado'], ESTADOS_PEDIDO) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="texto-suave">Se muestran los 100 más recientes.</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
