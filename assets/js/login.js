(function () {
    'use strict';
    // Botón "ojo": muestra u oculta la contraseña.
    document.querySelectorAll('button.ojo').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var campo = document.getElementById(btn.getAttribute('data-objetivo'));
            if (!campo) { return; }
            var mostrar = campo.type === 'password';
            campo.type = mostrar ? 'text' : 'password';
            btn.classList.toggle('activo', mostrar);
            btn.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
            btn.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
            campo.focus();
        });
    });
})();
