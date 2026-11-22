<?php
declare(strict_types=1);

require __DIR__ . '/seguridad.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['id_usuario'])) {
    bitacora((int)$_SESSION['id_usuario'], 'LOGOUT_PESTANA', 'Cierre de pestaña');
    cerrar_sesion();
}

http_response_code(204);