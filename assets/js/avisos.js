/**
 * Avisos (mensajes flash) del sitio y del panel.
 */
// ---------------------------------------------------------------- Avisos (mensajes flash)
// Los avisos del servidor flotan sobre la página: se cierran con la × o solos tras unos segundos.
document.querySelectorAll('.avisos .aviso').forEach((aviso) => {
    const quitar = () => {
        aviso.classList.add('aviso--saliendo');
        setTimeout(() => {
            const contenedor = aviso.parentElement;
            aviso.remove();
            if (contenedor && !contenedor.children.length) {
                contenedor.remove();
            }
        }, 300);
    };
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'aviso__cerrar';
    boton.setAttribute('aria-label', 'Cerrar aviso');
    boton.textContent = '×';
    boton.addEventListener('click', quitar);
    aviso.append(boton);
    // Los errores se quedan más tiempo para que se alcancen a leer.
    setTimeout(quitar, aviso.classList.contains('aviso--error') ? 9000 : 5000);
});
