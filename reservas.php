<?php
/**
 * Solicitud de reserva de mesa.
 * La reserva se guarda en estado 'pendiente': es una SOLICITUD que la sede confirma.
 * Tras guardarla se ofrece avisar a la sede por WhatsApp (opcional).
 */
require __DIR__ . '/includes/init.php';

$sedes = sedes_activas();
$sedes_por_id = array_column($sedes, null, 'id');
$usuario = usuario_actual();

$fecha_minima = fecha_mas_dias(RESERVA_DIAS_ANTELACION);
$fecha_maxima = fecha_mas_dias(RESERVA_DIAS_MAXIMO);

// Horas que se pueden reservar: 7:00, 7:30, … 9:00 p. m.
$horas = [];
$hora = new DateTime(RESERVA_HORA_PRIMERA);
$ultima = new DateTime(RESERVA_HORA_ULTIMA);
while ($hora <= $ultima) {
    $horas[] = $hora->format('H:i:s');
    $hora->modify('+' . RESERVA_INTERVALO_MIN . ' minutes');
}

$valores = [
    'nombre'        => $usuario['nombre'] ?? '',
    'telefono'      => '',
    'email'         => $usuario['email'] ?? '',
    'sede_id'       => '',
    'fecha'         => '',
    'hora'          => '',
    'num_personas'  => '2',
    'observaciones' => '',
];
if ($usuario) {
    $consulta = db()->prepare('SELECT telefono FROM usuarios WHERE id = ?');
    $consulta->execute([$usuario['id']]);
    $valores['telefono'] = $consulta->fetchColumn() ?: '';
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $valores = array_merge($valores, array_intersect_key($_POST, $valores));

    $nombre        = limpiar_texto($_POST['nombre'] ?? null, 100);
    $telefono      = normalizar_telefono($_POST['telefono'] ?? null);
    $email_crudo   = limpiar_texto($_POST['email'] ?? null, 150);
    $email         = $email_crudo === null ? null : validar_email($email_crudo);
    $sede_id       = filter_var($_POST['sede_id'] ?? null, FILTER_VALIDATE_INT);
    $fecha         = $_POST['fecha'] ?? '';
    $hora          = $_POST['hora'] ?? '';
    $num_personas  = filter_var($_POST['num_personas'] ?? null, FILTER_VALIDATE_INT,
                                ['options' => ['min_range' => 1, 'max_range' => RESERVA_MAX_PERSONAS]]);
    $observaciones = limpiar_texto_largo($_POST['observaciones'] ?? null, 500);

    if ($nombre === null || mb_strlen($nombre) < 3) {
        $errores['nombre'] = 'Escribe tu nombre.';
    }
    if ($telefono === null) {
        $errores['telefono'] = 'Escribe un número de 10 dígitos, por ejemplo 312 345 6789.';
    }
    if ($email_crudo !== null && $email === null) {
        $errores['email'] = 'El correo no es válido. Puedes dejarlo vacío.';
    }
    if (!$sede_id || !isset($sedes_por_id[$sede_id])) {
        $errores['sede_id'] = 'Elige la sede.';
    }
    $fecha_valida = is_string($fecha) && DateTime::createFromFormat('!Y-m-d', $fecha)?->format('Y-m-d') === $fecha;
    if (!$fecha_valida) {
        $errores['fecha'] = 'Elige la fecha de la reserva.';
    } elseif ($fecha < $fecha_minima) {
        $errores['fecha'] = 'Las reservas se solicitan con al menos un día de anticipación.';
    } elseif ($fecha > $fecha_maxima) {
        $errores['fecha'] = 'Puedes reservar hasta ' . RESERVA_DIAS_MAXIMO . ' días adelante.';
    }
    if (!is_string($hora) || !in_array($hora, $horas, true)) {
        $errores['hora'] = 'Elige una hora de la lista.';
    }
    if ($num_personas === false) {
        $errores['num_personas'] = 'Indica un número de personas entre 1 y ' . RESERVA_MAX_PERSONAS . '.';
    }

    if (!$errores) {
        db()->prepare('INSERT INTO reservas
                           (usuario_id, sede_id, nombre_cliente, telefono_cliente, email_cliente,
                            fecha, hora, num_personas, observaciones)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$usuario['id'] ?? null, $sede_id, $nombre, $telefono, $email,
                       $fecha, $hora, $num_personas, $observaciones]);
        $reserva_id = (int) db()->lastInsertId();
        $sede = $sedes_por_id[$sede_id];

        $lineas = [
            '*Solicitud de reserva ' . codigo_reserva($reserva_id) . '*',
            $sede['nombre'],
            '',
            'Nombre: ' . $nombre,
            'Teléfono: ' . telefono_legible($telefono),
            'Fecha: ' . fecha_legible($fecha),
            'Hora: ' . hora_legible($hora),
            'Personas: ' . $num_personas,
        ];
        if ($observaciones) {
            array_push($lineas, '', 'Observaciones: ' . $observaciones);
        }
        array_push($lineas, '', 'Quedo pendiente de la confirmación.');

        $_SESSION['ultima_reserva'] = [
            'codigo'   => codigo_reserva($reserva_id),
            'sede'     => $sede['nombre'],
            'fecha'    => fecha_legible($fecha),
            'hora'     => hora_legible($hora),
            'personas' => $num_personas,
            'enlace'   => enlace_whatsapp($sede['whatsapp'], implode("\n", $lineas)),
        ];
        redirigir('reservas.php?enviada=1');
    }
}

// Confirmación después de guardar (POST → redirección → GET, así recargar no duplica la reserva).
$reserva_enviada = isset($_GET['enviada']) ? ($_SESSION['ultima_reserva'] ?? null) : null;

$titulo_pagina = 'Reservas';
$pagina_actual = 'reservas';
require __DIR__ . '/includes/header.php';
?>

<section class="cabecera-pagina">
    <div class="contenedor">
        <span class="antetitulo">Reserva tu mesa</span>
        <h1>Reservas</h1>
        <p class="texto-suave">
            Para grupos de 1 a <?= RESERVA_MAX_PERSONAS ?> personas, con al menos un día de anticipación.
            Tu solicitud queda pendiente hasta que la sede la confirme.
        </p>
    </div>
</section>

<div class="contenedor pagina-formulario">
    <?php if ($reserva_enviada): ?>

        <div class="generado__tarjeta tarjeta">
            <p class="generado__codigo">Solicitud <strong><?= e($reserva_enviada['codigo']) ?></strong> · <?= e($reserva_enviada['sede']) ?></p>
            <div class="aviso aviso--exito">
                Recibimos tu solicitud de reserva. Todavía <strong>no está confirmada</strong>:
                la sede revisará la disponibilidad y te contactará.
            </div>
            <dl class="resumen__filas">
                <div><dt>Fecha</dt><dd><?= e($reserva_enviada['fecha']) ?></dd></div>
                <div><dt>Hora</dt><dd><?= e($reserva_enviada['hora']) ?></dd></div>
                <div><dt>Personas</dt><dd><?= (int) $reserva_enviada['personas'] ?></dd></div>
            </dl>
            <a href="<?= e($reserva_enviada['enlace']) ?>" class="boton boton--whatsapp boton--bloque" target="_blank" rel="noopener">
                Avisar a la sede por WhatsApp
            </a>
            <p class="generado__ayuda texto-suave">Opcional: agiliza la confirmación enviando los datos por WhatsApp.</p>
            <a href="<?= url('index.php') ?>" class="resumen__volver">Volver al inicio</a>
        </div>

    <?php else: ?>

        <?php if ($errores): ?>
            <div class="aviso aviso--error formulario--angosto" role="alert">Revisa los campos marcados.</div>
        <?php endif; ?>

        <form method="post" class="formulario tarjeta formulario--angosto" novalidate>
            <?= csrf_campo() ?>

            <div class="campo--doble">
                <div class="campo">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" required autocomplete="name"
                           value="<?= e($valores['nombre']) ?>"<?= attr_error($errores, 'nombre') ?>>
                    <?= mensaje_error($errores, 'nombre') ?>
                </div>
                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" maxlength="20" required autocomplete="tel" inputmode="tel"
                           placeholder="312 345 6789" value="<?= e($valores['telefono']) ?>"<?= attr_error($errores, 'telefono') ?>>
                    <?= mensaje_error($errores, 'telefono') ?>
                </div>
            </div>

            <div class="campo">
                <label for="email">Correo <span class="opcional">(opcional)</span></label>
                <input type="email" id="email" name="email" maxlength="150" autocomplete="email"
                       value="<?= e($valores['email']) ?>"<?= attr_error($errores, 'email') ?>>
                <?= mensaje_error($errores, 'email') ?>
            </div>

            <div class="campo">
                <span class="campo__etiqueta" id="etiqueta-sede">Sede</span>
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
                <?= mensaje_error($errores, 'sede_id') ?>
            </div>

            <div class="campo--doble">
                <div class="campo">
                    <label for="fecha">Fecha</label>
                    <input type="date" id="fecha" name="fecha" required
                           min="<?= e($fecha_minima) ?>" max="<?= e($fecha_maxima) ?>"
                           value="<?= e($valores['fecha']) ?>"<?= attr_error($errores, 'fecha') ?>>
                    <?= mensaje_error($errores, 'fecha') ?>
                </div>
                <div class="campo">
                    <label for="hora">Hora</label>
                    <select id="hora" name="hora" required<?= attr_error($errores, 'hora') ?>>
                        <option value="">Elige una hora</option>
                        <?php foreach ($horas as $h): ?>
                            <option value="<?= e($h) ?>" <?= $valores['hora'] === $h ? 'selected' : '' ?>><?= e(hora_legible($h)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= mensaje_error($errores, 'hora') ?>
                </div>
            </div>

            <div class="campo">
                <label for="num_personas">Número de personas</label>
                <input type="number" id="num_personas" name="num_personas" min="1" max="<?= RESERVA_MAX_PERSONAS ?>" required
                       inputmode="numeric" value="<?= e($valores['num_personas']) ?>"<?= attr_error($errores, 'num_personas') ?>>
                <?= mensaje_error($errores, 'num_personas') ?>
            </div>

            <div class="campo">
                <label for="observaciones">Observaciones <span class="opcional">(opcional)</span></label>
                <textarea id="observaciones" name="observaciones" maxlength="500"
                          placeholder="Ej.: cumpleaños, necesitamos silla para bebé…"><?= e($valores['observaciones']) ?></textarea>
            </div>

            <button type="submit" class="boton boton--primario boton--bloque">Solicitar reserva</button>
        </form>

    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
