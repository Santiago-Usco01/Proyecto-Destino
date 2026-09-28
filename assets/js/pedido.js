/**
 * Formulario de domicilio.
 * - Copia el carrito (localStorage) al campo oculto que se envía al servidor.
 * - Muestra el resumen con precios reales (api/carrito.php).
 * - Evita enviar el formulario dos veces.
 * El servidor vuelve a validar todo al recibirlo.
 * Depende de main.js (Carrito, formatoPrecio).
 */
const form = document.getElementById('formulario-pedido');
const campoCarrito = document.getElementById('campo-carrito');
const vacio = document.getElementById('pedido-vacio');
const cargando = document.getElementById('resumen-cargando');
const lista = document.getElementById('resumen-lineas');
const subtotalEl = document.getElementById('resumen-subtotal');
const botonEnviar = document.getElementById('enviar-pedido');
const bloqueo = document.getElementById('resumen-bloqueo');

function bloquear(mensaje) {
    bloqueo.textContent = mensaje;
    bloqueo.hidden = false;
    botonEnviar.disabled = true;
}

async function prepararResumen() {
    const items = Carrito.leer();
    const ids = Object.keys(items);

    if (ids.length === 0) {
        form.hidden = true;
        vacio.hidden = false;
        return;
    }
    campoCarrito.value = JSON.stringify(items);

    try {
        const respuesta = await fetch(`${DESTINO.baseUrl}/api/carrito.php?ids=${ids.join(',')}`);
        if (!respuesta.ok) throw new Error(respuesta.status);
        const datos = await respuesta.json();

        let subtotal = 0;
        const filas = datos.productos.map((p) => {
            const li = document.createElement('li');
            const nombre = document.createElement('span');
            nombre.textContent = `${items[p.id]} × ${p.nombre}`;
            const valor = document.createElement('span');
            valor.textContent = p.disponible ? formatoPrecio(p.precio * items[p.id]) : 'No disponible';
            if (p.disponible) subtotal += p.precio * items[p.id];
            li.append(nombre, valor);
            return li;
        });
        lista.replaceChildren(...filas);
        subtotalEl.textContent = formatoPrecio(subtotal);
        cargando.hidden = true;

        if (!datos.local_abierto) {
            bloquear(`${datos.estado_local}.`);
        } else if (datos.productos.length !== ids.length || datos.productos.some((p) => !p.disponible)) {
            bloquear('Hay productos que no se pueden pedir ahora. Vuelve al carrito para revisarlos.');
        }
    } catch {
        cargando.textContent = 'No pudimos cargar el resumen. Revisa tu conexión.';
    }
}

form.addEventListener('submit', (evento) => {
    // Siempre enviar la versión más reciente del carrito.
    campoCarrito.value = JSON.stringify(Carrito.leer());

    if (botonEnviar.disabled) {
        evento.preventDefault();
        return;
    }
    botonEnviar.disabled = true;
    botonEnviar.textContent = 'Generando pedido…';
});

// Al volver con el botón "atrás" del navegador, reactivar el botón.
window.addEventListener('pageshow', () => {
    if (bloqueo.hidden) {
        botonEnviar.disabled = false;
        botonEnviar.textContent = 'Generar pedido';
    }
});

prepararResumen();
