<?php
/**
 * Usuarios: listado con filtros, creación y edición (nombre, teléfono, rol, estado, contraseña).
 * ?id=N edita; ?nuevo=1 crea. No se borran usuarios: se desactivan.
 * Un administrador no puede quitarse a sí mismo el rol ni desactivarse (siempre queda uno activo).
 */
require __DIR__ . '/../includes/admin.php';

const CLAVE_MIN = 8;

$yo = usuario_actual()['id'];
$roles = db()->query('SELECT id, nombre, descripcion FROM roles ORDER BY id')->fetchAll();
$roles_por_id = array_column($roles, null, 'id');

$id = id_recibido();
$nuevo = isset($_GET['nuevo']);
$usuario = null;
if ($id) {
    $consulta = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
    $consulta->execute([$id]);
    $usuario = $consulta->fetch();
    if (!$usuario) {
        flash('error', 'El usuario no existe.');
        redirigir('admin/usuarios.php');
    }
}
$formulario = $usuario || $nuevo;
$es_yo = $usuario && (int) $usuario['id'] === $yo;

$errores = [];
$valores = [
    'nombre'   => $usuario['nombre'] ?? '',
    'email'    => $usuario['email'] ?? '',
    'telefono' => $usuario['telefono'] ?? '',
    'rol_id'   => (int) ($usuario['rol_id'] ?? 1),
    'activo'   => $usuario ? (int) $usuario['activo'] : 1,
];

if ($formulario && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    foreach (['nombre', 'email', 'telefono'] as $campo) {
        $valores[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }
    $rol_id = filter_var($_POST['rol_id'] ?? null, FILTER_VALIDATE_INT);
    $valores['rol_id'] = $rol_id === false ? 0 : $rol_id;
    $valores['activo'] = isset($_POST['activo']) ? 1 : 0;
    if ($es_yo) {
        // Protección: no se puede degradar ni desactivar la propia cuenta.
        $valores['rol_id'] = (int) $usuario['rol_id'];
        $valores['activo'] = 1;
    }

    $nombre    = limpiar_texto($_POST['nombre'] ?? null, 100);
    $email     = $usuario ? $usuario['email'] : validar_email($_POST['email'] ?? null);
    $tel_crudo = limpiar_texto($_POST['telefono'] ?? null, 20);
    $telefono  = $tel_crudo === null ? null : normalizar_telefono($tel_crudo);
    $clave     = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($nombre === null || mb_strlen($nombre) < 3) {
        $errores['nombre'] = 'Escribe el nombre.';
    }
    if ($email === null) {
        $errores['email'] = 'Escribe un correo válido.';
    }
    if ($tel_crudo !== null && $telefono === null) {
        $errores['telefono'] = 'Escribe un número de 10 dígitos o déjalo vacío.';
    }
    if (!isset($roles_por_id[$valores['rol_id']])) {
        $errores['rol_id'] = 'Elige un rol.';
    }
    // En la creación la contraseña es obligatoria; al editar, solo si se quiere cambiar.
    if (!$usuario || $clave !== '') {
        if (strlen($clave) < CLAVE_MIN) {
            $errores['password'] = 'La contraseña debe tener al menos ' . CLAVE_MIN . ' caracteres.';
        } elseif (strlen($clave) > 72) {
            $errores['password'] = 'La contraseña es demasiado larga (máximo 72 caracteres).';
        }
    }

    if (!$errores) {
        try {
            if ($usuario) {
                $sql = 'UPDATE usuarios SET nombre = ?, telefono = ?, rol_id = ?, activo = ?';
                $params = [$nombre, $telefono, $valores['rol_id'], $valores['activo']];
                if ($clave !== '') {
                    $sql .= ', password_hash = ?';
                    $params[] = password_hash($clave, PASSWORD_DEFAULT);
                }
                db()->prepare($sql . ' WHERE id = ?')->execute([...$params, $id]);
                flash('exito', 'Usuario actualizado.');
            } else {
                db()->prepare('INSERT INTO usuarios (rol_id, nombre, email, telefono, password_hash, activo) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$valores['rol_id'], $nombre, $email, $telefono, password_hash($clave, PASSWORD_DEFAULT), $valores['activo']]);
                flash('exito', 'Usuario creado.');
            }
            redirigir('admin/usuarios.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $errores['email'] = 'Ya existe un usuario con ese correo.';
        }
    }
}

$titulo_pagina = 'Usuarios';
$seccion = 'usuarios';

// ---------------------------------------------------------------- Listado
if (!$formulario) {
    $rol_filtro = filter_var($_GET['rol'] ?? null, FILTER_VALIDATE_INT);
    $rol_filtro = isset($roles_por_id[$rol_filtro]) ? $rol_filtro : 0;
    $busqueda = limpiar_texto($_GET['q'] ?? null, 100) ?? '';

    $sql = 'SELECT u.id, u.nombre, u.email, u.telefono, u.activo, u.created_at, r.nombre AS rol
            FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE 1 = 1';
    $params = [];
    if ($rol_filtro) {
        $sql .= ' AND u.rol_id = ?';
        $params[] = $rol_filtro;
    }
    if ($busqueda !== '') {
        $sql .= ' AND (u.nombre LIKE ? OR u.email LIKE ?)';
        $like = '%' . addcslashes($busqueda, '%_\\') . '%';
        array_push($params, $like, $like);
    }
    $consulta = db()->prepare($sql . ' ORDER BY u.activo DESC, u.nombre LIMIT 200');
    $consulta->execute($params);
    $usuarios = $consulta->fetchAll();

    require __DIR__ . '/../includes/admin-header.php';
    ?>
    <div class="admin__titulo">
        <h1>Usuarios</h1>
        <a class="boton boton--primario boton--pequeno" href="<?= url('admin/usuarios.php?nuevo=1') ?>">Nuevo usuario</a>
    </div>

    <nav class="admin__filtros" aria-label="Filtrar por rol">
        <a href="<?= url('admin/usuarios.php' . ($busqueda !== '' ? '?q=' . urlencode($busqueda) : '')) ?>"<?= !$rol_filtro ? ' class="activo"' : '' ?>>Todos</a>
        <?php foreach ($roles as $r): ?>
            <a href="<?= url('admin/usuarios.php?rol=' . (int) $r['id'] . ($busqueda !== '' ? '&q=' . urlencode($busqueda) : '')) ?>"<?= $rol_filtro === (int) $r['id'] ? ' class="activo"' : '' ?>><?= e(ucfirst($r['nombre'])) ?></a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="admin__filtros">
        <?php if ($rol_filtro): ?><input type="hidden" name="rol" value="<?= $rol_filtro ?>"><?php endif; ?>
        <input type="search" name="q" maxlength="100" placeholder="Buscar por nombre o correo" value="<?= e($busqueda) ?>" aria-label="Buscar usuarios">
        <button type="submit" class="boton boton--secundario boton--pequeno">Buscar</button>
    </form>

    <?php if (!$usuarios): ?>
        <p class="tarjeta admin__vacio">No hay usuarios con esos filtros.</p>
    <?php else: ?>
        <div class="tabla-envoltorio"><table class="tabla">
            <thead><tr><th>Usuario</th><th>Teléfono</th><th>Rol</th><th>Registro</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr class="<?= $u['activo'] ? '' : 'inactivo' ?>">
                    <td><?= e($u['nombre']) ?><?= (int) $u['id'] === $yo ? ' <small>(tú)</small>' : '' ?><br><small><?= e($u['email']) ?></small></td>
                    <td><?= $u['telefono'] ? e(telefono_legible($u['telefono'])) : '—' ?></td>
                    <td><?= e(ucfirst($u['rol'])) ?></td>
                    <td><?= e(fecha_legible(substr($u['created_at'], 0, 10))) ?></td>
                    <td><?= $u['activo'] ? '<span class="etiqueta etiqueta--exito">Activo</span>' : '<span class="etiqueta etiqueta--error">Inactivo</span>' ?></td>
                    <td><a class="boton boton--secundario boton--pequeno" href="<?= url('admin/usuarios.php?id=' . (int) $u['id']) ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif;
    require __DIR__ . '/../includes/admin-footer.php';
    exit;
}

// ---------------------------------------------------------------- Formulario
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin__titulo">
    <h1><?= $usuario ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
    <a href="<?= url('admin/usuarios.php') ?>" class="boton boton--secundario boton--pequeno">← Volver</a>
</div>

<?php if ($errores): ?>
    <div class="aviso aviso--error" role="alert">Revisa los campos marcados.</div>
<?php endif; ?>

<form method="post" class="formulario tarjeta admin__formulario" novalidate>
    <?= csrf_campo() ?>

    <div class="campo">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" maxlength="100" required value="<?= e($valores['nombre']) ?>"<?= attr_error($errores, 'nombre') ?>>
        <?= mensaje_error($errores, 'nombre') ?>
    </div>
    <div class="campo--doble">
        <div class="campo">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" maxlength="150" required value="<?= e($valores['email']) ?>"<?= $usuario ? ' readonly' : '' ?><?= attr_error($errores, 'email') ?>>
            <?= mensaje_error($errores, 'email') ?>
        </div>
        <div class="campo">
            <label for="telefono">Teléfono <span class="opcional">(opcional)</span></label>
            <input type="tel" id="telefono" name="telefono" maxlength="20" inputmode="tel" value="<?= e($valores['telefono']) ?>"<?= attr_error($errores, 'telefono') ?>>
            <?= mensaje_error($errores, 'telefono') ?>
        </div>
    </div>
    <div class="campo">
        <label for="rol_id">Rol</label>
        <select id="rol_id" name="rol_id"<?= $es_yo ? ' disabled' : '' ?><?= attr_error($errores, 'rol_id') ?>>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int) $r['id'] ?>"<?= $valores['rol_id'] === (int) $r['id'] ? ' selected' : '' ?>><?= e(ucfirst($r['nombre'])) ?> — <?= e($r['descripcion']) ?></option>
            <?php endforeach; ?>
        </select>
        <?= mensaje_error($errores, 'rol_id') ?>
        <?php if ($es_yo): ?><small>No puedes cambiar tu propio rol.</small><?php endif; ?>
    </div>
    <div class="campo">
        <label for="password"><?= $usuario ? 'Nueva contraseña <span class="opcional">(déjala vacía para no cambiarla)</span>' : 'Contraseña inicial' ?></label>
        <input type="password" id="password" name="password" minlength="<?= CLAVE_MIN ?>" autocomplete="new-password"<?= $usuario ? '' : ' required' ?><?= attr_error($errores, 'password') ?>>
        <?= mensaje_error($errores, 'password') ?>
    </div>
    <div class="campo--check">
        <input type="checkbox" id="activo" name="activo" value="1"<?= $valores['activo'] ? ' checked' : '' ?><?= $es_yo ? ' disabled' : '' ?>>
        <label for="activo">Cuenta activa (puede iniciar sesión)<?= $es_yo ? ' — no puedes desactivar tu propia cuenta' : '' ?></label>
    </div>

    <button type="submit" class="boton boton--primario"><?= $usuario ? 'Guardar cambios' : 'Crear usuario' ?></button>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
