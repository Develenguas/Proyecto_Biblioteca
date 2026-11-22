<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

// ---------- Sesión segura ----------
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('BIBSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ---------- Cabeceras de seguridad ----------
header("Content-Security-Policy: default-src 'self'; style-src 'self'; frame-ancestors 'none'; form-action 'self'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

// ---------- Utilidades ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $config;
        $c = $config['db'];
        try {
            $pdo = new PDO(
                "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
                $c['user'],
                $c['pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Error de conexión BD: ' . $e->getMessage());
            http_response_code(500);
            exit('Error interno del servidor.');
        }
    }
    return $pdo;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(): bool
{
    $enviado = $_POST['csrf'] ?? '';
    return is_string($enviado)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $enviado);
}

function ip_binaria(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $bin = @inet_pton($ip);
    return $bin === false ? null : $bin;
}

function bitacora(?int $idUsuario, string $evento, ?string $detalle = null): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO bitacora_eventos (id_usuario, evento, detalle, direccion_ip)
             VALUES (:u, :e, :d, :ip)'
        );
        $st->bindValue(':u', $idUsuario, $idUsuario === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':e', substr($evento, 0, 50));
        $st->bindValue(':d', $detalle === null ? null : substr($detalle, 0, 255));
        $st->bindValue(':ip', ip_binaria(), ip_binaria() === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $st->execute();
    } catch (PDOException $e) {
        error_log('Error en bitácora: ' . $e->getMessage());
    }
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function requiere_sesion(): void
{
    global $config;
    $limite = $config['sesion_minutos'] * 60;
    if (empty($_SESSION['id_usuario'])) {
        redirigir('login.php');
    }
    if (time() - ($_SESSION['ultima_actividad'] ?? 0) > $limite) {
        cerrar_sesion();
        session_start();
        flash('Tu sesión expiró por inactividad.');
        redirigir('login.php');
    }
    $_SESSION['ultima_actividad'] = time();
}

// ---------- Captcha (imagen generada con GD, sin servicios externos) ----------
function captcha_generar(): string
{
    // Sin caracteres ambiguos (0/O, 1/I/L)
    $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $codigo = '';
    for ($i = 0; $i < 5; $i++) {
        $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    $_SESSION['captcha'] = ['codigo' => $codigo, 'hora' => time()];
    return $codigo;
}

function captcha_valido(string $entrada): bool
{
    $dato = $_SESSION['captcha'] ?? null;
    unset($_SESSION['captcha']); // un solo uso: cada intento exige un código nuevo
    if (!is_array($dato) || time() - $dato['hora'] > 300) {
        return false;
    }
    return hash_equals($dato['codigo'], strtoupper(trim($entrada)));
}

// El captcha se resuelve en una ventana emergente (verificar_captcha.php).
// Si es correcto, la sesión guarda una "aprobación" válida 2 minutos y de un solo uso,
// que login.php y registro.php exigen antes de procesar el formulario.
function captcha_aprobado(): bool
{
    $t = $_SESSION['captcha_aprobado'] ?? null;
    unset($_SESSION['captcha_aprobado']);
    return is_int($t) && time() - $t <= 120;
}

// Correo con @, dominio y extensión (.com, .mx, .edu.mx, etc.)
function correo_valido(string $correo): bool
{
    return strlen($correo) <= 254
        && (bool)preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/', $correo)
        && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------- Códigos de verificación por correo ----------
function generar_codigo_verificacion(): string
{
    return (string)random_int(100000, 999999);
}

function enviar_codigo_correo(string $destinatario, string $asunto, string $codigo, string $proposito): bool
{
    global $config;
    $cfgCorreo = $config['correo'] ?? [];
    $remitente = (string)($cfgCorreo['remitente'] ?? 'no-reply@192.168.20.132');
    $nombreRem = (string)($cfgCorreo['nombre_remitente'] ?? 'Biblioteca');
    $minutos   = (int)($cfgCorreo['minutos_expiracion'] ?? 10);
    $smtp      = $cfgCorreo['smtp'] ?? [];

    $cuerpo = "Hola,\r\n\r\n"
        . "Tu código de verificación para {$proposito} en el Sistema de Gestión Bibliotecaria es:\r\n\r\n"
        . "    {$codigo}\r\n\r\n"
        . "Este código vence en {$minutos} minutos.\r\n"
        . "Si tú no realizaste esta solicitud, ignora este mensaje.\r\n";

    $asuntoCodificado = mb_encode_mimeheader($asunto, 'UTF-8');
    $remitenteCodificado = mb_encode_mimeheader($nombreRem, 'UTF-8') . " <{$remitente}>";

    if (!empty($smtp['host'])) {
        $host = (string)$smtp['host'];
        $port = (int)($smtp['port'] ?? 587);
        $user = (string)($smtp['user'] ?? '');
        $pass = (string)($smtp['pass'] ?? '');
        $sec  = strtolower((string)($smtp['security'] ?? 'tls'));

        $transport = ($sec === 'ssl') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($transport, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            error_log("SMTP conexión fallida a {$transport}: [{$errno}] {$errstr}");
            return false;
        }

        stream_set_timeout($fp, 15);

        $leer = static function () use ($fp): string {
            $resp = '';
            while (($linea = fgets($fp, 515)) !== false) {
                $resp .= $linea;
                if (strlen($linea) >= 4 && $linea[3] === ' ') {
                    break;
                }
            }
            return $resp;
        };

        $comando = static function (string $cmd, array $codigosEsperados) use ($fp, $leer): bool {
            fwrite($fp, $cmd . "\r\n");
            $resp = $leer();
            $codigoResp = substr($resp, 0, 3);
            if (!in_array($codigoResp, $codigosEsperados, true)) {
                error_log("SMTP error en [{$cmd}]: {$resp}");
                return false;
            }
            return true;
        };

        $saludo = $leer();
        if (substr($saludo, 0, 3) !== '220') {
            error_log("SMTP saludo inválido: {$saludo}");
            fclose($fp);
            return false;
        }

        $ehloHost = 'localhost';
        if (!$comando("EHLO {$ehloHost}", ['250'])) {
            fclose($fp);
            return false;
        }

        if ($sec === 'tls') {
            if (!$comando('STARTTLS', ['220'])) {
                fclose($fp);
                return false;
            }
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log('SMTP error al activar TLS');
                fclose($fp);
                return false;
            }
            if (!$comando("EHLO {$ehloHost}", ['250'])) {
                fclose($fp);
                return false;
            }
        }

        if ($user !== '' || $pass !== '') {
            if (!$comando('AUTH LOGIN', ['334'])) {
                fclose($fp);
                return false;
            }
            if (!$comando(base64_encode($user), ['334'])) {
                fclose($fp);
                return false;
            }
            if (!$comando(base64_encode($pass), ['235'])) {
                error_log('SMTP autenticación fallida: revisa usuario y contraseña/clave de aplicación');
                fclose($fp);
                return false;
            }
        }

        if (!$comando("MAIL FROM:<{$remitente}>", ['250']) || !$comando("RCPT TO:<{$destinatario}>", ['250', '251'])) {
            fclose($fp);
            return false;
        }

        if (!$comando('DATA', ['354'])) {
            fclose($fp);
            return false;
        }

        $cuerpoEscapado = preg_replace('/^\./m', '..', $cuerpo) ?? $cuerpo;
        $mensajeSMTP = "Date: " . date('r') . "\r\n"
            . "From: {$remitenteCodificado}\r\n"
            . "To: <{$destinatario}>\r\n"
            . "Subject: {$asuntoCodificado}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "\r\n"
            . $cuerpoEscapado . "\r\n.";

        fwrite($fp, $mensajeSMTP . "\r\n");
        $respData = $leer();
        $comando('QUIT', ['221', '250']);
        fclose($fp);

        if (substr($respData, 0, 3) !== '250') {
            error_log("SMTP error al finalizar DATA: {$respData}");
            return false;
        }

        return true;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $remitenteCodificado,
        'Reply-To: ' . $remitente,
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $enviado = mail($destinatario, $asuntoCodificado, $cuerpo, implode("\r\n", $headers));
    if (!$enviado) {
        error_log("No se pudo enviar correo de verificación ({$proposito}) a {$destinatario}");
    }
    return $enviado;
}