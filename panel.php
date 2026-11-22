<?php
declare(strict_types=1);
require __DIR__ . '/seguridad.php';
cerrar_sesion(); 
redirigir('login.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body >
<main class="tarjeta">
    <h1>Login cargado</h1>
    <p class="alerta ok">Sesión iniciada correctamente.</p>
    <p><strong>Usuario:</strong> <?= e($_SESSION['nombre']) ?></p>
    <p><strong>Rol:</strong> <?= e($_SESSION['rol']) ?></p>

    <form method="post" action="logout.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit">Cerrar sesión</button>
    </form>
</main>
</body>
</html>
