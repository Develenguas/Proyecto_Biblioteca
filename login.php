<?php
declare(strict_types=1);
require __DIR__ . '/seguridad.php';

if (!empty($_SESSION['id_usuario'])) {
    redirigir('panel.php');
}

$error = null;
$correo = '';
$mensaje = flash();

const MSG_FALLO = 'Credenciales incorrectas o cuenta bloqueada temporalmente.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        http_response_code(400);
        $error = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
    } elseif (!captcha_aprobado()) {
        // El captcha se revisa antes de tocar la cuenta: un robot no puede bloquear usuarios.
        $correo = strtolower(trim((string)($_POST['correo'] ?? '')));
        bitacora(null, 'CAPTCHA_OMITIDO', 'login');
        $error = 'Debes resolver el captcha para iniciar sesión.';
    } else {
        $correo = strtolower(trim((string)($_POST['correo'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $maxIntentos = (int)$config['max_intentos'];
        $minBloqueo = (int)$config['minutos_bloqueo'];

        try {
            $st = db()->prepare(
                'SELECT u.id_usuario, u.nombre, u.password_hash, u.activo, u.intentos_fallidos,
                        r.nombre_rol,
                        (u.fecha_bloqueo_hasta IS NOT NULL AND u.fecha_bloqueo_hasta > NOW()) AS bloqueado,
                        (u.fecha_bloqueo_hasta IS NOT NULL AND u.fecha_bloqueo_hasta <= NOW()) AS bloqueo_vencido
                 FROM usuarios u
                 JOIN roles r ON r.id_rol = u.id_rol
                 WHERE u.correo = :c'
            );
            $st->execute([':c' => $correo]);
            $u = $st->fetch();

            // Siempre se ejecuta una verificación de hash, exista o no el usuario,
            // para que el tiempo de respuesta no revele si el correo está registrado.
            $hashAComparar = $u['password_hash'] ?? password_hash('relleno-tiempo-constante', PASSWORD_BCRYPT, ['cost' => 12]);
            $passOk = password_verify($pass, $hashAComparar);

            if ($u && (int)$u['bloqueado'] === 1) {
                bitacora((int)$u['id_usuario'], 'LOGIN_BLOQUEADO', 'Intento durante bloqueo');
                $error = MSG_FALLO;
            } elseif ($u && $passOk && (int)$u['activo'] === 1) {
                // Éxito: se reinicia el contador y se renueva el ID de sesión.
                db()->prepare(
                    'UPDATE usuarios
                     SET intentos_fallidos = 0, fecha_bloqueo_hasta = NULL, fecha_ultimo_acceso = NOW()
                     WHERE id_usuario = :id'
                )->execute([':id' => $u['id_usuario']]);

                if (password_needs_rehash($u['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                    db()->prepare('UPDATE usuarios SET password_hash = :h WHERE id_usuario = :id')
                        ->execute([':h' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), ':id' => $u['id_usuario']]);
                }

                session_regenerate_id(true);
                $_SESSION['id_usuario'] = (int)$u['id_usuario'];
                $_SESSION['nombre'] = $u['nombre'];
                $_SESSION['rol'] = $u['nombre_rol'];
                $_SESSION['ultima_actividad'] = time();
                unset($_SESSION['csrf']);

                bitacora((int)$u['id_usuario'], 'LOGIN_OK');
                redirigir('panel.php');
            } else {
                if ($u) {
                    if ((int)$u['activo'] === 0) {
                        bitacora((int)$u['id_usuario'], 'LOGIN_CUENTA_INACTIVA');
                    } else {
                        // Si el bloqueo anterior ya venció, el conteo empieza de cero.
                        $previos = (int)$u['bloqueo_vencido'] === 1 ? 0 : (int)$u['intentos_fallidos'];
                        $nuevos = $previos + 1;

                        if ($nuevos >= $maxIntentos) {
                            db()->prepare(
                                "UPDATE usuarios
                                 SET intentos_fallidos = 0,
                                     fecha_bloqueo_hasta = DATE_ADD(NOW(), INTERVAL {$minBloqueo} MINUTE)
                                 WHERE id_usuario = :id"
                            )->execute([':id' => $u['id_usuario']]);
                            bitacora((int)$u['id_usuario'], 'CUENTA_BLOQUEADA', "{$minBloqueo} minutos");
                        } else {
                            db()->prepare(
                                'UPDATE usuarios SET intentos_fallidos = :n, fecha_bloqueo_hasta = NULL
                                 WHERE id_usuario = :id'
                            )->execute([':n' => $nuevos, ':id' => $u['id_usuario']]);
                            bitacora((int)$u['id_usuario'], 'LOGIN_FALLIDO', "Intento {$nuevos}");
                        }
                    }
                } else {
                    bitacora(null, 'LOGIN_FALLIDO', 'Correo no registrado');
                }
                $error = MSG_FALLO;
            }
        } catch (PDOException $ex) {
            error_log('Error en login: ' . $ex->getMessage());
            $error = 'No se pudo iniciar sesión. Inténtalo más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="tarjeta">
    <h1>Iniciar sesión</h1>

    <?php if ($mensaje): ?><p class="alerta ok"><?= e($mensaje) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alerta error"><?= e($error) ?></p><?php endif; ?>

    <div id="errores-cliente" class="alerta error" hidden></div>

    <form method="post" novalidate data-captcha-form="login">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label for="correo">Correo</label>
        <input id="correo" name="correo" type="email" maxlength="254" required autofocus value="<?= e($correo) ?>">

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" maxlength="72" required autocomplete="current-password">


        <button type="submit">Entrar</button>
    </form>

    <p class="pie">¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
</main>
<?php require __DIR__ . '/captcha_modal.php'; ?>
<script src="captcha_modal.js"></script>
</body>
</html>
