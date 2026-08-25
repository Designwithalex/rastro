<?php
/**
 * helpers.php — funciones de presentación de Rastro Fitness.
 *
 * Acá vive todo lo que una vista necesita para formatear datos: escape,
 * moneda, rutas y —sobre todo— el cálculo del precio con descuento.
 *
 * Regla del proyecto: el descuento por transferencia se calcula en
 * `precio_con_descuento()` y en ningún otro lado. Ninguna vista multiplica
 * un precio a mano. Si mañana el negocio cambia la regla (topes, descuento
 * por categoría, cuotas), se cambia una sola función.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------
   Salida
   ------------------------------------------------------------------ */

/**
 * Escape de salida. Se usa en TODA la salida de las vistas, sin excepción.
 * Acepta null para no tener que preguntar por cada campo opcional.
 */
function e(mixed $v): string
{
    if ($v === null || is_bool($v)) {
        return '';
    }

    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape para atributos de JavaScript embebido (data-*, JSON en línea).
 */
function e_json(mixed $v): string
{
    return e(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/* ------------------------------------------------------------------
   Query string

   TODA vista que lea un parámetro de la URL lo hace con estas tres
   funciones. Nunca `$_GET['x']` directo: `?q[]=a` llega como array y
   cualquier cast a string tira un warning y ensucia la página.
   ------------------------------------------------------------------ */

/**
 * Parámetro de texto. Devuelve el valor por defecto si no vino, si vino
 * vacío, si vino como array o si no está en la lista de permitidos.
 *
 * @param string[] $permitidos Lista blanca. Vacía significa "cualquier texto".
 */
function param(string $nombre, string $defecto = '', array $permitidos = []): string
{
    $valor = $_GET[$nombre] ?? null;

    if (!is_string($valor)) {
        return $defecto;
    }

    $valor = trim($valor);

    if ($valor === '') {
        return $defecto;
    }

    if ($permitidos !== [] && !in_array($valor, $permitidos, true)) {
        return $defecto;
    }

    return $valor;
}

/**
 * Parámetro numérico entero, con recorte opcional a un rango.
 * Sirve para `pagina`, `precio_min` y `precio_max`.
 */
function param_int(string $nombre, ?int $defecto = null, ?int $min = null, ?int $max = null): ?int
{
    $valor = $_GET[$nombre] ?? null;

    if (!is_string($valor) || !preg_match('/^-?\d{1,12}$/', trim($valor))) {
        return $defecto;
    }

    $numero = (int) trim($valor);

    if ($min !== null) {
        $numero = max($min, $numero);
    }

    if ($max !== null) {
        $numero = min($max, $numero);
    }

    return $numero;
}

/**
 * Parámetro de bandera: `?destacado=1`, `?en_stock=on`.
 */
function param_bool(string $nombre): bool
{
    $valor = $_GET[$nombre] ?? null;

    return is_string($valor) && in_array(strtolower(trim($valor)), ['1', 'true', 'on', 'si'], true);
}

/* ------------------------------------------------------------------
   Números y moneda
   ------------------------------------------------------------------ */

/**
 * Formato de precio argentino: $189.400. Punto de miles, sin decimales.
 * Los precios del catálogo son enteros en pesos: no hay centavos.
 */
function moneda(int|float|string|null $n): string
{
    $n = (float) $n;

    // El signo va antes del símbolo: -$500, nunca $-500.
    // Aparece en el carrito, donde el ahorro y las notas de crédito son negativos.
    return ($n < 0 ? '-' : '') . '$' . number_format(abs($n), 0, ',', '.');
}

/**
 * Igual que moneda() pero sin el símbolo, para cuando el símbolo va
 * en su propio elemento (por ejemplo el precio grande en degradé plata).
 */
function moneda_sin_simbolo(int|float|string|null $n): string
{
    $n = (float) $n;

    return ($n < 0 ? '-' : '') . number_format(abs($n), 0, ',', '.');
}

/**
 * Convierte a número algo que escribió una persona.
 *
 * En Argentina el separador decimal es la coma, así que quien cargue el
 * descuento en el panel va a escribir "12,5" y no "12.5". Un cast directo a
 * float devuelve 12 sin avisar: medio punto de descuento perdido en silencio
 * sobre todo el catálogo. También se toleran el símbolo de porcentaje y los
 * espacios, que es lo otro que aparece pegado al número.
 *
 * FORMATO ESPERADO EN LOS DATOS (para docs/DATA-CONTRACT.md):
 * el campo canónico es un número JSON con punto decimal —`"descuento_pct": 12.5`—
 * y esta función existe para aguantar lo que hoy llega como texto desde el
 * panel. El día que el panel valide en el alta, se puede simplificar.
 */
function numero_decimal(mixed $valor): float
{
    if (is_int($valor) || is_float($valor)) {
        return (float) $valor;
    }

    if (!is_string($valor)) {
        return 0.0;
    }

    $limpio = str_replace([' ', '%', "\u{00A0}"], '', trim($valor));

    // "12,5" -> "12.5". Si vienen los dos separadores ("1.234,5"), el punto
    // es de miles y la coma es la decimal.
    if (str_contains($limpio, ',')) {
        $limpio = str_replace('.', '', $limpio);
        $limpio = str_replace(',', '.', $limpio);
    }

    return is_numeric($limpio) ? (float) $limpio : 0.0;
}

/**
 * Formatea un porcentaje sin decimales inútiles: 15 -> "15", 12.5 -> "12,5".
 */
function porcentaje(int|float $n): string
{
    return rtrim(rtrim(number_format((float) $n, 1, ',', ''), '0'), ',');
}

/**
 * Peso en kilos con coma decimal: 20 -> "20 kg", 2.5 -> "2,5 kg".
 */
function peso_kg(int|float|null $kg): string
{
    if ($kg === null) {
        return '—';
    }

    return porcentaje((float) $kg) . ' kg';
}

/* ------------------------------------------------------------------
   Precio: la única fuente de verdad
   ------------------------------------------------------------------ */

/**
 * Calcula el bloque de precio de un producto.
 *
 * El precio publicado es el precio pagando con Mercado Pago. Sobre ese
 * precio se aplica el descuento por transferencia o efectivo, que sale
 * de `settings.descuento_transferencia_pct` salvo que el producto traiga
 * su propio `descuento_pct` (override por producto, para promos puntuales).
 *
 * @param array $producto Producto tal como lo devuelve el repository.
 * @param array $settings Configuración tal como la devuelve repo_settings().
 *
 * @return array{
 *     publicado:int,
 *     con_descuento:int,
 *     porcentaje:float,
 *     ahorro:int,
 *     tiene_descuento:bool
 * }
 */
function precio_con_descuento(array $producto, array $settings): array
{
    $publicado = (int) round((float) ($producto['precio_lista'] ?? 0));

    // El override del producto manda; si no hay, vale el global.
    $pct = $producto['descuento_pct'] ?? null;
    if ($pct === null || $pct === '') {
        $pct = $settings['descuento_transferencia_pct'] ?? 0;
    }

    // Se lee con numero_decimal() porque "12,5" escrito con coma tiene que
    // valer 12,5 y no 12.
    $pct = numero_decimal($pct);

    // Un porcentaje mal cargado en el panel no puede regalar mercadería ni
    // inflar el precio. Fuera de rango NO se recorta al extremo: se ignora.
    // Recortar un 150 mal tipeado a 100 es justamente regalar el producto,
    // que es el error caro; con 0 el precio queda alto y alguien se da cuenta.
    if ($pct <= 0 || $pct >= 100) {
        $pct = 0.0;
    }

    $con_descuento = (int) round($publicado * (100 - $pct) / 100);

    return [
        'publicado'       => $publicado,
        'con_descuento'   => $con_descuento,
        'porcentaje'      => $pct,
        'ahorro'          => $publicado - $con_descuento,
        'tiene_descuento' => $pct > 0 && $publicado > 0,
    ];
}

/**
 * El porcentaje de descuento por transferencia, ya formateado, para las
 * líneas de copy ("15% de descuento pagando por transferencia").
 * Existe para que ninguna vista tenga que leer la clave cruda de settings.
 */
function descuento_global(array $settings): string
{
    return porcentaje(numero_decimal($settings['descuento_transferencia_pct'] ?? 0));
}

/* ------------------------------------------------------------------
   Rutas y assets
   ------------------------------------------------------------------ */

/**
 * URL absoluta dentro del sitio. Respeta el subdirectorio en el que esté
 * montado el front controller, así funciona igual en local y en Hostinger.
 */
function url(string $ruta = '/'): string
{
    $base = defined('RASTRO_BASE') ? RASTRO_BASE : '';

    if ($ruta === '' || $ruta === '/') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base . '/' . ltrim($ruta, '/');
}

/**
 * URL de un archivo de assets, con un sufijo de versión sacado de la fecha
 * de modificación. Evita tener que purgar caché a mano en cada deploy.
 *
 * Las tipografías son la excepción y van SIN versión, a propósito.
 * La referencia canónica de cada fuente es el @font-face de tokens.css, que
 * se genera desde Figma y no puede pedirle el filemtime a PHP. Si el preload
 * llevara ?v= y el @font-face no, serían dos URLs distintas: el navegador
 * bajaría cada fuente dos veces y el preload no se usaría nunca. Sin versión,
 * las dos URLs no pueden divergir.
 *
 * No hace falta invalidarlas: el .htaccess las sirve `immutable` por un año
 * y el contenido de un WOFF2 no cambia. El día que cambie la familia
 * (licencia de Eurostile, PENDIENTES #4) cambia el nombre del archivo.
 */
function asset(string $ruta): string
{
    $relativa = 'assets/' . ltrim($ruta, '/');
    $absoluta = dirname(__DIR__) . '/' . $relativa;

    if (preg_match('/\.woff2?$/i', $relativa)) {
        return url($relativa);
    }

    $version = is_file($absoluta) ? substr((string) filemtime($absoluta), -6) : null;

    return url($relativa) . ($version ? '?v=' . $version : '');
}

/**
 * Ruta que se está mostrando, normalizada y sin barra final.
 * "/" se mantiene como "/".
 */
function ruta_actual(): string
{
    $ruta = defined('RASTRO_RUTA') ? RASTRO_RUTA : '/';
    $ruta = '/' . trim($ruta, '/');

    return $ruta === '/' ? '/' : $ruta;
}

/**
 * Devuelve la clase de estado activo si la ruta corresponde al ítem de
 * navegación actual. Para la home el match es exacto; para el resto,
 * cualquier ruta que cuelgue de ella (/catalogo también marca
 * /catalogo/discos).
 *
 *   <a class="nav__enlace <?= e(activo('/catalogo')) ?>">
 */
function activo(string $ruta, string $clase = 'es-activo'): string
{
    return es_ruta_activa($ruta) ? $clase : '';
}

/**
 * Igual que activo() pero booleano: sirve para la sección entera.
 */
function es_ruta_activa(string $ruta): bool
{
    $ruta   = '/' . trim($ruta, '/');
    $actual = ruta_actual();

    if ($ruta === '/') {
        return $actual === '/';
    }

    return $actual === $ruta || str_starts_with($actual, $ruta . '/');
}

/**
 * Coincidencia exacta con la ruta actual. Es la que decide el
 * aria-current="page": en /catalogo/discos la sección "Catálogo" está
 * activa, pero la página actual es "Discos", y solo una puede llevarlo.
 */
function es_ruta_exacta(string $ruta): bool
{
    return ruta_actual() === '/' . trim($ruta, '/');
}

/* ------------------------------------------------------------------
   Texto
   ------------------------------------------------------------------ */

/**
 * Convierte un texto en slug apto para URL: minúsculas, sin acentos,
 * separado por guiones. "Discos Bumper 20 kg" -> "discos-bumper-20-kg".
 */
function slug(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');

    $texto = strtr($texto, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'º' => '', 'ª' => '',
    ]);

    $texto = preg_replace('/[^a-z0-9]+/u', '-', $texto) ?? '';

    return trim($texto, '-');
}

/**
 * Recorta un texto a una cantidad de caracteres sin cortar palabras.
 */
function recortar(string $texto, int $largo = 120, string $final = '…'): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');

    if (mb_strlen($texto, 'UTF-8') <= $largo) {
        return $texto;
    }

    $corte = mb_substr($texto, 0, $largo, 'UTF-8');
    $ultimo = mb_strrpos($corte, ' ', 0, 'UTF-8');

    return rtrim($ultimo ? mb_substr($corte, 0, $ultimo, 'UTF-8') : $corte, ' ,.;:') . $final;
}

/**
 * Reemplaza los marcadores de un texto editable por el dato vivo.
 *
 * El copy de banners y promos lo carga el cliente desde el panel, y ahí no
 * puede quedar escrito "15%": si mañana el descuento pasa a 12, el banner
 * miente y hay dos fuentes de verdad para el dato más importante del sitio.
 * En vez del número se escribe el marcador:
 *
 *   "{descuento} de descuento pagando por transferencia o efectivo"
 *   "Envío sin cargo desde {envio_gratis}"
 *
 * Se usa strtr y no sprintf a propósito: un "%" suelto escrito por el
 * cliente en el panel no puede romper nada.
 *
 * TODO(backend): el panel tiene que listar estos marcadores al lado del
 * campo de texto, si no nadie se entera de que existen.
 */
function interpolar(string $texto, array $settings): string
{
    return strtr($texto, [
        '{descuento}'    => descuento_global($settings) . '%',
        '{envio_gratis}' => moneda($settings['envio_gratis_desde'] ?? 0),
        '{whatsapp}'     => (string) ($settings['whatsapp'] ?? ''),
    ]);
}

/* ------------------------------------------------------------------
   Contacto
   ------------------------------------------------------------------ */

/**
 * Arma el enlace de WhatsApp a partir de la configuración.
 * El canal mayorista no muestra precios: contacta por acá.
 *
 * @param array       $settings Configuración de repo_settings().
 * @param string|null $mensaje  Mensaje inicial; si es null usa el de settings.
 *
 * @return string|null null si todavía no hay número cargado.
 */
function whatsapp_link(array $settings, ?string $mensaje = null): ?string
{
    $numero = preg_replace('/\D+/', '', (string) ($settings['whatsapp'] ?? '')) ?? '';
    $texto  = $mensaje ?? (string) ($settings['whatsapp_mensaje'] ?? '');

    // Sin número no hay enlace. Devolver "https://wa.me/" mandaría al usuario
    // a la home de WhatsApp, que es peor que no ofrecer el botón: la vista
    // pregunta por null y esconde la pieza.
    if ($numero === '') {
        return null;
    }

    $url = 'https://wa.me/' . $numero;

    if ($texto !== '') {
        $url .= '?text=' . rawurlencode($texto);
    }

    return $url;
}

/**
 * La medida que identifica a un producto en una línea: "20 kg", "2,20 m".
 *
 * Es el tercer dato de la línea técnica que la v2 muestra en cada producto
 * —código, medida y estado— tanto en la card del catálogo como en el
 * mega-menú (CLAUDE.md §5.6). Vive acá y no en cada vista para que las dos
 * digan lo mismo.
 *
 * Casi todo el catálogo se identifica por peso, así que ese es el valor
 * preferido. Los productos que no tienen peso cargado caen en la primera
 * especificación dimensional que exista.
 */
function medida_producto(array $producto): string
{
    if (isset($producto['peso_kg']) && $producto['peso_kg'] !== null && $producto['peso_kg'] !== '') {
        return peso_kg((float) $producto['peso_kg']);
    }

    $preferidas = ['Medidas', 'Largo', 'Alto', 'Diámetro'];

    foreach ($preferidas as $etiqueta) {
        foreach ($producto['especificaciones'] ?? [] as $especificacion) {
            if (($especificacion['label'] ?? '') === $etiqueta && ($especificacion['valor'] ?? '') !== '') {
                return (string) $especificacion['valor'];
            }
        }
    }

    return '—';
}

/**
 * El estado de stock en una palabra, para la línea técnica.
 * No dice cuántas unidades quedan a propósito: eso es información de
 * depósito y en un listado invita a la comparación equivocada.
 */
function estado_stock(array $producto): string
{
    return ((int) ($producto['stock'] ?? 0)) > 0 ? 'En stock' : 'Sin stock';
}

/**
 * Versión WebP de una imagen, para el <source> de un <picture>.
 * Devuelve null si todavía no se generó, así la vista cae al original
 * sin romperse.
 */
function imagen_webp(string $ruta): ?string
{
    // Un SVG o un archivo ya en WebP no tienen versión alternativa.
    if (!preg_match('/\.(jpe?g|png)$/i', $ruta)) {
        return null;
    }

    $webp     = preg_replace('/\.(jpe?g|png)$/i', '.webp', $ruta) ?? $ruta;
    $absoluta = dirname(__DIR__) . '/assets/' . ltrim($webp, '/');

    return is_file($absoluta) ? asset($webp) : null;
}

/**
 * Medidas de un SVG, leídas del propio archivo.
 *
 * NO se delega en getimagesize() a propósito: soporta SVG recién desde PHP
 * 8.5, y Hostinger puede estar corriendo 8.1. La misma llamada devolvería
 * 800×800 en la máquina de desarrollo y null en el servidor, con el
 * consiguiente salto de layout que aparece solo en producción. Doce líneas
 * de lectura valen más que un comportamiento que depende del intérprete.
 *
 * Se aceptan únicamente medidas en píxeles. Un `width="100%"` o un
 * `width="10cm"` no dicen cuánto va a ocupar la imagen en la página, así
 * que se ignoran y se cae al viewBox, que da la proporción —que es lo que
 * el navegador necesita para reservar el espacio— aunque no sea el tamaño
 * final.
 *
 * @return array{ancho:int, alto:int}|null
 */
function _medidas_svg(string $absoluta): ?array
{
    // La etiqueta <svg> es lo primero del archivo salvo prólogo o
    // comentario; 4 KB alcanzan de sobra y evitan cargar un mapa entero.
    $cabecera = (string) @file_get_contents($absoluta, false, null, 0, 4096);

    if (!preg_match('/<svg\b[^>]*>/i', $cabecera, $etiqueta)) {
        return null;
    }

    $svg = $etiqueta[0];

    $en_pixeles = static function (string $atributo) use ($svg): ?float {
        if (!preg_match('/\b' . $atributo . '\s*=\s*["\']([^"\']*)["\']/i', $svg, $valor)) {
            return null;
        }

        if (!preg_match('/^\s*([0-9]*\.?[0-9]+)\s*(px)?\s*$/i', $valor[1], $numero)) {
            return null;
        }

        return (float) $numero[1];
    };

    $ancho = $en_pixeles('width');
    $alto  = $en_pixeles('height');

    if ($ancho === null || $alto === null) {
        // min-x min-y ancho alto
        if (preg_match('/\bviewBox\s*=\s*["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $svg, $caja)) {
            $ancho = (float) $caja[1];
            $alto  = (float) $caja[2];
        }
    }

    if ($ancho === null || $alto === null || $ancho <= 0 || $alto <= 0) {
        return null;
    }

    return ['ancho' => (int) round($ancho), 'alto' => (int) round($alto)];
}

/**
 * Medidas reales de una imagen: ['ancho' => int, 'alto' => int].
 *
 * Existe para no escribir width y height a mano en las vistas. Las fotos
 * que hoy están en el repo miden todas 928×1152, pero las que suba el
 * cliente —la de los fundadores, las de las obras, los logos de las marcas
 * oficiales— van a medir cualquier cosa, y un width/height que miente
 * reserva un espacio que no es el de la imagen: la página salta cuando
 * termina de cargar, que es justo lo que esos dos atributos existen para
 * evitar.
 *
 * Los SVG se leen aparte, con _medidas_svg(), y por eso también devuelven
 * medidas. La alternativa era dejárselo a getimagesize(), que soporta SVG
 * recién desde PHP 8.5: en local daría 800×800 y en Hostinger null, y el
 * marcador `sin-foto.svg` de los cuatro productos sin foto entra en la card
 * de producto, que todavía no está escrita. Un comportamiento que cambia
 * con la versión del intérprete es una trampa para el que venga después.
 *
 * Devuelve null cuando no se puede saber: el archivo no está, el formato no
 * se reconoce, o el SVG declara su tamaño en porcentaje. Ahí la vista omite
 * los atributos en lugar de inventarlos.
 *
 * TODO(backend): con la base real conviene guardar ancho y alto como
 * columnas al subir la foto y devolverlos en el propio producto, en vez de
 * abrir el archivo en cada request.
 *
 * @return array{ancho:int, alto:int}|null
 */
function imagen_medidas(string $ruta): ?array
{
    static $cache = [];

    if (array_key_exists($ruta, $cache)) {
        return $cache[$ruta];
    }

    $absoluta = dirname(__DIR__) . '/assets/' . ltrim($ruta, '/');

    if (!is_file($absoluta)) {
        return $cache[$ruta] = null;
    }

    if (preg_match('/\.svg$/i', $ruta)) {
        return $cache[$ruta] = _medidas_svg($absoluta);
    }

    $medidas = @getimagesize($absoluta);

    if ($medidas === false || (int) $medidas[0] <= 0 || (int) $medidas[1] <= 0) {
        return $cache[$ruta] = null;
    }

    return $cache[$ruta] = ['ancho' => (int) $medidas[0], 'alto' => (int) $medidas[1]];
}

/**
 * Miniatura de 96 px de una foto de producto, en WebP.
 *
 * Existe por el mega-menú: muestra hasta ocho fotos por categoría a 48 px de
 * lado, y servir ahí los archivos de 1000 px son varios cientos de KB para
 * dibujar una estampilla. Las genera bin/optimizar-imagenes.sh en
 * assets/img/productos/miniaturas/, con el mismo nombre de archivo.
 *
 * Devuelve null si todavía no se generó —o si la imagen es un SVG, como el
 * marcador de foto pendiente— y ahí la vista usa el original.
 *
 * TODO(backend): cuando el panel permita subir fotos, el alta tiene que
 * generar esta miniatura además del WebP grande. Si no existe, el sitio no
 * se rompe: cae al archivo original, solo que pesado.
 */
function imagen_miniatura(string $ruta): ?string
{
    if (!preg_match('#^img/productos/([^/]+)\.(jpe?g|png|webp)$#i', $ruta, $partes)) {
        return null;
    }

    $miniatura = 'img/productos/miniaturas/' . $partes[1] . '.webp';
    $absoluta  = dirname(__DIR__) . '/assets/' . $miniatura;

    return is_file($absoluta) ? asset($miniatura) : null;
}
