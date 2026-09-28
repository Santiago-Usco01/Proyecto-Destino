-- =============================================================================
--  DESTINO CAFÉ PIZZA PAN — Base de datos
--  Motor: MariaDB 10.4 (XAMPP) · InnoDB · utf8mb4
--
--  Tablas (8): roles, usuarios, categorias, productos, sedes,
--              pedidos, detalle_pedido, reservas
--
--  Criterios:
--   - Precios en pesos colombianos enteros (INT UNSIGNED, sin decimales).
--   - Teléfonos como texto (VARCHAR).
--   - Contraseñas solo como hash (password_hash de PHP).
--   - Los datos históricos no se borran: productos, categorías, sedes y
--     usuarios se desactivan; pedidos y reservas se cancelan.
--   - Reglas que dependen de la fecha/hora actual (reserva con un día de
--     antelación, franjas horarias, local abierto) se validan en PHP con la
--     zona horaria America/Bogota.
--
--  ATENCIÓN: este script BORRA la base de datos "destino" si ya existe y la
--  vuelve a crear desde cero. No lo ejecutes sobre una base con datos reales.
-- =============================================================================

DROP DATABASE IF EXISTS destino;
CREATE DATABASE destino
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE destino;

-- -----------------------------------------------------------------------------
-- roles
-- -----------------------------------------------------------------------------
CREATE TABLE roles (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(30)      NOT NULL,
  descripcion VARCHAR(150)     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- usuarios
-- -----------------------------------------------------------------------------
CREATE TABLE usuarios (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  rol_id        TINYINT UNSIGNED NOT NULL,
  nombre        VARCHAR(100)     NOT NULL,
  email         VARCHAR(150)     NOT NULL,
  telefono      VARCHAR(20)      NULL,
  password_hash VARCHAR(255)     NOT NULL,
  activo        TINYINT(1)       NOT NULL DEFAULT 1,
  created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email),
  CONSTRAINT fk_usuarios_rol
    FOREIGN KEY (rol_id) REFERENCES roles (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- categorias
--   hora_inicio / hora_fin: franja en la que sus productos se pueden pedir.
-- -----------------------------------------------------------------------------
CREATE TABLE categorias (
  id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(80)       NOT NULL,
  descripcion VARCHAR(255)      NULL,
  orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  hora_inicio TIME              NOT NULL,
  hora_fin    TIME              NOT NULL,
  activo      TINYINT(1)        NOT NULL DEFAULT 1,
  created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME          NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre),
  CONSTRAINT chk_categorias_franja CHECK (hora_inicio < hora_fin)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- productos
--   hora_inicio / hora_fin: NULL = hereda la franja de su categoría.
--   disponible_domicilio: 0 = se muestra en la carta pero no se puede pedir.
--   activo: 0 = oculto en la carta.
-- -----------------------------------------------------------------------------
CREATE TABLE productos (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id         INT UNSIGNED NOT NULL,
  nombre               VARCHAR(120) NOT NULL,
  descripcion          TEXT         NULL,
  precio               INT UNSIGNED NOT NULL,
  imagen               VARCHAR(255) NULL,
  hora_inicio          TIME         NULL,
  hora_fin             TIME         NULL,
  disponible_domicilio TINYINT(1)   NOT NULL DEFAULT 1,
  activo               TINYINT(1)   NOT NULL DEFAULT 1,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_productos_categoria_nombre (categoria_id, nombre),
  CONSTRAINT fk_productos_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_productos_precio CHECK (precio > 0),
  CONSTRAINT chk_productos_franja CHECK (
    (hora_inicio IS NULL AND hora_fin IS NULL)
    OR (hora_inicio IS NOT NULL AND hora_fin IS NOT NULL AND hora_inicio < hora_fin)
  )
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- sedes
--   whatsapp: formato internacional sin "+" para enlaces wa.me (57XXXXXXXXXX).
-- -----------------------------------------------------------------------------
CREATE TABLE sedes (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre     VARCHAR(80)      NOT NULL,
  direccion  VARCHAR(200)     NOT NULL,
  telefono   VARCHAR(20)      NOT NULL,
  whatsapp   VARCHAR(15)      NOT NULL,
  horario    VARCHAR(255)     NOT NULL,
  url_mapa   VARCHAR(500)     NULL,
  activo     TINYINT(1)       NOT NULL DEFAULT 1,
  created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sedes_nombre (nombre),
  CONSTRAINT chk_sedes_whatsapp CHECK (whatsapp REGEXP '^[0-9]{10,15}$')
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- pedidos
--   Es una SOLICITUD: nace en 'pendiente_confirmacion'. La confirmación
--   ocurre por WhatsApp y el administrador la refleja en el panel.
--   usuario_id NULL = pedido de invitado. Los datos del cliente se copian
--   aquí para conservar el historial aunque el usuario cambie o se elimine.
--   costo_domicilio NULL = aún no definido por el encargado.
--   total es calculado (NULL mientras no haya costo de domicilio).
-- -----------------------------------------------------------------------------
CREATE TABLE pedidos (
  id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id        INT UNSIGNED     NULL,
  sede_id           TINYINT UNSIGNED NOT NULL,
  nombre_cliente    VARCHAR(100)     NOT NULL,
  telefono_cliente  VARCHAR(20)      NOT NULL,
  direccion_entrega VARCHAR(255)     NOT NULL,
  barrio            VARCHAR(100)     NULL,
  metodo_pago       ENUM('efectivo','transferencia') NOT NULL,
  observaciones     VARCHAR(500)     NULL,
  subtotal          INT UNSIGNED     NOT NULL,
  costo_domicilio   INT UNSIGNED     NULL,
  total             INT UNSIGNED AS (subtotal + costo_domicilio) VIRTUAL,
  estado            ENUM('pendiente_confirmacion','confirmado','en_preparacion',
                         'en_camino','entregado','cancelado')
                    NOT NULL DEFAULT 'pendiente_confirmacion',
  created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pedidos_estado (estado),
  KEY idx_pedidos_sede_fecha (sede_id, created_at),
  CONSTRAINT fk_pedidos_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pedidos_sede
    FOREIGN KEY (sede_id) REFERENCES sedes (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- detalle_pedido  (resuelve la relación N:M pedidos <-> productos)
--   nombre_producto y precio_unitario son COPIAS del momento del pedido:
--   si el producto cambia de precio después, el pedido no se altera.
-- -----------------------------------------------------------------------------
CREATE TABLE detalle_pedido (
  id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  pedido_id       INT UNSIGNED      NOT NULL,
  producto_id     INT UNSIGNED      NOT NULL,
  nombre_producto VARCHAR(120)      NOT NULL,
  precio_unitario INT UNSIGNED      NOT NULL,
  cantidad        SMALLINT UNSIGNED NOT NULL,
  subtotal_linea  INT UNSIGNED AS (precio_unitario * cantidad) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_detalle_pedido_producto (pedido_id, producto_id),
  CONSTRAINT fk_detalle_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detalle_producto
    FOREIGN KEY (producto_id) REFERENCES productos (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_detalle_cantidad CHECK (cantidad >= 1)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- reservas
--   Es una SOLICITUD: nace en 'pendiente'.
--   Máximo 40 personas. La antelación mínima de un día se valida en PHP.
-- -----------------------------------------------------------------------------
CREATE TABLE reservas (
  id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id       INT UNSIGNED     NULL,
  sede_id          TINYINT UNSIGNED NOT NULL,
  nombre_cliente   VARCHAR(100)     NOT NULL,
  telefono_cliente VARCHAR(20)      NOT NULL,
  email_cliente    VARCHAR(150)     NULL,
  fecha            DATE             NOT NULL,
  hora             TIME             NOT NULL,
  num_personas     TINYINT UNSIGNED NOT NULL,
  observaciones    VARCHAR(500)     NULL,
  estado           ENUM('pendiente','confirmada','rechazada','cancelada','atendida')
                   NOT NULL DEFAULT 'pendiente',
  nota_admin       VARCHAR(255)     NULL,
  created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reservas_sede_fecha (sede_id, fecha),
  KEY idx_reservas_estado (estado),
  CONSTRAINT fk_reservas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reservas_sede
    FOREIGN KEY (sede_id) REFERENCES sedes (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_reservas_personas CHECK (num_personas BETWEEN 1 AND 40)
) ENGINE=InnoDB;


-- =============================================================================
--  DATOS INICIALES
-- =============================================================================

INSERT INTO roles (id, nombre, descripcion) VALUES
  (1, 'cliente',       'Consulta el menú, solicita pedidos y reservas'),
  (2, 'administrador', 'Gestiona productos, categorías, pedidos, reservas y sedes');

-- El administrador NO se crea aquí (este archivo es público en el repositorio).
-- Después de importar este script, créalo desde la terminal:
--   C:\xampp\php\php.exe database\crear-admin.php correo@ejemplo.com "Nombre"

INSERT INTO sedes (id, nombre, direccion, telefono, whatsapp, horario, url_mapa) VALUES
  (1, 'Sede Oriente', 'Cl. 19 #47-10, Neiva, Huila', '3123760524', '573123760524',
   'Lunes a domingo, 7:00 a. m. - 10:00 p. m.',
   'https://maps.app.goo.gl/5kcSh82wBehy8y2y5'),
  (2, 'Sede Sur', 'Cl. 21 Sur #29-28, Neiva, Huila', '3150016855', '573150016855',
   'Lunes a domingo, 7:00 a. m. - 10:00 p. m.',
   'https://maps.app.goo.gl/13kBTJmoYXnZ3s8LA');

INSERT INTO categorias (id, nombre, descripcion, orden, hora_inicio, hora_fin) VALUES
  ( 1, 'Desayunos',            'Servicio de desayunos hasta el mediodía', 1, '07:00:00', '12:00:00'),
  ( 2, 'Panadería',            NULL,  2, '07:00:00', '22:00:00'),
  ( 3, 'Tortas y postres',     NULL,  3, '07:00:00', '22:00:00'),
  ( 4, 'Bebidas calientes',    NULL,  4, '07:00:00', '22:00:00'),
  ( 5, 'Bebidas frías',        NULL,  5, '07:00:00', '22:00:00'),
  ( 6, 'Sodas frutales',       NULL,  6, '07:00:00', '22:00:00'),
  ( 7, 'Pizzas',               NULL,  7, '16:00:00', '22:00:00'),
  ( 8, 'Sándwiches',           NULL,  8, '16:00:00', '22:00:00'),
  ( 9, 'Hamburguesas',         NULL,  9, '16:00:00', '22:00:00'),
  (10, 'Perros y salchipapas', NULL, 10, '16:00:00', '22:00:00'),
  (11, 'Panzerottis',          NULL, 11, '16:00:00', '22:00:00'),
  (12, 'Cervezas',             'Solo para consumo en el local', 12, '16:00:00', '22:00:00');

-- ---------------------------------------------------------------- Desayunos (17)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (1, 'De la mamá señora', 'Sudado de carne con papa, huevo frito y arroz.', 18000),
  (1, 'Calentado criollo', 'Carne y papa en salsa criolla, huevo en tortilla, pico de gallo, maduritos y chorizo.', 17000),
  (1, 'Caldo campesino', 'Tradicional caldo de costilla con porción de costilla y papa, acompañado de arepas y huevo.', 17000),
  (1, 'Bowl de frutas', 'Frutas de temporada.', 11000),
  (1, 'Desayuno mi Huila', 'Tamal huilense acompañado de un espumoso migado: chocolate caliente, quesillo yaguareño, mini waffle de almojábana, pan y galletas Ducales.', 24000),
  (1, 'Huevos rancheros', 'Waffle de buñuelo redondo con queso crema, huevos revueltos con salchicha, queso y crocante de tocineta.', 16000),
  (1, 'Calentado paisa', 'Bandeja paisa en calentado con arroz, fríjol, chicharrón, huevo frito y maduritos, acompañada de aguacate, chorizo y carne.', 17000),
  (1, 'Parfait', 'Yogur natural, avena en hojuelas y frutos secos con mermelada de la casa, acompañado de fruta fresca.', 14000),
  (1, 'Huevos rotos', 'Base de papa en casco salteada en mantequilla, tiras de tocineta y queso fundido, acompañada de 2 huevos y salsa napolitana.', 16000),
  (1, 'Huevos Yankee', '2 huevos fritos, pancakes de la casa con tocineta y fruta.', 15000),
  (1, 'Huevos primavera', 'Huevos en cacerola con base de espinaca, tomate cherry, champiñones, maíz tierno y queso parmesano.', 15000),
  (1, 'Omelette de pollo con champiñones', 'Huevo en tortilla acompañado de salsa blanca, pollo y champiñones.', 14000),
  (1, 'Desayuno Destino', '1/2 waffle de la casa, fruta de temporada y huevos revueltos con tocineta.', 14000),
  (1, 'Omelette de la casa', 'Dos huevos revueltos, salchicha, maíz tierno, champiñones, tomate cherry, aguacate, queso y trozos de tocineta.', 14000),
  (1, 'Tamal', NULL, 12000),
  (1, 'Quesillo', NULL, 4000),
  (1, 'Huevo', NULL, 2500);

-- ---------------------------------------------------------------- Panadería (20)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (2, 'Croissant', NULL, 5000),
  (2, 'Pan de queso', NULL, 6000),
  (2, 'Pan campesino', NULL, 6000),
  (2, 'Pan mariquiteño', NULL, 5000),
  (2, 'Pan de coco', NULL, 700),
  (2, 'Pan rollo', NULL, 700),
  (2, 'Palitos de queso', NULL, 5000),
  (2, 'Galleta rellena de Nutella', NULL, 8000),
  (2, 'Galleta rellena de pistacho', NULL, 8000),
  (2, 'Galleta rellena de leche Klim', NULL, 8000),
  (2, 'Galleta rellena de chocolate', NULL, 5000),
  (2, 'Galleta rellena de avena', NULL, 5000),
  (2, 'Galleta rellena red velvet', NULL, 6000),
  (2, 'Mojicón', NULL, 5000),
  (2, 'Pan galleta', NULL, 6000),
  (2, 'Buñuelo', NULL, 2500),
  (2, 'Almojábana', NULL, 2500),
  (2, 'Caña', NULL, 2000),
  (2, 'Pan chino', NULL, 2000),
  (2, 'Brazo de reina', NULL, 2000);

-- ---------------------------------------------------------------- Tortas y postres (14)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (3, 'Torta de chocolate', NULL, 6000),
  (3, 'Torta de chocolate con helado', NULL, 12000),
  (3, 'Torta de zanahoria', NULL, 6000),
  (3, 'Torta red velvet', NULL, 8000),
  (3, 'Galleta de vainilla', NULL, 5000),
  (3, 'Galleta de chocolate', NULL, 5000),
  (3, 'Galleta de avena', NULL, 5000),
  (3, 'Galleta red velvet', NULL, 6000),
  (3, 'Postre de almojábana', NULL, 10000),
  (3, 'Postre de Milo', NULL, 10000),
  (3, 'Postre de maracuyá', NULL, 10000),
  (3, 'Postre de limón', NULL, 10000),
  (3, 'Postre tres leches', NULL, 10000),
  (3, 'Postre de Oreo', NULL, 10000);

-- ---------------------------------------------------------------- Bebidas calientes (15)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (4, 'Migaito de chocolate', 'Acompañado de bizcocho de achira, quesillo yaguareño, almojábana y waffle de pandeyuca.', 16000),
  (4, 'Espresso', NULL, 4000),
  (4, 'Americano', NULL, 4500),
  (4, 'Latte', NULL, 5500),
  (4, 'Capuccino', NULL, 6000),
  (4, 'Capuccino en leche de almendras', NULL, 8000),
  (4, 'Latte en leche de almendras', NULL, 8000),
  (4, 'Mocaccino', NULL, 8000),
  (4, 'Café del campo', NULL, 4500),
  (4, 'Aromática', NULL, 4000),
  (4, 'Milo caliente', NULL, 8000),
  (4, 'Chocolate', NULL, 6000),
  (4, 'Chocolate en leche', NULL, 6500),
  (4, 'Affogato', NULL, 9000),
  (4, 'Método de café', NULL, 12000);

-- ---------------------------------------------------------------- Bebidas frías (25)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (5, 'Capuccino frío', NULL, 11000),
  (5, 'Milo frío', NULL, 12000),
  (5, 'Frappé de café', NULL, 10000),
  (5, 'Frappé de Oreo', NULL, 10000),
  (5, 'Malteada de vainilla', NULL, 15000),
  (5, 'Malteada de Oreo', NULL, 15000),
  (5, 'Malteada de café', NULL, 15000),
  (5, 'Malteada de arequipe', NULL, 15000),
  (5, 'Jugo de naranja y zanahoria', NULL, 12000),
  (5, 'Jugo de zanahoria', NULL, 10000),
  (5, 'Zumo de naranja', NULL, 10000),
  (5, 'Jugo de maracuyá en agua', NULL, 8000),
  (5, 'Jugo de mango en agua', NULL, 8000),
  (5, 'Jugo de guanábana en agua', NULL, 8000),
  (5, 'Jugo de cholupa en agua', NULL, 8000),
  (5, 'Jugo de maracuyá en leche', NULL, 9000),
  (5, 'Jugo de mango en leche', NULL, 9000),
  (5, 'Jugo de guanábana en leche', NULL, 9000),
  (5, 'Jugo de cholupa en leche', NULL, 9000),
  (5, 'Jugo verde', NULL, 10000),
  (5, 'Limonada natural', NULL, 7000),
  (5, 'Limonada cerezada', NULL, 10000),
  (5, 'Limonada de hierbabuena', NULL, 8000),
  (5, 'Limonada de coco', NULL, 12000),
  (5, 'Zumo de limón', 'Vaso con zumo de limón natural.', 1000);

-- ---------------------------------------------------------------- Sodas frutales (6)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (6, 'Soda de frutos rojos', NULL, 10000),
  (6, 'Soda de maracuyá', NULL, 10000),
  (6, 'Soda frutal', NULL, 10000),
  (6, 'Soda de cholupa', NULL, 10000),
  (6, 'Soda de lychee', NULL, 12000),
  (6, 'Soda de kiwi', NULL, 12000);

-- ---------------------------------------------------------------- Pizzas (13)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (7, 'Pizza cuatro carnes', 'Salsa pomodoro, chorizo español, salami, pepperoni y cábano.', 30000),
  (7, 'Pizza mexicana', 'Carne molida, cebolla morada, jalapeño, pimentón, queso mozzarella y nachos.', 30000),
  (7, 'Pizza hawaiana', 'Piña en reducción de panela, jamón de cerdo y queso mozzarella.', 30000),
  (7, 'Pizza pulled pork', 'Bondiola de cerdo, salsa pomodoro, queso mozzarella, cebolla grillé y salsa BBQ.', 30000),
  (7, 'Pizza pollo y champiñones', 'Champiñones, pollo a la parrilla, salsa pomodoro y queso mozzarella.', 30000),
  (7, 'Pizza pollo parrillado', 'Trozos de pollo a la parrilla, tocineta y salsa BBQ.', 30000),
  (7, 'Pizza Destino', 'Salsa pomodoro y jamón serrano en reducción de balsámico, acompañada de queso burrata.', 30000),
  (7, 'Pizza española', 'Chorizo español, chorizo de cerdo, cebolla morada y pimentón morrón.', 28000),
  (7, 'Pizza pepperoni', 'Salsa pomodoro, queso mozzarella, pepperoni y albahaca.', 30000),
  (7, 'Pizza cuatro quesos', 'Queso mozzarella, queso azul, queso sabana y queso gruyère.', 30000),
  (7, 'Pizza margarita', 'Tomate, queso mozzarella y albahaca.', 27000),
  (7, 'Pizza de Nutella', 'Nutella, fresa y banano.', 27000),
  (7, 'Pizza mitad y mitad', 'Combina tus dos sabores favoritos. Indica los dos sabores en las observaciones del pedido.', 32000);

-- ---------------------------------------------------------------- Sándwiches (4)
INSERT INTO productos (categoria_id, nombre, descripcion, precio, hora_inicio, hora_fin) VALUES
  (8, 'Sanduche de lomito', 'Queso cheddar, cebolla caramelizada, lomito fino y salsa de la casa.', 16000, NULL, NULL),
  (8, 'Sanduche Verona', 'Pan focaccia, pepperoni, queso mozzarella, aguacate, salsa napolitana, jamón serrano, tomate cherry y reducción de balsámico.', 18000, '07:00:00', '22:00:00'),
  (8, 'Sanduche de jamón y queso', 'Jamón de cerdo premium, queso, vegetales y salsa de la casa.', 15000, NULL, NULL),
  (8, 'Sanduche de pollo apanado', 'Pollo, huevo frito, queso y salsa de la casa.', 15000, NULL, NULL);

-- ---------------------------------------------------------------- Hamburguesas (5)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (9, 'Hamburguesa de la casa', 'Pan, queso apanado, crema de aguacate, panceta y mermelada de tocineta.', 23000),
  (9, 'Hamburguesa americana', 'Pan, queso cheddar, salsa de tocineta, tocineta, vegetales, cebolla caramelizada y carne.', 23000),
  (9, 'Hamburguesa criolla', 'Patacón, salsa criolla, huevo, chorizo, cebolla caramelizada y carne.', 23000),
  (9, 'Hamburguesa de pollo', 'Pan, rúgula, mayonesa de pepinillos, queso mozzarella y pechuga apanada.', 25000),
  (9, 'Hamburguesa de pulled pork', 'Pan, vegetales, queso mozzarella, pulled pork en salsa BBQ y carne.', 23000);

-- ---------------------------------------------------------------- Perros y salchipapas (2)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (10, 'Salchipapa Yanki', 'Cuajada, salchicha americana, maduritos, pico de gallo, pollo y papa criolla.', 24000),
  (10, 'Perro clásico', 'Cebolla, queso, salchicha americana y papa ripio.', 12000);

-- ---------------------------------------------------------------- Panzerottis (3)
INSERT INTO productos (categoria_id, nombre, descripcion, precio) VALUES
  (11, 'Panzerotti de pollo', NULL, 24000),
  (11, 'Panzerotti de carne', NULL, 24000),
  (11, 'Panzerotti hawaiano', NULL, 24000);

-- ---------------------------------------------------------------- Cervezas (2) · no disponibles a domicilio
INSERT INTO productos (categoria_id, nombre, descripcion, precio, disponible_domicilio) VALUES
  (12, 'Club Colombia', NULL, 7000, 0),
  (12, 'Stella Artois', NULL, 7000, 0);
