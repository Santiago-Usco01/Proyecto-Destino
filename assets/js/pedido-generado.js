/**
 * El pedido ya quedó guardado: se vacía el carrito del navegador.
 * Solo una vez por pedido, para no borrar un carrito nuevo si el cliente
 * vuelve a abrir esta página más tarde.
 * Depende de main.js (Carrito).
 */
(() => {
    const codigo = document.querySelector('[data-codigo-pedido]')?.dataset.codigoPedido;
    const CLAVE = 'destino_ultimo_pedido';
    try {
        if (codigo && localStorage.getItem(CLAVE) !== codigo) {
            Carrito.vaciar();
            localStorage.setItem(CLAVE, codigo);
        }
    } catch {
        // Sin almacenamiento disponible no hay carrito que vaciar.
    }
})();
