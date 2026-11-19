(function () {
    'use strict';

    var form = document.querySelector('form[data-captcha-form]');
    if (!form) { return; }

    var contexto = form.getAttribute('data-captcha-form');
    var cajaErrores = document.getElementById('errores-cliente');
    var fondo = document.getElementById('captcha-fondo');
    var imagen = document.getElementById('captcha-imagen');
    var campo = document.getElementById('captcha-codigo');
    var errorModal = document.getElementById('captcha-error');
    var btnVerificar = document.getElementById('captcha-verificar');
    var btnCancelar = document.getElementById('captcha-cancelar');
    var btnRecargar = document.getElementById('captcha-recargar');
    var enviado = false;

    // Mismo criterio que el servidor: texto@dominio.extension
    var REGEX_CORREO = /^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/;

    function valor(nombre) {
        var el = form.elements[nombre];
        return el ? el.value : '';
    }

    function validar() {
        var errores = [];
        var correo = valor('correo').trim();

        if (contexto === 'registro') {
            var nombre = valor('nombre').trim();
            var pass = valor('password');
            if (nombre === '' || nombre.length > 100) {
                errores.push('El nombre es obligatorio (máximo 100 caracteres).');
            }
            if (!REGEX_CORREO.test(correo) || correo.length > 254) {
                errores.push('El correo no es válido. Debe tener @ y una extensión (ej. nombre@correo.com).');
            }
            if (valor('rol') === '') {
                errores.push('Selecciona un rol.');
            }
            if (pass.length < 10 || pass.length > 72) {
                errores.push('La contraseña debe tener entre 10 y 72 caracteres.');
            } else if (!/[A-Za-z]/.test(pass) || !/\d/.test(pass)) {
                errores.push('La contraseña debe incluir letras y números.');
            }
            if (pass !== valor('password2')) {
                errores.push('Las contraseñas no coinciden.');
            }
        } else {
            if (!REGEX_CORREO.test(correo)) {
                errores.push('Escribe un correo válido (con @ y extensión, ej. nombre@correo.com).');
            }
            if (valor('password') === '') {
                errores.push('Escribe tu contraseña.');
            }
        }
        return errores;
    }

    function mostrarErrores(errores) {
        cajaErrores.textContent = '';
        errores.forEach(function (msg) {
            var p = document.createElement('div');
            p.textContent = msg;
            cajaErrores.appendChild(p);
        });
        cajaErrores.hidden = errores.length === 0;
    }

    function cargarImagen() {
        imagen.src = 'captcha.php?r=' + Date.now();
        campo.value = '';
    }

    function abrirModal() {
        errorModal.hidden = true;
        cargarImagen();
        fondo.hidden = false;
        campo.focus();
    }

    function cerrarModal() {
        fondo.hidden = true;
        errorModal.hidden = true;
    }

    function verificar() {
        var codigo = campo.value.trim();
        if (codigo === '') {
            errorModal.textContent = 'Escribe el código de la imagen.';
            errorModal.hidden = false;
            campo.focus();
            return;
        }
        btnVerificar.disabled = true;

        var datos = new URLSearchParams();
        datos.set('csrf', valor('csrf'));
        datos.set('captcha', codigo);
        datos.set('contexto', contexto);

        fetch('verificar_captcha.php', {
            method: 'POST',
            body: datos,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    // Captcha correcto: ahora sí se envía el formulario.
                    enviado = true;
                    cerrarModal();
                    form.submit();
                    return;
                }
                // Captcha incorrecto: se queda la ventana, con imagen nueva, para reintentar.
                errorModal.textContent = res.mensaje || 'Código incorrecto. Inténtalo de nuevo.';
                errorModal.hidden = false;
                cargarImagen();
                campo.focus();
            })
            .catch(function () {
                errorModal.textContent = 'No se pudo verificar. Revisa tu conexión e inténtalo de nuevo.';
                errorModal.hidden = false;
            })
            .then(function () { btnVerificar.disabled = false; });
    }

    form.addEventListener('submit', function (ev) {
        if (enviado) { return; }
        ev.preventDefault();
        var errores = validar();
        mostrarErrores(errores);
        if (errores.length === 0) {
            abrirModal();
        }
    });

    btnVerificar.addEventListener('click', verificar);
    btnCancelar.addEventListener('click', cerrarModal);
    btnRecargar.addEventListener('click', function () { cargarImagen(); campo.focus(); });
    campo.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') { ev.preventDefault(); verificar(); }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && !fondo.hidden) { cerrarModal(); }
    });
})();
