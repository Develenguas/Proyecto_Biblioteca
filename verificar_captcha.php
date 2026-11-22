<?php
declare(strict_types=1);
require __DIR__ . '/seguridad.php';

header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, array $datos): never
{
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}
if (!csrf_valido()) {
    responder(400, ['ok' => false, 'mensaje' => 'Solicitud inválida. Recarga la página.']);
}

$contexto = ($_POST['contexto'] ?? '') === 'registro' ? 'registro' : 'login';

// captcha_valido() consume el código: cada intento exige una imagen nueva.
if (captcha_valido((string)($_POST['captcha'] ?? ''))) {
    $_SESSION['captcha_aprobado'] = time();
    responder(200, ['ok' => true]);
}

bitacora(null, 'CAPTCHA_FALLIDO', $contexto);
responder(200, ['ok' => false, 'mensaje' => 'Código incorrecto. Inténtalo de nuevo.']);
