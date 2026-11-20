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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        // bcrypt solo usa los primeros 72 bytes, por eso ese es el máximo.
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
        // El rol se valida en el servidor contra una lista blanca.
        if (!in_array($rolElegido, $config['roles_autoregistro'], true)) {
            $errores[] = 'Rol no permitido.';
        }

        if (!$errores) {
            try {
                $st = db()->prepare('SELECT id_rol FROM roles WHERE nombre_rol = :n');
                $st->execute([':n' => $rolElegido]);
                $idRol = $st->fetchColumn();

                if ($idRol === false) {
                    $errores[] = 'Rol no permitido.';
                } else {
                    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $ins = db()->prepare(
                        'INSERT INTO usuarios (nombre, correo, password_hash, id_rol)
                         VALUES (:n, :c, :h, :r)'
                    );
                    $ins->execute([':n' => $nombre, ':c' => $correo, ':h' => $hash, ':r' => (int)$idRol]);
                    bitacora((int)db()->lastInsertId(), 'REGISTRO', 'Rol: ' . $rolElegido);
                    flash('Cuenta creada. Ya puedes iniciar sesión.');
                    redirigir('login.php');
                }
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $errores[] = 'Ese correo ya está registrado.';
                } else {
                    error_log('Error en registro: ' . $ex->getMessage());
                    $errores[] = 'No se pudo completar el registro. Inténtalo más tarde.';
                }
            }
        }
    }
}
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
        <h1>Crear cuenta</h1>
        <p class="sub">Completa tus datos para registrarte.</p>

        <?php foreach ($errores as $err): ?>
            <p class="alerta error"><?= e($err) ?></p>
        <?php endforeach; ?>

        <div id="errores-cliente" class="alerta error" hidden></div>

        <form method="post" autocomplete="off" novalidate data-captcha-form="registro">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

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

        <p class="pie">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
    </main>
</div>
<?php require __DIR__ . '/captcha_modal.php'; ?>
<script src="assets/js/login.js"></script>
<script src="assets/js/captcha_modal.js"></script>
<script src="assets/js/registro_password.js"></script>
</body>
</html>