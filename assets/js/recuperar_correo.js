(function () {
    'use strict';

    var form = document.querySelector('form[data-captcha-form="recuperar"]');
    if (!form) { return; }

    var correoInput = form.querySelector('#correo');
    var btnSubmit = form.querySelector('button[type="submit"]');
    var cajaErrores = document.getElementById('errores-cliente');
    var REGEX_CORREO = /^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/;

    var ultimoCorreoConsultado = '';
    var correoValidoEnBd = false;
    var consultando = false;
    var timerDebounce = null;

    if (!correoInput || !btnSubmit) { return; }

    function mostrarMensajeError(msg) {
        if (!cajaErrores) { return; }
        if (!msg) {
            cajaErrores.hidden = true;
            cajaErrores.textContent = '';
            return;
        }
        cajaErrores.hidden = false;
        cajaErrores.textContent = msg;
    }

    function comprobarCorreoEnServidor(correo) {
        consultando = true;
        btnSubmit.disabled = true;

        var datos = new URLSearchParams();
        datos.set('correo', correo);

        return fetch('verificar_correo_recuperacion.php', {
            method: 'POST',
            body: datos,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                ultimoCorreoConsultado = correo;
                if (res && res.ok && res.existe) {
                    correoValidoEnBd = true;
                    mostrarMensajeError('');
                    btnSubmit.disabled = false;
                } else {
                    correoValidoEnBd = false;
                    mostrarMensajeError((res && res.mensaje) || 'Este correo no está registrado.');
                    btnSubmit.disabled = true;
                }
                return correoValidoEnBd;
            })
            .catch(function () {
                correoValidoEnBd = false;
                mostrarMensajeError('No se pudo verificar el correo. Inténtalo de nuevo.');
                btnSubmit.disabled = false;
                return false;
            })
            .finally(function () {
                consultando = false;
            });
    }

    function evaluarCambio() {
        var correo = correoInput.value.trim().toLowerCase();

        if (timerDebounce) {
            window.clearTimeout(timerDebounce);
        }

        if (correo === '') {
            ultimoCorreoConsultado = '';
            correoValidoEnBd = false;
            mostrarMensajeError('');
            btnSubmit.disabled = false;
            return;
        }

        if (!REGEX_CORREO.test(correo) || correo.length > 254) {
            ultimoCorreoConsultado = correo;
            correoValidoEnBd = false;
            mostrarMensajeError('Escribe un correo válido (con @ y extensión, ej. nombre@correo.com).');
            btnSubmit.disabled = true;
            return;
        }

        if (correo === ultimoCorreoConsultado) {
            mostrarMensajeError(correoValidoEnBd ? '' : 'Este correo no está registrado en el sistema.');
            btnSubmit.disabled = !correoValidoEnBd;
            return;
        }

        timerDebounce = window.setTimeout(function () {
            comprobarCorreoEnServidor(correo);
        }, 300);
    }

    correoInput.addEventListener('input', evaluarCambio);
    correoInput.addEventListener('blur', function () {
        if (timerDebounce) {
            window.clearTimeout(timerDebounce);
        }
        var correo = correoInput.value.trim().toLowerCase();
        if (correo !== '' && REGEX_CORREO.test(correo) && correo.length <= 254 && correo !== ultimoCorreoConsultado) {
            comprobarCorreoEnServidor(correo);
        }
    });

    // Fase de captura (true): corre ANTES que captcha_modal.js para impedir abrir el modal si no existe
    form.addEventListener('submit', function (ev) {
        if (consultando) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
            return;
        }

        var correo = correoInput.value.trim().toLowerCase();

        if (correo === '' || !REGEX_CORREO.test(correo) || correo.length > 254) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
            correoValidoEnBd = false;
            btnSubmit.disabled = true;
            mostrarMensajeError('Escribe un correo válido (con @ y extensión, ej. nombre@correo.com).');
            return;
        }

        if (correo === ultimoCorreoConsultado && !correoValidoEnBd) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
            btnSubmit.disabled = true;
            mostrarMensajeError('Este correo no está registrado en el sistema. No se puede enviar el código.');
            return;
        }

        if (correo !== ultimoCorreoConsultado) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
            comprobarCorreoEnServidor(correo).then(function (existe) {
                if (existe) {
                    form.requestSubmit();
                }
            });
        }
    }, true);
})();