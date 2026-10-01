<?php
/**
 * Pie de página público. Cierra <main>, <body> y <html>.
 * Una página puede definir $scripts_pagina = ['assets/js/menu.js'] para cargar JS adicional.
 */
$sedes_pie = sedes_activas();
$scripts_pagina ??= [];
?>
</main>

<footer class="pie">
    <div class="contenedor pie__rejilla">
        <div>
            <img class="pie__logo" src="<?= url('assets/img/logo-claro.png') ?>" alt="<?= e(NEGOCIO_NOMBRE) ?>" width="389" height="196" loading="lazy">
            <p class="pie__texto"><?= e(NEGOCIO_HORARIO) ?></p>
            <ul class="redes" aria-label="Redes sociales">
                <li>
                    <a href="<?= e(URL_INSTAGRAM) ?>" target="_blank" rel="noopener" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 8.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4ZM17.3 5.5a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4ZM12 2c-2.7 0-3 0-4.1.1-1 0-1.8.2-2.4.5-.7.2-1.2.6-1.8 1.1-.5.6-.9 1.1-1.1 1.8-.3.6-.5 1.4-.5 2.4C2 9 2 9.3 2 12s0 3 .1 4.1c0 1 .2 1.8.5 2.4.2.7.6 1.2 1.1 1.8.6.5 1.1.9 1.8 1.1.6.3 1.4.5 2.4.5 1.1.1 1.4.1 4.1.1s3 0 4.1-.1c1 0 1.8-.2 2.4-.5.7-.2 1.2-.6 1.8-1.1.5-.6.9-1.1 1.1-1.8.3-.6.5-1.4.5-2.4.1-1.1.1-1.4.1-4.1s0-3-.1-4.1c0-1-.2-1.8-.5-2.4-.2-.7-.6-1.2-1.1-1.8-.6-.5-1.1-.9-1.8-1.1-.6-.3-1.4-.5-2.4-.5C15 2 14.7 2 12 2Zm0 1.8c2.7 0 3 0 4 .1.9 0 1.5.2 1.8.3.5.2.8.4 1.1.7.4.4.6.7.8 1.2.1.3.3.9.3 1.8.1 1 .1 1.4.1 4.1s0 3-.1 4c0 .9-.2 1.5-.3 1.8-.2.5-.4.8-.8 1.1-.3.4-.6.6-1.1.8-.3.1-.9.3-1.8.3-1 .1-1.3.1-4 .1s-3.1 0-4.1-.1c-.9 0-1.5-.2-1.8-.3-.5-.2-.8-.4-1.1-.8-.4-.3-.6-.6-.8-1.1-.1-.3-.3-.9-.3-1.8-.1-1-.1-1.3-.1-4s0-3.1.1-4.1c0-.9.2-1.5.3-1.8.2-.5.4-.8.8-1.2.3-.3.6-.5 1.1-.7.3-.1.9-.3 1.8-.3 1-.1 1.4-.1 4.1-.1Z"/></svg>
                    </a>
                </li>
                <li>
                    <a href="<?= e(URL_FACEBOOK) ?>" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/></svg>
                    </a>
                </li>
            </ul>
        </div>

        <?php foreach ($sedes_pie as $sede): ?>
            <div>
                <h2 class="pie__titulo"><?= e($sede['nombre']) ?></h2>
                <address class="pie__texto">
                    <?= e($sede['direccion']) ?><br>
                    Tel. <a href="tel:+57<?= e($sede['telefono']) ?>"><?= e(telefono_legible($sede['telefono'])) ?></a><br>
                    <a href="<?= e(enlace_whatsapp($sede['whatsapp'])) ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a>
                    <?php if ($sede['url_mapa']): ?>
                        · <a href="<?= e($sede['url_mapa']) ?>" target="_blank" rel="noopener">Cómo llegar</a>
                    <?php endif; ?>
                </address>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="contenedor pie__legal">
        <p>© <?= date('Y') ?> <?= e(NEGOCIO_NOMBRE) ?> · <?= e(NEGOCIO_CIUDAD) ?></p>
    </div>
</footer>

<script>window.DESTINO = { baseUrl: <?= json_encode(BASE_URL) ?> };</script>
<script src="<?= url('assets/js/main.js') ?>"></script>
<script src="<?= url('assets/js/avisos.js') ?>"></script>
<?php foreach ($scripts_pagina as $script): ?>
    <script src="<?= url($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
