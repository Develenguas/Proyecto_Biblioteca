<?php /* Ventana emergente del captcha. Se incluye en login.php y registro.php */ ?>
<div id="captcha-fondo" class="modal-fondo" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="captcha-titulo">
        <h2 id="captcha-titulo">Verificación</h2>
        <p>Escribe las letras de la imagen para continuar.</p>

        <img id="captcha-imagen" src="" width="240" height="60" alt="Código de verificación">
        <button type="button" id="captcha-recargar" class="boton-enlace">↻ Otra imagen</button>

        <input id="captcha-codigo" type="text" maxlength="6" autocomplete="off" autocapitalize="characters" aria-label="Código de verificación">
        <p id="captcha-error" class="alerta error" hidden></p>

        <div class="modal-botones">
            <button type="button" id="captcha-cancelar" class="secundario">Cancelar</button>
            <button type="button" id="captcha-verificar">Verificar</button>
        </div>
    </div>
</div>
