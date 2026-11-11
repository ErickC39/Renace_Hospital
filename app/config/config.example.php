<?php

return [
    'app' => [
        'name'  => 'Hospital UTC',
        'env'   => 'local',
        'debug' => false,
        'session_timeout_min' => 30,
        // true solo detrás de un proxy propio (Render): permite creer las cabeceras X-Forwarded-*
        'trust_proxy' => false,
    ],
    'db' => [
        // Oracle de la máquina virtual (OCI8)
        'username' => 'APP_HOSPITAL', // usuario del aplicativo; nunca HOSPITAL ni SYSTEM
        'password' => '',
        'dsn'      => '//IP_DE_LA_VM:1521/HOSPDB', // //host:puerto/servicio
        'charset'  => 'AL32UTF8',
        // sqlnet.ora propio del aplicativo (evita ORA-12638 en Windows)
        'tns_admin' => __DIR__ . '/oracle',
        // Esquema dueño de las tablas y paquetes (el aplicativo trabaja sobre él)
        'schema'   => 'HOSPITAL',
    ],
];
