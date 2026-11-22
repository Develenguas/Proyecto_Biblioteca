<?php
declare(strict_types=1);
ini_set('display_errors', '0'); // un aviso de PHP dentro de la imagen la dejaría ilegible
ob_start();
require __DIR__ . '/seguridad.php';

$codigo = captcha_generar();

// Plan B: si el PHP no tiene la extensión GD, se entrega el captcha como SVG.
if (!function_exists('imagecreatetruecolor')) {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="60" viewBox="0 0 240 60">'
         . '<rect width="240" height="60" fill="#f5f7fa"/>';
    $x = 22;
    foreach (str_split($codigo) as $caracter) {
        $y = random_int(36, 46);
        $giro = random_int(-22, 22);
        $gris = random_int(20, 110);
        $svg .= sprintf(
            '<text x="%d" y="%d" font-size="34" font-weight="bold" font-family="monospace" '
            . 'fill="rgb(%d,%d,%d)" transform="rotate(%d %d %d)">%s</text>',
            $x, $y, $gris, $gris, $gris + 20, $giro, $x, $y, $caracter
        );
        $x += 40;
    }
    for ($i = 0; $i < 7; $i++) {
        $svg .= sprintf(
            '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="rgb(%d,%d,%d)" stroke-width="1.5"/>',
            random_int(0, 240), random_int(0, 60), random_int(0, 240), random_int(0, 60),
            $g = random_int(90, 190), $g, $g
        );
    }
    $svg .= '</svg>';
    ob_end_clean();
    header('Content-Type: image/svg+xml');
    echo $svg;
    exit;
}

// Se dibuja pequeño y se amplía 2x para que el texto quede deformado.
$chico = imagecreatetruecolor(120, 30);
imagefilledrectangle($chico, 0, 0, 120, 30, imagecolorallocate($chico, 245, 247, 250));
$x = 12;
foreach (str_split($codigo) as $caracter) {
    $color = imagecolorallocate($chico, random_int(20, 110), random_int(20, 110), random_int(20, 110));
    imagestring($chico, 5, $x + random_int(-1, 1), random_int(5, 10), $caracter, $color);
    $x += 20;
}

$img = imagecreatetruecolor(240, 60);
imagecopyresampled($img, $chico, 0, 0, 0, 0, 240, 60, 120, 30);

// Ruido encima del texto
for ($i = 0; $i < 7; $i++) {
    $c = imagecolorallocate($img, random_int(90, 190), random_int(90, 190), random_int(90, 190));
    imageline($img, random_int(0, 240), random_int(0, 60), random_int(0, 240), random_int(0, 60), $c);
}
for ($i = 0; $i < 250; $i++) {
    $c = imagecolorallocate($img, random_int(100, 200), random_int(100, 200), random_int(100, 200));
    imagesetpixel($img, random_int(0, 239), random_int(0, 59), $c);
}

ob_end_clean(); // descarta cualquier salida accidental antes de la imagen
header('Content-Type: image/png');
imagepng($img);
