/**
 * Menú: agregar productos al carrito y buscar en la carta.
 * Depende de main.js (Carrito).
 */
document.addEventListener('DOMContentLoaded', () => {
    // ------------------------------------------------------------ Agregar al carrito
    const aviso = document.getElementById('aviso-carrito');
    const avisoTexto = document.getElementById('aviso-carrito-texto');
    let temporizadorAviso;

    document.querySelectorAll('[data-agregar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            Carrito.agregar(Number(boton.dataset.agregar));

            avisoTexto.textContent = `${boton.dataset.nombre} agregado al carrito`;
            aviso.hidden = false;
            clearTimeout(temporizadorAviso);
            temporizadorAviso = setTimeout(() => { aviso.hidden = true; }, 3500);

            boton.textContent = 'Agregado ✓';
            setTimeout(() => { boton.textContent = 'Agregar'; }, 1200);
        });
    });

    // ------------------------------------------------------------ Buscador
    const buscador = document.getElementById('buscar-producto');
    const sinResultados = document.getElementById('sin-resultados');
    const categorias = document.querySelectorAll('[data-categoria]');

    // Minúsculas y sin tildes: "Almojábana" coincide con "almojabana".
    const normalizar = (texto) => texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    buscador?.addEventListener('input', () => {
        const termino = normalizar(buscador.value.trim());
        let visibles = 0;

        categorias.forEach((categoria) => {
            let visiblesEnCategoria = 0;
            categoria.querySelectorAll('[data-producto]').forEach((producto) => {
                const coincide = termino === '' || normalizar(producto.dataset.busqueda).includes(termino);
                producto.hidden = !coincide;
                if (coincide) visiblesEnCategoria++;
            });
            categoria.hidden = visiblesEnCategoria === 0;
            visibles += visiblesEnCategoria;
        });

        sinResultados.hidden = visibles > 0;
    });
});
