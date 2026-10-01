<?php
/**
 * Reservas: listado con filtro por estado; el administrador confirma, rechaza o anota.
 */
require __DIR__ . '/../includes/admin.php';

$filtro = $_GET['estado'] ?? $_POST['filtro'] ?? '';
$filtro = isset(ESTADOS_RESERVA[$filtro]) ? $filtro : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $id = id_recibido();
    $estado = $_POST['estado'] ?? '';
    $nota = limpiar_texto($_POST['nota_admin'] ?? null, 255);

    if ($id && isset(ESTADOS_RESERVA[$estado])) {
        db()->prepare('UPDATE reservas SET estado = ?, nota_admin = ? WHERE id = ?')->execute([$estado, $nota, $id]);
        flash('exito', codigo_reserva($id) . ' actualizada.');
    } else {
        flash('error', 'Datos no válidos.');
    }
    redirigir('admin/reservas.php' . ($filtro ? '?estado=' . $filtro : ''));
}

$sql = 'SELECT r.*, s.nombre AS sede FROM reservas r JOIN sedes s ON s.id = r.sede_id'
     . ($filtro ? ' WHERE r.estado = ?' : '')
     . ' ORDER BY r.fecha DESC, r.hora DESC LIMIT 100';
$consulta = db()->prepare($sql);
$consulta->execute($filtro ? [$filtro] : []);
$reservas = $consulta->fetchAll();

$titulo_pagina = 'Reservas';
$seccion = 'reservas';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin__titulo"><h1>Reservas</h1></div>

<nav class="admin__filtros" aria-label="Filtrar por estado">
    <a href="<?= url('admin/reservas.php') ?>"<?= $filtro === '' ? ' class="activo"' : '' ?>>Todas</a>
    <?php foreach (ESTADOS_RESERVA as $valor => $texto): ?>
        <a href="<?= url('admin/reservas.php?estado=' . $valor) ?>"<?= $filtro === $valor ? ' class="activo"' : '' ?>><?= e($texto) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$reservas): ?>
    <p class="tarjeta admin__vacio">No hay reservas<?= $filtro ? ' con ese estado' : '' ?>.</p>
<?php else: ?>
    <div class="tabla-envoltorio"><table class="tabla">
        <thead><tr><th>Reserva</th><th>Cliente</th><th>Cuándo</th><th>Personas</th><th>Gestionar</th></tr></thead>
        <tbody>
        <?php foreach ($reservas as $r): ?>
            <tr>
                <td><?= e(codigo_reserva((int) $r['id'])) ?><br><?= etiqueta_estado($r['estado'], ESTADOS_RESERVA) ?></td>
                <td>
                    <?= e($r['nombre_cliente']) ?><br>
                    <small><?= e(telefono_legible($r['telefono_cliente'])) ?></small>
                    <?php if ($r['observaciones']): ?><br><small><?= e($r['observaciones']) ?></small><?php endif; ?>
                </td>
                <td><?= e(fecha_legible($r['fecha'])) ?>, <?= e(hora_legible($r['hora'])) ?><br><small><?= e($r['sede']) ?></small></td>
                <td class="num"><?= (int) $r['num_personas'] ?></td>
                <td>
                    <form method="post">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
                        <div class="acciones">
                            <select name="estado" aria-label="Estado de <?= e(codigo_reserva((int) $r['id'])) ?>">
                                <?php foreach (ESTADOS_RESERVA as $valor => $texto): ?>
                                    <option value="<?= e($valor) ?>"<?= $r['estado'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="nota_admin" maxlength="255" placeholder="Nota interna"
                                   aria-label="Nota interna" value="<?= e($r['nota_admin'] ?? '') ?>">
                            <button type="submit" class="boton boton--primario boton--pequeno">Guardar</button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="texto-suave">Se muestran las 100 más recientes.</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
