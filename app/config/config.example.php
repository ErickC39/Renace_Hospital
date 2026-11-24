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
    'mail' => [
        // Cómo se envían los correos (código para recuperar la clave):
        //   'archivo' = no envía, los escribe en un archivo temporal (para probar en local)
        //   'brevo'   = los envía por la API de Brevo sobre HTTPS (lo que funciona en Render)
        'driver' => 'archivo',
        // Correo verificado en Brevo como remitente (por ejemplo, el Gmail del hospital).
        'remitente' => '',
        'nombre_remitente' => 'RENACE Hospital',
        // Clave de la API de Brevo. Nunca aquí: va en la variable de entorno MAIL_API_KEY.
        'api_key' => '',
    ],
];
