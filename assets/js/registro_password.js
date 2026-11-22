(function () {
    const passInput = document.getElementById('password');
    const pass2Input = document.getElementById('password2');
    const coincidenciaEl = document.getElementById('coincidencia-password');

    if (!passInput || !pass2Input) {
        return;
    }

    const reglas = {
        longitud: {
            el: document.getElementById('req-longitud'),
            test: function (val) {
                return val.length >= 8 && val.length <= 72;
            }
        },
        mayuscula: {
            el: document.getElementById('req-mayuscula'),
            test: function (val) {
                return /[A-Z]/.test(val);
            }
        },
        numero: {
            el: document.getElementById('req-numero'),
            test: function (val) {
                return /\d/.test(val);
            }
        },
        simbolo: {
            el: document.getElementById('req-simbolo'),
            test: function (val) {
                return /[^A-Za-z0-9]/.test(val);
            }
        }
    };

    function actualizarRequisitos() {
        const val = passInput.value;
        Object.keys(reglas).forEach(function (clave) {
            const item = reglas[clave];
            if (!item.el) {
                return;
            }
            const cumplido = item.test(val);
            item.el.classList.toggle('cumplido', cumplido);
            const indicador = item.el.querySelector('.req-indicador');
            if (indicador) {
                indicador.textContent = cumplido ? '✓' : '○';
            }
        });
    }

    function actualizarCoincidencia() {
        if (!coincidenciaEl) {
            return;
        }
        const p1 = passInput.value;
        const p2 = pass2Input.value;

        if (p2.length === 0) {
            coincidenciaEl.hidden = true;
            coincidenciaEl.textContent = '';
            coincidenciaEl.classList.remove('coincide', 'no-coincide');
            return;
        }

        coincidenciaEl.hidden = false;
        if (p1 === p2) {
            coincidenciaEl.textContent = '✓ Las contraseñas coinciden.';
            coincidenciaEl.classList.add('coincide');
            coincidenciaEl.classList.remove('no-coincide');
        } else {
            coincidenciaEl.textContent = '✕ Las contraseñas no coinciden.';
            coincidenciaEl.classList.add('no-coincide');
            coincidenciaEl.classList.remove('coincide');
        }
    }

    passInput.addEventListener('input', function () {
        actualizarRequisitos();
        actualizarCoincidencia();
    });

    pass2Input.addEventListener('input', actualizarCoincidencia);

    actualizarRequisitos();
    actualizarCoincidencia();
})();