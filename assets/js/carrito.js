/**
 * Página del carrito.
 * - Lee el carrito de localStorage ({ id: cantidad }).
 * - Pide al servidor nombre, precio y disponibilidad reales (api/carrito.php).
 * - Permite cambiar cantidades, eliminar y vaciar; recalcula el subtotal.
 * Depende de main.js (Carrito, formatoPrecio).
 */
const CANTIDAD_MAXIMA = 999; // límite técnico para evitar errores de digitación

const el = {
    cargando:  document.getElementById('carrito-cargando'),
    vacio:     document.getElementById('carrito-vacio'),
    error:     document.getElementById('carrito-error'),
    contenido: document.getElementById('carrito-contenido'),
    aviso:     document.getElementById('carrito-aviso'),
    lineas:    document.getElementById('carrito-lineas'),
    subtotal:  document.getElementById('resumen-subtotal'),
    total:     document.getElementById('resumen-total'),
    continuar: document.getElementById('continuar-pedido'),
    bloqueo:   document.getElementById('resumen-bloqueo'),
    vaciar:    document.getElementById('vaciar-carrito'),
};

let productos = {};      // id → { id, nombre, precio, disponible, motivo }
let localAbierto = true;
let estadoLocal = '';

// ---------------------------------------------------------------- Carga inicial
async function cargarCarrito() {
    const items = Carrito.leer();
    const ids = Object.keys(items);

    if (ids.length === 0) {
        mostrar('vacio');
        return;
    }

    try {
        const respuesta = await fetch(`${DESTINO.baseUrl}/api/carrito.php?ids=${ids.join(',')}`, {
            headers: { Accept: 'application/json' },
        });
        if (!respuesta.ok) throw new Error(respuesta.status);
        const datos = await respuesta.json();

        productos = Object.fromEntries(datos.productos.map((p) => [p.id, p]));
        localAbierto = datos.local_abierto;
        estadoLocal = datos.estado_local;
    } catch {
        mostrar('error');
        return;
    }

    // Quitar productos que ya no existen en la base de datos.
    const eliminados = ids.filter((id) => !productos[id]);
    if (eliminados.length) {
        eliminados.forEach((id) => Carrito.eliminar(id));
        el.aviso.textContent = eliminados.length === 1
            ? 'Quitamos un producto que ya no está disponible en el menú.'
            : `Quitamos ${eliminados.length} productos que ya no están disponibles en el menú.`;
        el.aviso.hidden = false;
    }

    dibujar();
}

// ---------------------------------------------------------------- Dibujo
function mostrar(estado) {
    el.cargando.hidden = true;
    el.vacio.hidden = estado !== 'vacio';
    el.error.hidden = estado !== 'error';
    el.contenido.hidden = estado !== 'contenido';
}

function dibujar() {
    const items = Carrito.leer();
    const ids = Object.keys(items).filter((id) => productos[id]);

    if (ids.length === 0) {
        mostrar('vacio');
        return;
    }
    mostrar('contenido');

    el.lineas.replaceChildren(...ids.map((id) => crearLinea(productos[id], items[id])));

    // Subtotal: solo productos disponibles ahora.
    const subtotal = ids.reduce((suma, id) => {
        const p = productos[id];
        return p.disponible ? suma + p.precio * items[id] : suma;
    }, 0);
    el.subtotal.textContent = formatoPrecio(subtotal);
    el.total.textContent = formatoPrecio(subtotal);

    // ¿Se puede continuar?
    const noDisponibles = ids.filter((id) => !productos[id].disponible).length;
    let motivoBloqueo = '';
    if (!localAbierto) {
        motivoBloqueo = `${estadoLocal}. Puedes dejar listo tu carrito y enviarlo cuando abramos.`;
    } else if (noDisponibles > 0) {
        motivoBloqueo = noDisponibles === 1
            ? 'Hay un producto que no se puede pedir en este momento. Quítalo para continuar.'
            : `Hay ${noDisponibles} productos que no se pueden pedir en este momento. Quítalos para continuar.`;
    }

    el.bloqueo.textContent = motivoBloqueo;
    el.bloqueo.hidden = motivoBloqueo === '';
    el.continuar.classList.toggle('deshabilitado', motivoBloqueo !== '');
    el.continuar.setAttribute('aria-disabled', String(motivoBloqueo !== ''));
}

/** Crea una fila del carrito. Todo el texto se asigna con textContent (sin HTML). */
function crearLinea(producto, cantidad) {
    const li = document.createElement('li');
    li.className = 'linea' + (producto.disponible ? '' : ' linea--no-disponible');

    const info = document.createElement('div');
    info.className = 'linea__info';
    const nombre = document.createElement('h3');
    nombre.className = 'linea__nombre';
    nombre.textContent = producto.nombre;
    const unitario = document.createElement('p');
    unitario.className = 'linea__unitario';
    unitario.textContent = `${formatoPrecio(producto.precio)} c/u`;
    info.append(nombre, unitario);
    if (!producto.disponible) {
        const motivo = document.createElement('p');
        motivo.className = 'linea__motivo';
        motivo.textContent = producto.motivo;
        info.append(motivo);
    }

    // Selector de cantidad: − [n] +
    const selector = document.createElement('div');
    selector.className = 'cantidad';
    const menos = crearBoton('−', `Quitar una unidad de ${producto.nombre}`, () => cambiar(producto.id, cantidad - 1));
    const entrada = document.createElement('input');
    entrada.type = 'number';
    entrada.min = '1';
    entrada.max = String(CANTIDAD_MAXIMA);
    entrada.inputMode = 'numeric';
    entrada.value = cantidad;
    entrada.setAttribute('aria-label', `Cantidad de ${producto.nombre}`);
    entrada.addEventListener('change', () => cambiar(producto.id, parseInt(entrada.value, 10)));
    const mas = crearBoton('+', `Agregar una unidad de ${producto.nombre}`, () => cambiar(producto.id, cantidad + 1));
    mas.disabled = cantidad >= CANTIDAD_MAXIMA;
    selector.append(menos, entrada, mas);

    const subtotal = document.createElement('p');
    subtotal.className = 'linea__subtotal';
    subtotal.textContent = producto.disponible ? formatoPrecio(producto.precio * cantidad) : '—';

    const eliminar = crearBoton('Eliminar', `Eliminar ${producto.nombre} del carrito`, () => {
        Carrito.eliminar(producto.id);
        dibujar();
    });
    eliminar.className = 'enlace-boton linea__eliminar';

    li.append(info, selector, subtotal, eliminar);
    return li;
}

function crearBoton(texto, etiqueta, alHacerClic) {
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.textContent = texto;
    boton.setAttribute('aria-label', etiqueta);
    boton.addEventListener('click', alHacerClic);
    return boton;
}

// ---------------------------------------------------------------- Acciones
function cambiar(id, cantidad) {
    if (!Number.isInteger(cantidad) || cantidad < 0) {
        dibujar(); // valor inválido: volver a mostrar la cantidad anterior
        return;
    }
    Carrito.cambiarCantidad(id, Math.min(cantidad, CANTIDAD_MAXIMA));
    dibujar();
}

el.vaciar.addEventListener('click', () => {
    Carrito.vaciar();
    dibujar();
});

el.continuar.addEventListener('click', (evento) => {
    if (el.continuar.getAttribute('aria-disabled') === 'true') {
        evento.preventDefault();
        el.bloqueo.focus?.();
    }
});

// Si el carrito cambia en otra pestaña, volver a cargar.
window.addEventListener('storage', (evento) => {
    if (evento.key === Carrito.CLAVE) cargarCarrito();
});

cargarCarrito();
