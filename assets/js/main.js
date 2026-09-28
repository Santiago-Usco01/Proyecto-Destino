/**
 * JavaScript común a todas las páginas públicas:
 *  - Menú desplegable en móvil.
 *  - Contador del carrito en el encabezado.
 *  - Utilidades del carrito (localStorage) que usan menu.js y carrito.js.
 */

// ---------------------------------------------------------------- Carrito (localStorage)
// Solo se guarda { id: cantidad }. Los precios siempre se consultan al servidor.
const Carrito = {
    CLAVE: 'destino_carrito',

    leer() {
        try {
            const datos = JSON.parse(localStorage.getItem(this.CLAVE));
            return datos && typeof datos === 'object' && !Array.isArray(datos) ? datos : {};
        } catch {
            return {};
        }
    },

    guardar(items) {
        try {
            localStorage.setItem(this.CLAVE, JSON.stringify(items));
        } catch {
            // Almacenamiento no disponible (modo privado estricto): el carrito no persiste.
        }
        actualizarContadorCarrito();
    },

    agregar(id, cantidad = 1) {
        const items = this.leer();
        items[id] = (items[id] || 0) + cantidad;
        this.guardar(items);
    },

    cambiarCantidad(id, cantidad) {
        const items = this.leer();
        if (cantidad > 0) {
            items[id] = cantidad;
        } else {
            delete items[id];
        }
        this.guardar(items);
    },

    eliminar(id) {
        this.cambiarCantidad(id, 0);
    },

    vaciar() {
        this.guardar({});
    },

    totalUnidades() {
        return Object.values(this.leer()).reduce((suma, n) => suma + n, 0);
    },
};

function actualizarContadorCarrito() {
    const contador = document.getElementById('contador-carrito');
    if (!contador) return;
    const total = Carrito.totalUnidades();
    contador.textContent = total > 99 ? '99+' : total;
    contador.hidden = total === 0;
}

// ---------------------------------------------------------------- Formato
function formatoPrecio(valor) {
    return '$' + Number(valor).toLocaleString('es-CO', { maximumFractionDigits: 0 });
}

// ---------------------------------------------------------------- Menú móvil
document.addEventListener('DOMContentLoaded', () => {
    actualizarContadorCarrito();

    const boton = document.querySelector('.menu-movil');
    const nav = document.getElementById('navegacion');
    if (boton && nav) {
        boton.addEventListener('click', () => {
            const abierto = boton.getAttribute('aria-expanded') === 'true';
            boton.setAttribute('aria-expanded', String(!abierto));
            nav.classList.toggle('abierta', !abierto);
        });
    }
});

// Si el carrito cambia en otra pestaña, actualizar el contador.
window.addEventListener('storage', (evento) => {
    if (evento.key === Carrito.CLAVE) actualizarContadorCarrito();
});
