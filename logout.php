<?php
declare(strict_types=1);
require __DIR__ . '/seguridad.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido() && !empty($_SESSION['id_usuario'])) {
    bitacora((int)$_SESSION['id_usuario'], 'LOGOUT');
    cerrar_sesion();
}
redirigir('login.php');
