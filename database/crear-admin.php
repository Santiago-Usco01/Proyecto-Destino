<?php
/**
 * Crea (o restablece) una cuenta de administrador.
 * Se ejecuta SOLO desde la terminal, nunca desde el navegador:
 *
 *   C:\xampp\php\php.exe database\crear-admin.php correo@ejemplo.com "Nombre del administrador"
 *
 * Genera una contraseña aleatoria, la guarda como hash y la muestra UNA vez.
 * Si el correo ya existe, le asigna el rol de administrador y una contraseña nueva.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se puede ejecutar desde la terminal.');
}

require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/db.php';
require dirname(__DIR__) . '/includes/funciones.php';

$email  = validar_email($argv[1] ?? null);
$nombre = limpiar_texto($argv[2] ?? 'Administrador Destino', 100);

if ($email === null) {
    fwrite(STDERR, "Uso: php database/crear-admin.php correo@ejemplo.com \"Nombre\"\n");
    exit(1);
}

// Contraseña aleatoria de 16 caracteres, sin caracteres fáciles de confundir.
$alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$clave = '';
for ($i = 0; $i < 16; $i++) {
    $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
}
$hash = password_hash($clave, PASSWORD_DEFAULT);

$rol_admin = db()->query("SELECT id FROM roles WHERE nombre = 'administrador'")->fetchColumn();
if (!$rol_admin) {
    fwrite(STDERR, "No existe el rol 'administrador'. Importa primero database/destino.sql.\n");
    exit(1);
}

$existe = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
$existe->execute([$email]);
$id = $existe->fetchColumn();

if ($id) {
    db()->prepare('UPDATE usuarios SET rol_id = ?, password_hash = ?, activo = 1 WHERE id = ?')
        ->execute([$rol_admin, $hash, $id]);
    $accion = 'Contraseña restablecida';
} else {
    db()->prepare('INSERT INTO usuarios (rol_id, nombre, email, password_hash) VALUES (?, ?, ?, ?)')
        ->execute([$rol_admin, $nombre, $email, $hash]);
    $accion = 'Administrador creado';
}

echo "$accion.\n";
echo "Correo:     $email\n";
echo "Contraseña: $clave\n";
echo "Guárdala en un lugar seguro: no se vuelve a mostrar.\n";
