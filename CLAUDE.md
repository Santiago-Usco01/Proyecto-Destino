# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Proyecto

Sitio de Destino Café Pizza Pan (Neiva, Colombia): PHP 8.2 + MariaDB/MySQL + JS vanilla, sin frameworks, sin Composer, sin npm. Se ejecuta bajo Apache (XAMPP) en `C:\xampp\htdocs\destino`, URL `http://localhost/destino/`. El código y los textos de la UI están en español; mantener ese idioma en nombres, comentarios y mensajes.

No hay suite de pruebas, linter ni paso de build.

## Comandos

```bash
# Configuración local (obligatoria: config.php hace exit(500) si falta config.local.php, que está en .gitignore)
copy includes\config.ejemplo.php includes\config.local.php

# Crear/recrear la BD (BORRA la base `destino` si existe)
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source database/destino.sql"

# Crear el usuario administrador (genera contraseña aleatoria, se muestra una vez)
C:\xampp\php\php.exe database\crear-admin.php admin@destino.local "Administrador Destino"

# Comprobar sintaxis de un archivo
C:\xampp\php\php.exe -l includes\funciones.php
```

Si la carpeta no se llama `destino`, cambiar `BASE_URL` en `includes/config.php`. Para probar fuera del horario del menú, definir `HORA_PRUEBA` (p. ej. `'17:00:00'`) en `includes/config.local.php`.

## Arquitectura

- **Arranque**: cada página PHP empieza con `require __DIR__ . '/includes/init.php'`, que carga en orden `config.php` → `db.php` (PDO, `db()`) → `funciones.php` → `auth.php` (sesión `DESTINOSESSID`, roles, CSRF). `includes/header.php` / `footer.php` son la plantilla. Las páginas están en la raíz (`menu.php`, `carrito.php`, `pedido.php`, `pedido-generado.php`, `reservas.php`, `cotizaciones.php`); `includes/`, `database/` y `uploads/productos/` tienen su propio `.htaccess` que bloquea acceso o ejecución.
- **Sistema de solicitudes, no de transacciones**: no hay pagos. Un pedido o reserva se guarda como `pendiente`/`pendiente_confirmacion` y se confirma por WhatsApp con la sede (cada sede en la tabla `sedes` tiene su número). Las cotizaciones no se guardan; solo generan un mensaje de WhatsApp (`enlace_whatsapp()` en `funciones.php`).
- **Carrito en el navegador, precios en el servidor**: el carrito vive en `localStorage` como `{id: cantidad}` (clase `Carrito` en `assets/js/main.js`). `api/carrito.php` y `preparar_lineas()` en `includes/pedidos.php` consultan la BD y recalculan precio y disponibilidad. Nunca confiar en precios o totales enviados por el cliente. La disponibilidad por franja horaria se calcula con `SQL_FRANJA_PRODUCTO` (fragmento SQL compartido), `motivo_no_pedible()` y `local_abierto()`/`en_franja()` en `funciones.php`, usando horarios y reglas de negocio de `config.php` (constantes `HORA_*`, `RESERVA_*`, `PASTELERIA_*`).
- **JS por página**: `assets/js/` tiene un script por página (`carrito.js`, `pedido.js`, `menu.js`, …) que depende de `main.js`.
- **BD**: 8 tablas (`roles`, `usuarios`, `categorias`, `productos`, `sedes`, `pedidos`, `detalle_pedido`, `reservas`). Datos se desactivan/cancelan, no se borran (FK lo impiden). Precios en pesos enteros `INT UNSIGNED`; teléfonos como `VARCHAR`. Diseño completo en `docs/modelo-relacional.md`. `database/destino.sql` es la única fuente del esquema y datos iniciales.

## Convenciones

- Toda salida a HTML pasa por `e()`; todas las consultas son sentencias preparadas PDO; los formularios llevan token CSRF (`auth.php`).
- Códigos legibles: `codigo_pedido()` → `DST-000123`, `codigo_reserva()` → `RES-000007`; precios con `precio()`; rutas con `url()` y `redirigir()` (respetan `BASE_URL`).
- Pendiente según el README: registro/login/"Mi cuenta" y panel de administración (`auth.php` ya tiene sesión y roles; las subidas irían a `uploads/productos/`).
