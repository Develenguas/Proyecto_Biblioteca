(function () {
    const preloader = document.getElementById('biblio-preloader');
    if (!preloader) {
        return;
    }

    const textoEstado = preloader.querySelector('.biblio-preloader-texto');
    const inicioCarga = performance.now();
    let ocultado = false;

    function actualizarEstado(mensaje) {
        if (textoEstado && mensaje) {
            textoEstado.textContent = mensaje;
        }
    }

    function ocultarPreloader() {
        if (ocultado) {
            return;
        }
        ocultado = true;
        preloader.classList.add('is-oculto');

        preloader.addEventListener('transitionend', function onEnd(ev) {
            if (ev.propertyName === 'opacity') {
                preloader.removeEventListener('transitionend', onEnd);
                preloader.setAttribute('hidden', '');
            }
        });

        // Respaldo por si la transición CSS no dispara transitionend
        window.setTimeout(function () {
            if (preloader.classList.contains('is-oculto')) {
                preloader.setAttribute('hidden', '');
            }
        }, 500);
    }

    // Si el documento ya terminó de cargar antes de ejecutar este script
    if (document.readyState === 'complete') {
        ocultarPreloader();
        return;
    }

    // Avance real según las fases del navegador (HTML parseado -> recursos descargados)
    if (document.readyState === 'interactive') {
        actualizarEstado('Descargando estilos y recursos del servidor...');
    } else {
        actualizarEstado('Conectando con el servidor...');
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!ocultado) {
            actualizarEstado('Cargando recursos visuales...');
        }
    }, { once: true });

    // Si la conexión tarda, avisar tras unos segundos sin cortar la carga real
    const avisoConexionLenta = window.setTimeout(function () {
        if (!ocultado && document.readyState !== 'complete') {
            const seg = Math.round((performance.now() - inicioCarga) / 1000);
            actualizarEstado('Conexión lenta, finalizando carga (' + seg + ' s)...');
        }
    }, 3000);

    // Se oculta en el momento exacto en que el navegador termina de cargar todo
    window.addEventListener('load', function () {
        window.clearTimeout(avisoConexionLenta);
        ocultarPreloader();
    }, { once: true });
})();