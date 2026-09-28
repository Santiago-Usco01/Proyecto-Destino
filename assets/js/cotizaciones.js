/**
 * Cotizaciones (mayoristas y pastelería).
 * Arma el mensaje con los datos opcionales y abre WhatsApp de la sede elegida.
 * No se envía nada al servidor ni se guarda en la base de datos.
 */
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
               'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

/** "2026-10-12" → "lunes 12 de octubre de 2026" */
function fechaLegible(ymd) {
    const [a, m, d] = ymd.split('-').map(Number);
    const fecha = new Date(a, m - 1, d);
    return `${DIAS[fecha.getDay()]} ${d} de ${MESES[m - 1]} de ${a}`;
}

const valor = (form, nombre) => (form.elements[nombre]?.value || '').trim();

const constructores = {
    mayoristas(form, sede) {
        const lineas = [`Hola, Destino ${sede}. Me interesa una cotización de pan al por mayor.`];
        const nombre = valor(form, 'nombre');
        const negocio = valor(form, 'negocio');
        const detalle = valor(form, 'detalle');
        if (nombre || negocio || detalle) lineas.push('');
        if (nombre) lineas.push(`Nombre: ${nombre}`);
        if (negocio) lineas.push(`Negocio: ${negocio}`);
        if (detalle) lineas.push(`Lo que necesito: ${detalle}`);
        return lineas.join('\n');
    },

    pasteleria(form, sede) {
        const lineas = [`Hola, Destino ${sede}. Me interesa cotizar tortas o postres para un evento.`];
        const nombre = valor(form, 'nombre');
        const fecha = valor(form, 'fecha');
        const detalle = valor(form, 'detalle');
        if (nombre || fecha || detalle) lineas.push('');
        if (nombre) lineas.push(`Nombre: ${nombre}`);
        if (fecha) lineas.push(`Fecha del evento: ${fechaLegible(fecha)}`);
        if (detalle) lineas.push(`Mi idea: ${detalle}`);
        return lineas.join('\n');
    },
};

/** Validaciones propias de cada formulario. Devuelve un mensaje de error o ''. */
const validaciones = {
    pasteleria(form) {
        const fecha = valor(form, 'fecha');
        if (fecha && fecha < window.COTIZACIONES.fechaMinimaEvento) {
            return 'Los pedidos de pastelería se hacen con al menos una semana de anticipación. Elige una fecha posterior.';
        }
        return '';
    },
};

document.querySelectorAll('[data-cotizacion]').forEach((form) => {
    const tipo = form.dataset.cotizacion;
    const error = form.querySelector('[data-error]');

    form.addEventListener('submit', (evento) => {
        evento.preventDefault();

        const mensajeError = validaciones[tipo]?.(form) || '';
        if (error) {
            error.textContent = mensajeError;
            error.hidden = mensajeError === '';
        }
        if (mensajeError) return;

        const sede = form.querySelector('input[name="sede"]:checked');
        if (!sede) return;

        const mensaje = constructores[tipo](form, sede.dataset.sede);
        const enlace = `https://wa.me/${sede.dataset.whatsapp}?text=${encodeURIComponent(mensaje)}`;
        window.open(enlace, '_blank', 'noopener');
    });
});
