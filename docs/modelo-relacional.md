# Modelo relacional — Destino

Base de datos `destino` · MariaDB 10.4 / MySQL · InnoDB · `utf8mb4_unicode_ci`
Script: [`database/destino.sql`](../database/destino.sql)

## Principios del diseño

1. **Destino es un sistema de solicitudes, no de transacciones.** No hay pagos en línea. Un pedido o una reserva guardados son *solicitudes*; la confirmación ocurre por WhatsApp y el administrador la refleja cambiando el estado.
2. **Solo se guarda lo que se usa.** Las cotizaciones (mayoristas y pastelería) no se almacenan: solo generan un mensaje de WhatsApp. El carrito vive en el navegador.
3. **La historia no se pierde.** Productos, categorías, sedes y usuarios se *desactivan*; pedidos y reservas se *cancelan*. Las claves foráneas impiden borrar lo que tiene historial.
4. **Precios en pesos enteros** (`INT UNSIGNED`), sin decimales.
5. **Teléfonos como texto** (`VARCHAR`), porque pueden tener prefijos o ceros iniciales.

## Tablas

| Tabla | Propósito |
|---|---|
| `roles` | Catálogo de roles: `cliente` y `administrador` |
| `usuarios` | Cuentas para iniciar sesión (contraseña como hash) |
| `categorias` | Agrupan los productos y definen su franja horaria |
| `productos` | Ítems del menú |
| `sedes` | Las dos sedes, con su número de WhatsApp |
| `pedidos` | Solicitudes de domicilio |
| `detalle_pedido` | Productos de cada pedido (resuelve la relación N:M) |
| `reservas` | Solicitudes de reserva de mesa |

### Tablas descartadas

| Tabla | Motivo |
|---|---|
| `cotizaciones_mayoristas`, `cotizaciones_pasteleria` | Se gestionan por WhatsApp; guardarlas acumularía datos personales sin uso |
| `resenas` | No forman parte de los requisitos |
| `carrito` | El carrito es temporal; vive en `localStorage` y el servidor revalida precios |
| `horarios_sede`, `producto_sede` | El horario es texto libre y todos los productos están en ambas sedes |

## Diagrama de relaciones

```
roles ─────1:N────► usuarios ─────1:N (opcional)────► pedidos ◄────N:1──── sedes
                         │                               │                   │
                         └──1:N (opcional)──► reservas ◄─┼──────N:1──────────┘
                                                         │
categorias ──1:N──► productos ──1:N──► detalle_pedido ◄──1:N
```

- **pedidos N:M productos**, resuelta por `detalle_pedido`.
- `usuario_id` es opcional en pedidos y reservas: **no hace falta iniciar sesión** para pedir o reservar.

## Campos

### `roles`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | TINYINT UNSIGNED | PK |
| `nombre` | VARCHAR(30) | UNIQUE |
| `descripcion` | VARCHAR(150) | NULL |

### `usuarios`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | INT UNSIGNED | PK |
| `rol_id` | TINYINT UNSIGNED | FK → `roles` |
| `nombre` | VARCHAR(100) | |
| `email` | VARCHAR(150) | UNIQUE, usuario de inicio de sesión |
| `telefono` | VARCHAR(20) | NULL |
| `password_hash` | VARCHAR(255) | bcrypt (`password_hash` de PHP) |
| `activo` | TINYINT(1) | |
| `created_at`, `updated_at` | DATETIME | |

### `categorias`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | INT UNSIGNED | PK |
| `nombre` | VARCHAR(80) | UNIQUE |
| `descripcion` | VARCHAR(255) | NULL |
| `orden` | SMALLINT UNSIGNED | Orden en la carta |
| `hora_inicio`, `hora_fin` | TIME | Franja en la que sus productos se pueden pedir. CHECK inicio < fin |
| `activo` | TINYINT(1) | |
| `created_at`, `updated_at` | DATETIME | |

### `productos`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | INT UNSIGNED | PK |
| `categoria_id` | INT UNSIGNED | FK → `categorias`. UNIQUE con `nombre` |
| `nombre` | VARCHAR(120) | |
| `descripcion` | TEXT | NULL |
| `precio` | INT UNSIGNED | COP. CHECK > 0 |
| `imagen` | VARCHAR(255) | NULL, ruta del archivo (la imagen no va en la BD) |
| `hora_inicio`, `hora_fin` | TIME | NULL = hereda la franja de la categoría |
| `disponible_domicilio` | TINYINT(1) | 0 = se muestra pero no se puede pedir (cervezas) |
| `activo` | TINYINT(1) | 0 = oculto en la carta |
| `created_at`, `updated_at` | DATETIME | |

### `sedes`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | TINYINT UNSIGNED | PK |
| `nombre` | VARCHAR(80) | UNIQUE |
| `direccion` | VARCHAR(200) | |
| `telefono` | VARCHAR(20) | |
| `whatsapp` | VARCHAR(15) | Formato `57XXXXXXXXXX` para enlaces `wa.me` |
| `horario` | VARCHAR(255) | Texto libre |
| `url_mapa` | VARCHAR(500) | NULL |
| `activo` | TINYINT(1) | |
| `created_at`, `updated_at` | DATETIME | |

### `pedidos`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | INT UNSIGNED | PK. Código visible: `DST-000123` |
| `usuario_id` | INT UNSIGNED | FK → `usuarios`, NULL (pedido de invitado) |
| `sede_id` | TINYINT UNSIGNED | FK → `sedes` |
| `nombre_cliente`, `telefono_cliente` | VARCHAR | Copia de los datos al momento del pedido |
| `direccion_entrega` | VARCHAR(255) | |
| `barrio` | VARCHAR(100) | NULL |
| `metodo_pago` | ENUM('efectivo','transferencia') | |
| `observaciones` | VARCHAR(500) | NULL |
| `subtotal` | INT UNSIGNED | Calculado en el servidor |
| `costo_domicilio` | INT UNSIGNED | **NULL hasta que el encargado lo define** |
| `total` | INT UNSIGNED | Columna generada: `subtotal + costo_domicilio` (NULL mientras no haya domicilio) |
| `estado` | ENUM | `pendiente_confirmacion`, `confirmado`, `en_preparacion`, `en_camino`, `entregado`, `cancelado` |
| `created_at`, `updated_at` | DATETIME | |

### `detalle_pedido`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | INT UNSIGNED | PK |
| `pedido_id` | INT UNSIGNED | FK → `pedidos`. UNIQUE con `producto_id` |
| `producto_id` | INT UNSIGNED | FK → `productos` |
| `nombre_producto` | VARCHAR(120) | **Copia** del nombre al momento del pedido |
| `precio_unitario` | INT UNSIGNED | **Copia** del precio al momento del pedido |
| `cantidad` | SMALLINT UNSIGNED | CHECK ≥ 1 |
| `subtotal_linea` | INT UNSIGNED | Columna generada: `precio_unitario × cantidad` |

**Precio histórico:** si el administrador cambia el precio de un producto, los pedidos anteriores conservan el precio con el que se hicieron, porque `detalle_pedido` guarda su propia copia.

### `reservas`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | INT UNSIGNED | PK. Código visible: `RES-000123` |
| `usuario_id` | INT UNSIGNED | FK → `usuarios`, NULL |
| `sede_id` | TINYINT UNSIGNED | FK → `sedes` |
| `nombre_cliente`, `telefono_cliente` | VARCHAR | |
| `email_cliente` | VARCHAR(150) | NULL |
| `fecha` | DATE | Mínimo un día de anticipación (validado en PHP) |
| `hora` | TIME | |
| `num_personas` | TINYINT UNSIGNED | CHECK entre 1 y 40 |
| `observaciones` | VARCHAR(500) | NULL |
| `estado` | ENUM | `pendiente`, `confirmada`, `rechazada`, `cancelada`, `atendida` |
| `nota_admin` | VARCHAR(255) | NULL, por ejemplo el motivo de un rechazo |
| `created_at`, `updated_at` | DATETIME | |

## Integridad referencial

| Clave foránea | ON DELETE | Razón |
|---|---|---|
| `usuarios.rol_id → roles` | RESTRICT | No se borra un rol con usuarios |
| `productos.categoria_id → categorias` | RESTRICT | La categoría se desactiva; no quedan productos huérfanos |
| `detalle_pedido.producto_id → productos` | RESTRICT | Un producto que ya se pidió solo se puede desactivar |
| `detalle_pedido.pedido_id → pedidos` | CASCADE | El detalle no tiene sentido sin su pedido |
| `pedidos.sede_id → sedes` | RESTRICT | Una sede con historial se desactiva |
| `reservas.sede_id → sedes` | RESTRICT | Igual |
| `pedidos.usuario_id → usuarios` | SET NULL | Si se elimina una cuenta, el pedido se conserva con la copia de los datos del cliente |
| `reservas.usuario_id → usuarios` | SET NULL | Igual |

## Reglas validadas en PHP

MariaDB no permite usar la fecha actual dentro de un `CHECK`, así que estas reglas se validan en el servidor con la zona horaria `America/Bogota`:

- El local recibe pedidos de 7:00 a. m. a 10:00 p. m.
- Cada producto solo se puede pedir dentro de su franja (Desayunos 7:00–12:00, Panadería y cafetería 7:00–22:00, Comidas rápidas 16:00–22:00).
- Las reservas se piden con al menos un día de anticipación, hasta 60 días adelante, entre 7:00 a. m. y 9:00 p. m.
- Los pedidos de pastelería para eventos requieren una semana de anticipación (solo informativo, por WhatsApp).
