<?php
declare(strict_types=1);

require __DIR__ . '/seguridad.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'existe' => false,
        'mensaje' => 'Método no permitido.'
    ]);
    exit;
}

$correo = strtolower(trim((string)($_POST['correo'] ?? '')));

if ($correo === '') {
    echo json_encode([
        'ok' => true,
        'existe' => false,
        'mensaje' => 'Escribe el correo electrónico con el que creaste tu cuenta.'
    ]);
    exit;
}

if (!correo_valido($correo)) {
    echo json_encode([
        'ok' => true,
        'existe' => false,
        'mensaje' => 'El correo no tiene un formato válido (ej. nombre@correo.com).'
    ]);
    exit;
}

try {
    $pdo = db();
    $st = $pdo->prepare(
        'SELECT id_usuario, activo
         FROM usuarios
         WHERE LOWER(correo) = :c
         LIMIT 1'
    );
    $st->execute([':c' => $correo]);
    $usuario = $st->fetch();

    if (!$usuario) {
        echo json_encode([
            'ok' => true,
            'existe' => false,
            'mensaje' => 'No existe ninguna cuenta creada con este correo en la plataforma.'
        ]);
        exit;
    }

    if ((int)$usuario['activo'] !== 1) {
        echo json_encode([
            'ok' => true,
            'existe' => false,
            'mensaje' => 'Existe una cuenta con este correo, pero se encuentra inactiva.'
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'existe' => true,
        'mensaje' => 'Cuenta verificada: existe una cuenta activa con este correo.'
    ]);
} catch (Throwable $ex) {
    error_log('Error en verificar_correo_recuperacion.php: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'existe' => false,
        'mensaje' => 'Error de conexión con la base de datos al verificar el correo.'
    ]);
}