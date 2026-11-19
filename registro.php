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
        if (strlen($pass) < 10 || strlen($pass) > 72) {
            $errores[] = 'La contraseña debe tener entre 10 y 72 caracteres.';
        } elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
            $errores[] = 'La contraseña debe incluir letras y números.';
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
    <title>Registro</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="tarjeta">
    <h1>Crear cuenta</h1>

    <?php foreach ($errores as $err): ?>
        <p class="alerta error"><?= e($err) ?></p>
    <?php endforeach; ?>

    <div id="errores-cliente" class="alerta error" hidden></div>

    <form method="post" autocomplete="off" novalidate data-captcha-form="registro">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" type="text" maxlength="100" required value="<?= e($nombre) ?>">

        <label for="correo">Correo</label>
        <input id="correo" name="correo" type="email" maxlength="254" required value="<?= e($correo) ?>">

        <label for="rol">Rol</label>
        <select id="rol" name="rol" required>
            <option value="">Selecciona…</option>
            <?php foreach ($config['roles_autoregistro'] as $r): ?>
                <option value="<?= e($r) ?>" <?= $r === $rolElegido ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" minlength="10" maxlength="72" required autocomplete="new-password">
        <small>Mínimo 10 caracteres, con letras y números.</small>

        <label for="password2">Confirmar contraseña</label>
        <input id="password2" name="password2" type="password" minlength="10" maxlength="72" required autocomplete="new-password">


        <button type="submit">Registrarme</button>
    </form>

    <p class="pie">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
</main>
<?php require __DIR__ . '/captcha_modal.php'; ?>
<script src="captcha_modal.js"></script>
</body>
</html>
