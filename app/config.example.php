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
    // Mercado Pago la usa para las back_urls y NO acepta localhost ni una IP:
    // necesita un dominio con DNS. Ver docs/MERCADOPAGO.md.
    'base_url' => 'https://rastrofitness.com',

    /* ------------------------------------------------------------------
       PANEL DE ADMINISTRACIÓN  (/admin)

       El administrador de arranque vive acá y no en data/users.json a
       propósito: el panel escribe archivos en el servidor, y un hash de
       administrador versionado en el repo es un hash que ya está en las
       máquinas de todo el equipo y en el historial de git para siempre.

       Sin estos tres valores el panel no deja entrar a nadie y muestra
       cómo configurarlo. Es deliberado: un panel que escribe archivos y
       arranca con una clave por defecto es un panel abierto.

       El hash se genera en la máquina donde va a correr:

           php -r 'echo password_hash("la-clave-real", PASSWORD_DEFAULT), PHP_EOL;'

       Copiar la salida entera, incluido el $2y$12$ del principio. Ojo con
       las comillas: en un .php el string va entre comillas SIMPLES, si no
       PHP se come los $ y el hash deja de coincidir con la clave.

       Cuando el backend tenga usuarios de verdad, cualquier fila de
       data/users.json con rol "admin" también entra por acá, y este
       bloque se puede vaciar.
       ------------------------------------------------------------------ */

    // Mail con el que se entra al panel.
    'panel_email' => '',

    // Salida de password_hash(), entre comillas simples.
    'panel_password_hash' => '',

    // Cómo se lo saluda en el panel. Cosmético.
    'panel_nombre' => 'Administración',

    /* ------------------------------------------------------------------
       Mercado Pago

       Las credenciales salen del panel de desarrolladores:
       https://www.mercadopago.com.ar/developers/panel
       -> Tus integraciones -> (tu aplicación) -> Credenciales

       Cada aplicación tiene DOS juegos de credenciales, de prueba y de
       producción, y los dos empiezan con APP_USR. No se distinguen por el
       prefijo: se distinguen por la solapa de la que se copiaron. Es fácil
       mezclarlas y cobrar de verdad creyendo que se está probando, así que
       'mp_modo' está para dejar escrito con cuál de las dos se cargó este
       archivo. El sitio lo usa para mostrar el cartel de ambiente de prueba.

       El paso a paso completo —incluido cómo crear la aplicación, los
       usuarios de prueba y cómo dar de alta el webhook— está en
       docs/MERCADOPAGO.md.
       ------------------------------------------------------------------ */

    // 'test' o 'produccion'. Tiene que coincidir con las credenciales de abajo.
    'mp_modo' => 'test',

    // Access Token. Es la credencial privada: NUNCA sale del servidor, no
    // se imprime en una vista y no viaja al navegador.
    'mp_access_token' => '',

    // Public Key. Es pública. Hoy el sitio no la usa —Checkout Pro redirige
    // en vez de embeber— y queda cargada para el día que se pruebe Bricks.
    'mp_public_key' => '',

    // Clave secreta de la firma de los webhooks. Se genera al configurar la
    // notificación: Tus integraciones -> Webhooks -> Configurar notificación
    // -> "Revelar clave secreta". Sin esto, el endpoint /webhooks/mercadopago
    // rechaza TODAS las notificaciones con un 401, que es lo correcto: sin
    // firma no hay forma de saber si el aviso lo mandó Mercado Pago.
    'mp_webhook_secret' => '',

    // URL pública del webhook. Se deja vacía en el servidor de verdad: se
    // arma sola con base_url. Sólo se completa cuando se prueba con un túnel
    // (ngrok, cloudflared) contra una máquina de escritorio, porque ahí la
    // URL pública no es la del sitio.
    'mp_notification_url' => '',
];
