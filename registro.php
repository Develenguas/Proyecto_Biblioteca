<?php
declare(strict_types=1);
require __DIR__ . '/seguridad.php';

if (!empty($_SESSION['id_usuario'])) {
    redirigir('panel.php');
}

$errores = [];
$nombre = '';
$correo = '';
$rolElegido = '';
$maxIntentosCodigo = (int)($config['correo']['max_intentos_codigo'] ?? 5);

// Cancelar verificación pendiente y volver al formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar_verificacion') {
    if (!csrf_valido()) {
        http_response_code(400);
        $errores[] = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
    } else {
        unset($_SESSION['pendiente_registro']);
        redirigir('registro.php');
    }
}

// Reenviar código de verificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reenviar_codigo') {
    if (!csrf_valido()) {
        http_response_code(400);
        $errores[] = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
    } elseif (empty($_SESSION['pendiente_registro']) || !is_array($_SESSION['pendiente_registro'])) {
        $errores[] = 'No hay una solicitud de registro pendiente.';
    } else {
        $pend = $_SESSION['pendiente_registro'];
        $nuevoCodigo = generar_codigo_verificacion();
        $expMin = (int)($config['correo']['minutos_expiracion'] ?? 10);

        $_SESSION['pendiente_registro']['codigo'] = $nuevoCodigo;
        $_SESSION['pendiente_registro']['expira'] = time() + ($expMin * 60);
        $_SESSION['pendiente_registro']['intentos'] = 0;

        if (enviar_codigo_correo($pend['correo'], 'Código de verificación de cuenta', $nuevoCodigo, 'crear tu cuenta')) {
            bitacora(null, 'CODIGO_REGISTRO_REENVIADO', $pend['correo']);
            flash('Se envió un nuevo código de verificación a tu correo.');
            redirigir('registro.php');
        } else {
            $errores[] = 'No se pudo reenviar el código de verificación. Inténtalo más tarde.';
        }
    }
}

// Validar código ingresado y crear el usuario en la BD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'verificar_codigo') {
    if (!csrf_valido()) {
        http_response_code(400);
        $errores[] = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
    } elseif (empty($_SESSION['pendiente_registro']) || !is_array($_SESSION['pendiente_registro'])) {
        $errores[] = 'La sesión de verificación expiró. Completa el registro nuevamente.';
    } else {
        $pend = $_SESSION['pendiente_registro'];
        $codigoIngresado = trim((string)($_POST['codigo_verificacion'] ?? ''));

        if (time() > (int)($pend['expira'] ?? 0)) {
            unset($_SESSION['pendiente_registro']);
            $errores[] = 'El código de verificación ha expirado. Completa el registro nuevamente.';
        } elseif ((int)($pend['intentos'] ?? 0) >= $maxIntentosCodigo) {
            unset($_SESSION['pendiente_registro']);
            bitacora(null, 'CODIGO_REGISTRO_BLOQUEADO', (string)($pend['correo'] ?? ''));
            $errores[] = 'Se excedió el número máximo de intentos. Completa el registro nuevamente.';
        } elseif (!preg_match('/^\d{6}$/', $codigoIngresado) || !hash_equals((string)$pend['codigo'], $codigoIngresado)) {
            $_SESSION['pendiente_registro']['intentos'] = (int)($pend['intentos'] ?? 0) + 1;
            $restantes = $maxIntentosCodigo - (int)$_SESSION['pendiente_registro']['intentos'];
            bitacora(null, 'CODIGO_REGISTRO_FALLIDO', (string)($pend['correo'] ?? ''));
            $errores[] = $restantes > 0
                ? "Código incorrecto. Te quedan {$restantes} intento(s)."
                : 'Código incorrecto. Se agotaron los intentos; inicia el registro de nuevo.';
        } else {
            // Código válido: insertar en usuarios respetando esquema y triggers de equipo_4
            try {
                $pdo = db();
                $st = $pdo->prepare('SELECT id_rol FROM roles WHERE nombre_rol = :n');
                $st->execute([':n' => $pend['rol']]);
                $idRol = $st->fetchColumn();

                if ($idRol === false) {
                    unset($_SESSION['pendiente_registro']);
                    $errores[] = 'Rol no permitido.';
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO usuarios (nombre, correo, password_hash, id_rol)
                         VALUES (:n, :c, :h, :r)'
                    );
                    $ins->execute([
                        ':n' => $pend['nombre'],
                        ':c' => $pend['correo'],
                        ':h' => $pend['password_hash'],
                        ':r' => (int)$idRol,
                    ]);
                    $nuevoId = (int)$pdo->lastInsertId();
                    unset($_SESSION['pendiente_registro']);
                    bitacora($nuevoId, 'REGISTRO', 'Rol: ' . $pend['rol'] . ' (correo verificado)');
                    flash('Cuenta creada. Ya puedes iniciar sesión.');
                    redirigir('login.php');
                }
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    unset($_SESSION['pendiente_registro']);
                    $errores[] = 'Ese correo ya está registrado.';
                } else {
                    error_log('Error en registro al verificar código: ' . $ex->getMessage());
                    $errores[] = 'No se pudo completar el registro. Inténtalo más tarde.';
                }
            }
        }
    }
}

// Paso 1: validar datos del formulario inicial y enviar código al correo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? 'crear') === 'crear') {
    if (!csrf_valido()) {
        http_response_code(400);
        $errores[] = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
    } else {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $correo = strtolower(trim((string)($_POST['correo'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password2'] ?? '');
        $rolElegido = (string)($_POST['rol'] ?? '');

        if (!captcha_aprobado()) {
            $errores[] = 'Debes resolver el captcha para registrarte.';
        }
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre es obligatorio (máximo 100 caracteres).';
        }
        if (!correo_valido($correo)) {
            $errores[] = 'El correo no es válido. Debe tener @ y un dominio con extensión (ej. nombre@correo.com).';
        }
        if (strlen($pass) < 8 || strlen($pass) > 72) {
            $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
        } elseif (!preg_match('/[A-Z]/', $pass)) {
            $errores[] = 'La contraseña debe incluir al menos 1 letra mayúscula.';
        } elseif (!preg_match('/\d/', $pass)) {
            $errores[] = 'La contraseña debe incluir al menos 1 número.';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $pass)) {
            $errores[] = 'La contraseña debe incluir al menos 1 símbolo.';
        }
        if ($pass !== $pass2) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
        if (!in_array($rolElegido, $config['roles_autoregistro'], true)) {
            $errores[] = 'Rol no permitido.';
        }

        if (!$errores) {
            try {
                $stRol = db()->prepare('SELECT id_rol FROM roles WHERE nombre_rol = :n');
                $stRol->execute([':n' => $rolElegido]);
                $idRol = $stRol->fetchColumn();

                if ($idRol === false) {
                    $errores[] = 'Rol no permitido.';
                } else {
                    $stCorreo = db()->prepare('SELECT 1 FROM usuarios WHERE correo = :c LIMIT 1');
                    $stCorreo->execute([':c' => $correo]);

                    if ($stCorreo->fetchColumn() !== false) {
                        $errores[] = 'Ese correo ya está registrado.';
                    } else {
                        $codigo = generar_codigo_verificacion();
                        $expMin = (int)($config['correo']['minutos_expiracion'] ?? 10);
                        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

                        $_SESSION['pendiente_registro'] = [
                            'nombre'        => $nombre,
                            'correo'        => $correo,
                            'password_hash' => $hash,
                            'rol'           => $rolElegido,
                            'codigo'        => $codigo,
                            'expira'        => time() + ($expMin * 60),
                            'intentos'      => 0,
                        ];

                        if (enviar_codigo_correo($correo, 'Código de verificación de cuenta', $codigo, 'crear tu cuenta')) {
                            bitacora(null, 'CODIGO_REGISTRO_ENVIADO', $correo);
                            flash("Enviamos un código de 6 dígitos a {$correo}. Ingrésalo para activar tu cuenta.");
                            redirigir('registro.php');
                        } else {
                            unset($_SESSION['pendiente_registro']);
                            $errores[] = 'No se pudo enviar el código de verificación a tu correo. Inténtalo más tarde.';
                        }
                    }
                }
            } catch (PDOException $ex) {
                error_log('Error al preparar registro: ' . $ex->getMessage());
                $errores[] = 'No se pudo completar el registro. Inténtalo más tarde.';
            }
        }
    }
}

$pendienteActivo = !empty($_SESSION['pendiente_registro']) && is_array($_SESSION['pendiente_registro']);
$correoPendiente = $pendienteActivo ? (string)($_SESSION['pendiente_registro']['correo'] ?? '') : '';
$mensajeFlash = flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro · Biblioteca</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="auth">
<div class="auth-caja">
    <aside class="auth-lado">
        <div class="marca"><img src="assets/img/logo.svg" alt="" width="42" height="42"> Biblioteca</div>
        <div class="texto-lado">
            <h2>Crea tu cuenta</h2>
            <p>Regístrate como alumno o profesor para consultar el catálogo de la biblioteca.</p>
        </div>
        <img class="ilustracion" src="assets/img/libros.svg" alt="" width="300" height="178">
    </aside>
    <main class="auth-form">
        <?php if ($pendienteActivo): ?>
            <h1>Verificar correo</h1>
            <p class="sub">Ingresa el código de 6 dígitos enviado a <strong><?= e($correoPendiente) ?></strong>.</p>

            <?php if ($mensajeFlash): ?>
                <p class="alerta ok"><?= e($mensajeFlash) ?></p>
            <?php endif; ?>
            <?php foreach ($errores as $err): ?>
                <p class="alerta error"><?= e($err) ?></p>
            <?php endforeach; ?>

            <form method="post" autocomplete="off" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="verificar_codigo">

                <label for="codigo_verificacion">Código de verificación</label>
                <div class="campo">
                    <input id="codigo_verificacion" name="codigo_verificacion" class="codigo-verificacion" type="text" inputmode="numeric" maxlength="6" pattern="\d{6}" required autofocus placeholder="000000">
                </div>

                <button type="submit">Confirmar y crear cuenta</button>
            </form>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="reenviar_codigo">
                <button type="submit" class="secundario">Reenviar código</button>
            </form>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="cancelar_verificacion">
                <button type="submit" class="boton-enlace">Cambiar datos o correo</button>
            </form>
        <?php else: ?>
            <h1>Crear cuenta</h1>
            <p class="sub">Completa tus datos para registrarte.</p>

            <?php if ($mensajeFlash): ?>
                <p class="alerta ok"><?= e($mensajeFlash) ?></p>
            <?php endif; ?>
            <?php foreach ($errores as $err): ?>
                <p class="alerta error"><?= e($err) ?></p>
            <?php endforeach; ?>

            <div id="errores-cliente" class="alerta error" hidden></div>

            <form method="post" autocomplete="off" novalidate data-captcha-form="registro">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="crear">

                <label for="nombre">Nombre</label>
                <div class="campo">
                    <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
                    <input id="nombre" name="nombre" type="text" maxlength="100" required placeholder="Tu nombre completo" value="<?= e($nombre) ?>">
                </div>

                <label for="correo">Correo</label>
                <div class="campo">
                    <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    <input id="correo" name="correo" type="email" maxlength="254" required placeholder="nombre@correo.com" value="<?= e($correo) ?>">
                </div>

                <label for="rol">Rol</label>
                <div class="campo">
                    <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M14 10h4M14 14h4M6 17c.5-2 5.5-2 6 0"/></svg>
                    <select id="rol" name="rol" required>
                        <option value="">Selecciona…</option>
                        <?php foreach ($config['roles_autoregistro'] as $r): ?>
                            <option value="<?= e($r) ?>" <?= $r === $rolElegido ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label for="password">Contraseña</label>
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

                <label for="password2">Confirmar contraseña</label>
                <div class="campo con-ojo">
                    <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                    <input id="password2" name="password2" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
                    <button type="button" class="ojo" data-objetivo="password2" aria-label="Mostrar contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
                <p id="coincidencia-password" class="estado-coincidencia" aria-live="polite" hidden></p>

                <button type="submit">Registrarme</button>
            </form>
        <?php endif; ?>

        <p class="pie">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
    </main>
</div>
<?php require __DIR__ . '/captcha_modal.php'; ?>
<script src="assets/js/login.js"></script>
<script src="assets/js/captcha_modal.js"></script>
<script src="assets/js/registro_password.js"></script>
</body>
</html>