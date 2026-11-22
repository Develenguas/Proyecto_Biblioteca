(function () {
    const forms = document.querySelectorAll('form');
    const erroresBox = document.getElementById('errores-cliente');

    function mostrarErrores(lista) {
        if (!erroresBox) {
            return;
        }
        if (!lista.length) {
            erroresBox.hidden = true;
            erroresBox.textContent = '';
            return;
        }
        erroresBox.hidden = false;
        erroresBox.innerHTML = lista.map(function (msg) {
            return '<div>' + msg.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
        }).join('');
    }

    forms.forEach(function (form) {
        if (form.dataset.captchaForm) {
            return;
        }

        form.addEventListener('submit', function (ev) {
            const errores = [];
            const correoInput = form.querySelector('#correo');
            const passInput = form.querySelector('#password');

            if (correoInput && !correoInput.disabled) {
                const valCorreo = correoInput.value.trim();
                if (!valCorreo) {
                    errores.push('Ingresa tu correo electrónico.');
                }
            }

            // Solo validar contraseña si el formulario tiene el campo #password presente y visible
            if (passInput && !passInput.disabled && passInput.offsetParent !== null) {
                if (!passInput.value) {
                    errores.push('Escribe tu contraseña.');
                }
            }

            if (errores.length > 0) {
                ev.preventDefault();
                mostrarErrores(errores);
            } else {
                mostrarErrores([]);
            }
        });
    });

    // Botones mostrar / ocultar contraseña (data-objetivo)
    document.querySelectorAll('button.ojo[data-objetivo]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const idInput = btn.getAttribute('data-objetivo');
            const input = idInput ? document.getElementById(idInput) : null;
            if (!input) {
                return;
            }
            const esPassword = input.type === 'password';
            input.type = esPassword ? 'text' : 'password';
            btn.classList.toggle('activo', esPassword);
            btn.setAttribute('aria-pressed', esPassword ? 'true' : 'false');
        });
    });
})();
(function () {
    var modo = document.body.dataset.tab;

    if (modo === 'login') {
        sessionStorage.setItem('tab_activa', '1');
    } else if (modo === 'protegida') {
        if (!sessionStorage.getItem('tab_activa')) {
            window.location.replace('logout.php');
        }
    }
})();