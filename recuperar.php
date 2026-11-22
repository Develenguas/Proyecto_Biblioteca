<?php
declare(strict_types=1);
require __DIR__ . '/seguridad.php';

if (!empty($_SESSION['id_usuario'])) {
    redirigir('panel.php');
}

$errores = [];
$correoInput = '';
$maxIntentosCodigo = (int)($config['correo']['max_intentos_codigo'] ?? 5);

// Cancelar proceso de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar_recuperacion') {
    if (!csrf_valido()) {
        http_response_code(400);
        $errores[] = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
    } else {
        unset($_SESSION['recuperacion_pass']);
        redirigir('recuperar.php');
    }
}

// Reenviar código de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reenviar_recuperacion') {
    if (!csrf_valido()) {
        http_response_code(400);
        $errores[] = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
    } elseif (empty($_SESSION['recuperacion_pass']) || !is_array($_SESSION['recuperacion_pass'])) {
        $errores[] = 'No hay una solicitud de recuperación activa.';
    } else {
        $rec = $_SESSION['recuperacion_pass'];
        $nuevoCodigo = generar_codigo_verificacion();
        $expMin = (int)($config['correo']['minutos_expiracion'] ?? 10);

        $_SESSION['recuperacion_pass']['codigo'] = $nuevoCodigo;
        $_SESSION['recuperacion_pass']['expira'] = time() + ($expMin * 60);
        $_SESSION['recuperacion_pass']['intentos'] = 0;

        if ((int)($rec['id_usuario'] ?? 0) > 0) {
            enviar_codigo_correo((string)$rec['correo'], 'Código para recuperar contraseña', $nuevoCodigo, 'recuperar tu contraseña');
            bitacora((int)$rec['id_usuario'], 'CODIGO_RECUPERACION_REENVIADO');
        }
        flash('Si el correo corresponde a una cuenta activa, se ha reenviado el código.');
        redirigir('recuperar.php');
    }
}

// Paso 2: verificar código y actualizar password_hash en usuarios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'restablecer_password') {
    if (!csrf_valido()) {
        http_response_code(400);
        $errores[] = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
    } elseif (empty($_SESSION['recuperacion_pass']) || !is_array($_SESSION['recuperacion_pass'])) {
        $errores[] = 'La solicitud de recuperación expiró. Solicita un nuevo código.';
    } else {
        $rec = $_SESSION['recuperacion_pass'];
        $codigoIngresado = trim((string)($_POST['codigo_verificacion'] ?? ''));
        $nuevaPass = (string)($_POST['password'] ?? '');
        $confPass = (string)($_POST['password2'] ?? '');

        if (time() > (int)($rec['expira'] ?? 0)) {
            unset($_SESSION['recuperacion_pass']);
            $errores[] = 'El código de verificación ha expirado. Solicita uno nuevo.';
        } elseif ((int)($rec['intentos'] ?? 0) >= $maxIntentosCodigo) {
            unset($_SESSION['recuperacion_pass']);
            if ((int)($rec['id_usuario'] ?? 0) > 0) {
                bitacora((int)$rec['id_usuario'], 'CODIGO_RECUPERACION_BLOQUEADO');
            }
            $errores[] = 'Se excedió el número máximo de intentos. Solicita un nuevo código.';
        } else {
            if (strlen($nuevaPass) < 8 || strlen($nuevaPass) > 72) {
                $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
            } elseif (!preg_match('/[A-Z]/', $nuevaPass)) {
                $errores[] = 'La contraseña debe incluir al menos 1 letra mayúscula.';
            } elseif (!preg_match('/\d/', $nuevaPass)) {
                $errores[] = 'La contraseña debe incluir al menos 1 número.';
            } elseif (!preg_match('/[^A-Za-z0-9]/', $nuevaPass)) {
                $errores[] = 'La contraseña debe incluir al menos 1 símbolo.';
            }
            if ($nuevaPass !== $confPass) {
                $errores[] = 'Las contraseñas no coinciden.';
            }

            if (!$errores) {
                $codigoEsperado = (string)($rec['codigo'] ?? '');
                $codigoValido = $codigoEsperado !== ''
                    && preg_match('/^\d{6}$/', $codigoIngresado)
                    && hash_equals($codigoEsperado, $codigoIngresado);

                if (!$codigoValido) {
                    $_SESSION['recuperacion_pass']['intentos'] = (int)($rec['intentos'] ?? 0) + 1;
                    $restantes = $maxIntentosCodigo - (int)$_SESSION['recuperacion_pass']['intentos'];
                    if ((int)($rec['id_usuario'] ?? 0) > 0) {
                        bitacora((int)$rec['id_usuario'], 'CODIGO_RECUPERACION_FALLIDO');
                    }
                    $errores[] = $restantes > 0
                        ? "Código incorrecto. Te quedan {$restantes} intento(s)."
                        : 'Código incorrecto. Se agotaron los intentos; solicita un nuevo código.';
                } else {
                    $idUsuario = (int)($rec['id_usuario'] ?? 0);
                    if ($idUsuario <= 0) {
                        unset($_SESSION['recuperacion_pass']);
                        $errores[] = 'No se pudo completar el restablecimiento. Solicita un nuevo código.';
                    } else {
                        try {
                            $nuevoHash = password_hash($nuevaPass, PASSWORD_BCRYPT, ['cost' => 12]);
                            $upd = db()->prepare(
                                'UPDATE usuarios
                                 SET password_hash = :h,
                                     intentos_fallidos = 0,
                                     fecha_bloqueo_hasta = NULL
                                 WHERE id_usuario = :id AND activo = 1'
                            );
                            $upd->execute([
                                ':h'  => $nuevoHash,
                                ':id' => $idUsuario,
                            ]);

                            unset($_SESSION['recuperacion_pass']);
                            if ($upd->rowCount() > 0) {
                                bitacora($idUsuario, 'PASSWORD_RECUPERADA');
                                flash('Contraseña actualizada. Ya puedes iniciar sesión.');
                                redirigir('login.php');
                            } else {
                                $errores[] = 'La cuenta no está disponible para restablecer contraseña.';
                            }
                        } catch (PDOException $ex) {
                            error_log('Error al restablecer contraseña: ' . $ex->getMessage());
                            $errores[] = 'No se pudo actualizar la contraseña. Inténtalo más tarde.';
                        }
                    }
                }
            }
        }
    }
}

// Paso 1: validar que el correo exista en la tabla usuarios antes de enviar el código
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? 'solicitar_codigo') === 'solicitar_codigo') {
    if (!csrf_valido()) {
        http_response_code(400);
        $errores[] = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
    } else {
        $correoInput = strtolower(trim((string)($_POST['correo'] ?? '')));

        if (!captcha_aprobado()) {
            $errores[] = 'Debes resolver el captcha para continuar.';
        } elseif (!correo_valido($correoInput)) {
            $errores[] = 'El correo no es válido. Debe tener @ y un dominio con extensión (ej. nombre@correo.com).';
        } else {
            try {
                $st = db()->prepare(
                    'SELECT id_usuario, correo, activo
                     FROM usuarios
                     WHERE correo = :c
                     LIMIT 1'
                );
                $st->execute([':c' => $correoInput]);
                $usuario = $st->fetch();

                if (!$usuario) {
                    bitacora(null, 'CODIGO_RECUPERACION_NO_EXISTE', $correoInput);
                    $errores[] = 'No existe una cuenta registrada con ese correo electrónico.';
                } elseif ((int)$usuario['activo'] !== 1) {
                    bitacora((int)$usuario['id_usuario'], 'CODIGO_RECUPERACION_INACTIVA', $correoInput);
                    $errores[] = 'La cuenta asociada a este correo está inactiva.';
                } else {
                    $codigo = generar_codigo_verificacion();
                    $expMin = (int)($config['correo']['minutos_expiracion'] ?? 10);

                    $_SESSION['recuperacion_pass'] = [
                        'id_usuario' => (int)$usuario['id_usuario'],
                        'correo'     => (string)$usuario['correo'],
                        'codigo'     => $codigo,
                        'expira'     => time() + ($expMin * 60),
                        'intentos'   => 0,
                    ];

                    if (enviar_codigo_correo((string)$usuario['correo'], 'Código para recuperar contraseña', $codigo, 'recuperar tu contraseña')) {
                        bitacora((int)$usuario['id_usuario'], 'CODIGO_RECUPERACION_ENVIADO');
                        flash('Enviamos un código de verificación a tu correo registrado.');
                        redirigir('recuperar.php');
                    } else {
                        unset($_SESSION['recuperacion_pass']);
                        $errores[] = 'No se pudo enviar el código de verificación. Inténtalo más tarde.';
                    }
                }
            } catch (PDOException $ex) {
                error_log('Error al validar cuenta en recuperación: ' . $ex->getMessage());
                $errores[] = 'No se pudo procesar la solicitud. Inténtalo más tarde.';
            }
        }
    }
}

$pasoCodigo = !empty($_SESSION['recuperacion_pass']) && is_array($_SESSION['recuperacion_pass']);
$correoRec = $pasoCodigo ? (string)($_SESSION['recuperacion_pass']['correo'] ?? '') : '';
$mensajeFlash = flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña · Biblioteca</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="auth">
<div class="auth-caja">
    <aside class="auth-lado">
        <div class="marca"><img src="assets/img/logo.svg" alt="" width="42" height="42"> Biblioteca</div>
        <div class="texto-lado">
            <h2>Recupera tu acceso</h2>
            <p>Verifica tu correo institucional o personal para asignar una nueva contraseña.</p>
        </div>
        <img class="ilustracion" src="assets/img/libros.svg" alt="" width="300" height="178">
    </aside>
    <main class="auth-form">
        <?php if ($pasoCodigo): ?>
            <h1>Restablecer contraseña</h1>
            <p class="sub">Ingresa el código enviado a <strong><?= e($correoRec) ?></strong> y define tu nueva contraseña.</p>

            <?php if ($mensajeFlash): ?>
                <p class="alerta ok"><?= e($mensajeFlash) ?></p>
            <?php endif; ?>
            <?php foreach ($errores as $err): ?>
                <p class="alerta error"><?= e($err) ?></p>
            <?php endforeach; ?>

            <div id="errores-cliente" class="alerta error" hidden></div>

            <form method="post" autocomplete="off" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="restablecer_password">

                <label for="codigo_verificacion">Código de verificación</label>
                <div class="campo">
                    <input id="codigo_verificacion" name="codigo_verificacion" class="codigo-verificacion" type="text" inputmode="numeric" maxlength="6" pattern="\d{6}" required autofocus placeholder="000000">
                </div>

                <label for="password">Nueva contraseña</label>
                <div class="campo con-ojo">
                    <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                    <input id="password" name="password" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
                    <button type="button" class="ojo" data-objetivo="password" aria-label="Mostrar contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>

                <ul id="requisitos-password" class="requisitos-pass" aria-label="Requisitos de contraseña">
                    <li id="req-longitud" class="req-item">
                        <span class="req-indicador" aria-hidden="true">○</span>
                        <span>Al menos 8 caracteres</span>
                    </li>
                    <li id="req-mayuscula" class="req-item">
                        <span class="req-indicador" aria-hidden="true">○</span>
                        <span>Al menos 1 letra mayúscula</span>
                    </li>
                    <li id="req-numero" class="req-item">
                        <span class="req-indicador" aria-hidden="true">○</span>
                        <span>Al menos 1 número</span>
                    </li>
                    <li id="req-simbolo" class="req-item">
                        <span class="req-indicador" aria-hidden="true">○</span>
                        <span>Al menos 1 símbolo</span>
                    </li>
                </ul>

                <label for="password2">Confirmar nueva contraseña</label>
                <div class="campo con-ojo">
                    <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                    <input id="password2" name="password2" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
                    <button type="button" class="ojo" data-objetivo="password2" aria-label="Mostrar contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
                <p id="coincidencia-password" class="estado-coincidencia" aria-live="polite" hidden></p>

                <button type="submit">Guardar nueva contraseña</button>
            </form>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="reenviar_recuperacion">
                <button type="submit" class="secundario">Reenviar código</button>
            </form>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="cancelar_recuperacion">
                <button type="submit" class="boton-enlace">Usar otro correo</button>
            </form>
        <?php else: ?>
            <h1>Recuperar contraseña</h1>
            <p class="sub">Ingresa tu correo registrado para recibir un código de verificación.</p>

            <?php if ($mensajeFlash): ?>
                <p class="alerta ok"><?= e($mensajeFlash) ?></p>
            <?php endif; ?>
            <?php foreach ($errores as $err): ?>
                <p class="alerta error"><?= e($err) ?></p>
            <?php endforeach; ?>

            <div id="errores-cliente" class="alerta error" hidden></div>

            <form method="post" autocomplete="off" novalidate data-captcha-form="recuperar">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="solicitar_codigo">

                <label for="correo">Correo electrónico</label>
                <div class="campo">
                    <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    <input id="correo" name="correo" type="email" maxlength="254" required autofocus placeholder="nombre@correo.com" value="<?= e($correoInput) ?>">
                </div>

                <button type="submit">Enviar código de verificación</button>
            </form>
        <?php endif; ?>

        <p class="pie">¿Ya recordaste tu contraseña? <a href="login.php">Inicia sesión</a></p>
    </main>
</div>
<?php require __DIR__ . '/captcha_modal.php'; ?>
<script src="assets/js/login.js"></script>
<script src="assets/js/captcha_modal.js"></script>
<script src="assets/js/registro_password.js"></script>
<script src="assets/js/recuperar_correo.js"></script>
</body>
</html>