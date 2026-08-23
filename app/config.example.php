<?php
/**
 * Plantilla de configuración de Rastro Fitness.
 *
 * Copiar como `app/config.php` y completar con los valores reales.
 *
 *   IMPORTANTE
 *   `app/config.php` está en .gitignore y NO se sincroniza por FTP.
 *   Vive únicamente en el servidor y en la máquina de cada dev.
 *   Nunca subir credenciales reales al repositorio, a un issue ni a Notion.
 *
 * Todos los valores salen de hPanel de Hostinger:
 *   Bases de datos -> Administración de bases de datos MySQL
 */

return [
    // Host de MySQL. En Hostinger es un servidor con nombre propio,
    // del estilo srvNNNN.hstgr.io. Figura junto a la base en hPanel.
    // Desde el propio servidor también funciona 'localhost'.
    'db_host' => '',

    // Nombre de la base. Hostinger le antepone el id de la cuenta:
    // uNNNNNNNNN_algo
    'db_name' => '',

    // Usuario de la base. Mismo prefijo: uNNNNNNNNN_algo
    'db_user' => '',

    // Contraseña del usuario de la base.
    'db_pass' => '',

    'db_charset' => 'utf8mb4',

    // Entorno: 'local' muestra los errores en pantalla, 'produccion' los oculta.
    'entorno' => 'local',

    // URL base del sitio, sin barra final.
    'base_url' => 'https://darkorange-buffalo-311255.hostingersite.com',
];
