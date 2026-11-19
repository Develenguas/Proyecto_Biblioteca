<?php
// Configuración de la aplicación. NO subir este archivo a repositorios públicos.
return [
    
    'db' => [
    'host' => '127.0.0.1',     // ver el punto 2
    'port' => 3307,            // el que use esa MariaDB
    'name' => 'equipo_4',
    'user' => 'usuario_equipo_4',
    'pass' => 'V9#mQ7!xR2@kL8$pT5&z',
    ],
    'max_intentos'      => 5,    // intentos fallidos antes de bloquear
    'minutos_bloqueo'   => 15,   // duración del bloqueo
    'sesion_minutos'    => 30,   // inactividad máxima de la sesión
    // Roles que una persona puede elegir al registrarse por sí misma.
    // 'administrador' NUNCA debe estar aquí: lo asigna otro administrador.
    'roles_autoregistro' => ['alumno', 'profesor'],
];
