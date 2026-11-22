(function () {
    'use strict';

    function notificarCierre() {
        if (navigator.sendBeacon) {
            navigator.sendBeacon('cerrar_pestana.php');
        }
    }

    window.addEventListener('pagehide', function (ev) {
        if (!ev.persisted) {
            notificarCierre();
        }
    });
})();