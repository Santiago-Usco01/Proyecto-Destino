# Destino Café Pizza Pan

Sitio web para **Destino**, café, panadería y restaurante con dos sedes en Neiva, Huila (Colombia).

Los clientes consultan el menú, arman un carrito y envían su pedido a domicilio **por WhatsApp** a la sede que elijan. También pueden solicitar reservas de mesa y pedir cotizaciones de pan al por mayor o de pastelería para eventos.

> Proyecto universitario individual desarrollado con PHP, MySQL, HTML, CSS y JavaScript, sin frameworks.

## Cómo funciona

- **No hay pagos en línea.** El sitio genera una *solicitud*; la confirmación del pedido y el valor del domicilio se acuerdan por WhatsApp con el encargado de la sede.
- Los pedidos y las reservas se guardan en la base de datos con estado `pendiente` para que el administrador los gestione.
- Las cotizaciones no se guardan: solo arman un mensaje de WhatsApp.

## Funcionalidades

| Módulo | Estado |
|---|---|
| Página de inicio (sedes, horarios, redes, cómo pedir) | ✅ |
| Menú por categorías con franjas horarias y buscador | ✅ |
| Carrito (localStorage) con precios validados en el servidor | ✅ |
| Pedido a domicilio + mensaje de WhatsApp | ✅ |
| Reservas de mesa | ✅ |
| Cotizaciones (mayoristas y pastelería) por WhatsApp | ✅ |
| Registro, inicio de sesión y "Mi cuenta" | 🚧 Pendiente |
| Panel de administración | 🚧 Pendiente |

## Tecnologías

- PHP 8.2 (PDO, sesiones, `password_hash`)
- MariaDB 10.4 / MySQL (InnoDB, utf8mb4)
- HTML5, CSS3 propio (sin frameworks) y JavaScript sin librerías
- Apache (XAMPP)

## Instalación local con XAMPP

1. **Instala [XAMPP](https://www.apachefriends.org/)** con PHP 8.1 o superior y enciende **Apache** y **MySQL**.

2. **Clona el repositorio** dentro de `htdocs` con el nombre `destino`:

   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/Santiago-Usco01/Proyecto-Destino.git destino
   cd destino
   ```

   Los comandos siguientes se ejecutan dentro de esa carpeta.

   > Si usas otro nombre de carpeta, cambia `BASE_URL` en `includes/config.php`.

3. **Crea la configuración local** copiando la plantilla:

   ```bash
   copy includes\config.ejemplo.php includes\config.local.php
   ```

   Con XAMPP por defecto (usuario `root` sin contraseña) no hay que cambiar nada.

4. **Crea la base de datos** importando `database/destino.sql`, desde phpMyAdmin (*Importar*) o desde la terminal:

   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source database/destino.sql"
   ```

   > ⚠️ El script **borra** la base `destino` si ya existe y la crea de nuevo.

5. **Crea el usuario administrador.** El script genera una contraseña aleatoria y la muestra una sola vez:

   ```bash
   C:\xampp\php\php.exe database\crear-admin.php admin@destino.local "Administrador Destino"
   ```

6. Abre **http://localhost/destino/**

### Probar fuera del horario

El menú solo deja pedir productos dentro de su horario (el local abre de 7:00 a. m. a 10:00 p. m.). Para probar a otra hora, en `includes/config.local.php`:

```php
define('HORA_PRUEBA', '17:00:00'); // null = hora real
```

## Estructura

```
destino/
├── index.php, menu.php, carrito.php, pedido.php, pedido-generado.php,
│   reservas.php, cotizaciones.php      Páginas públicas
├── api/carrito.php                     Precios y disponibilidad reales (JSON)
├── includes/                           Configuración, conexión, sesión, funciones y plantilla
│                                       (bloqueado al navegador con .htaccess)
├── assets/css, assets/js, assets/img   Estilos, scripts e imágenes
├── uploads/productos/                  Imágenes subidas desde el panel (no ejecuta PHP)
├── database/                           Script SQL y creación del administrador
│                                       (bloqueado al navegador con .htaccess)
└── docs/                               Documentación del modelo de datos
```

## Base de datos

8 tablas: `roles`, `usuarios`, `categorias`, `productos`, `sedes`, `pedidos`, `detalle_pedido` y `reservas`.
El diseño completo, las relaciones y las decisiones están en [docs/modelo-relacional.md](docs/modelo-relacional.md).

## Seguridad

- Contraseñas con `password_hash()` (bcrypt); nunca en texto plano ni en el repositorio.
- Todas las consultas usan sentencias preparadas (PDO) contra inyección SQL.
- Toda salida se escapa con `htmlspecialchars()` contra XSS.
- Token CSRF en los formularios.
- Cookie de sesión `HttpOnly` y `SameSite=Lax`; el identificador de sesión se regenera al iniciar sesión.
- El servidor recalcula precios y disponibilidad: el navegador nunca decide el precio.
- Credenciales fuera del repositorio (`includes/config.local.php`).

## Autores

**Santiago Cardenas Claros** **Marianna Cubillos Polania** **Alejandro Barreiro Montealegre*

El nombre, el logo, el menú y los datos de contacto pertenecen a Destino Café Pizza Pan y se usan con fines académicos.
