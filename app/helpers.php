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
   Números y moneda
   ------------------------------------------------------------------ */

/**
 * Formato de precio argentino: $189.400. Punto de miles, sin decimales.
 * Los precios del catálogo son enteros en pesos: no hay centavos.
 */
function moneda(int|float|string|null $n): string
{
    return '$' . number_format((float) $n, 0, ',', '.');
}

/**
 * Igual que moneda() pero sin el símbolo, para cuando el símbolo va
 * en su propio elemento (por ejemplo el precio grande en degradé plata).
 */
function moneda_sin_simbolo(int|float|string|null $n): string
{
    return number_format((float) $n, 0, ',', '.');
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

    // Un porcentaje mal cargado en el panel no puede regalar mercadería
    // ni inflar el precio: se recorta a un rango sano.
    $pct = max(0.0, min(100.0, (float) $pct));

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
    return porcentaje((float) ($settings['descuento_transferencia_pct'] ?? 0));
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
 * URL de un archivo de assets, con un sufijo de versión sacado de la
 * fecha de modificación. Evita tener que purgar caché a mano en cada deploy.
 */
function asset(string $ruta): string
{
    $relativa = 'assets/' . ltrim($ruta, '/');
    $absoluta = dirname(__DIR__) . '/' . $relativa;
    $version  = is_file($absoluta) ? substr((string) filemtime($absoluta), -6) : null;

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

/* ------------------------------------------------------------------
   Contacto
   ------------------------------------------------------------------ */

/**
 * Arma el enlace de WhatsApp a partir de la configuración.
 * El canal mayorista no muestra precios: contacta por acá.
 *
 * @param array       $settings Configuración de repo_settings().
 * @param string|null $mensaje  Mensaje inicial; si es null usa el de settings.
 */
function whatsapp_link(array $settings, ?string $mensaje = null): string
{
    $numero = preg_replace('/\D+/', '', (string) ($settings['whatsapp'] ?? '')) ?? '';
    $texto  = $mensaje ?? (string) ($settings['whatsapp_mensaje'] ?? '');

    $url = 'https://wa.me/' . $numero;

    if ($texto !== '') {
        $url .= '?text=' . rawurlencode($texto);
    }

    return $url;
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
