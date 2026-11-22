<?php
// Configuración de la aplicación. NO subir este archivo a repositorios públicos.
return [

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3307,
        'name' => 'equipo_4',
        'user' => 'usuario_equipo_4',
        'pass' => 'V9#mQ7!xR2@kL8$pT5&z',
    ],
    'max_intentos'       => 5,    // intentos fallidos antes de bloquear
    'minutos_bloqueo'    => 15,   // duración del bloqueo
    'sesion_minutos'     => 30,   // inactividad máxima de la sesión
    'roles_autoregistro' => ['alumno', 'profesor'],

   
// Configuración para códigos de verificación por correo
'correo' => [
    'remitente'           => 'biblioteca.proyecto4@gmail.com',
    'nombre_remitente'    => 'Sistema de Biblioteca',
    'minutos_expiracion'  => 10,
    'max_intentos_codigo' => 5,

    'smtp' => [
        'host'     => 'smtp.gmail.com',
        'port'     => 587,
        'user'     => 'biblioteca.proyecto4@gmail.com',
        'pass'     => 'mlttvkejhtavfxgi',
        'security' => 'tls',
    ],
],

];