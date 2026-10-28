<?php
/**
 * ============================================================================
 * envio.php — cuánto cuesta mandar un pedido, y cuándo no se sabe.
 * ============================================================================
 *
 * Hasta octubre de 2026 el envío era 0 y "a coordinar" en todo el país, y el
 * umbral de envío gratis valía para cualquier provincia: una jaula de 196 kg
 * salía gratis a Ushuaia. El cliente pidió (22/10):
 *
 *   · que el envío gratis se pueda apagar y, si se usa, sea sólo por zona;
 *   · una tarifa editable para CABA y alrededores;
 *   · "envío a cotizar" para racks, jaulas y bancos: el pedido entra y
 *     ellos cotizan;
 *   · origen editable (hoy San Pedro, CP 2930) y retiro apagado.
 *
 * LAS CUATRO SALIDAS POSIBLES
 *
 *   gratis     dentro de la zona y por encima del umbral.
 *   zona       dentro de la zona: se cobra la tarifa fija, en el mismo pago.
 *   a_cotizar  algún producto es pesado o voluminoso, o el destino está
 *              fuera de la zona. El pedido se registra igual y Rastro
 *              avisa el costo. Cuando entre el agregador (Zipnova o
 *              Envíopack) el "fuera de zona" pasa a cotizarse acá mismo.
 *   sin_cp     todavía no hay código postal: no se puede decir nada.
 *
 * La misma cuenta está replicada en assets/js/carrito.js para el resumen del
 * checkout, igual que el umbral de envío gratis antes. El servidor es el que
 * manda: lo que se cobra sale de envio_calcular(), no del navegador.
 */

declare(strict_types=1);

/**
 * Los ajustes de envío, con sus valores por defecto.
 *
 * Los defaults importan: en producción la tabla settings no tiene estas
 * claves hasta que alguien guarde la configuración desde el panel, y el
 * comportamiento sin tocar nada tiene que ser el que pidió el cliente
 * —envío gratis sólo en zona—, no el viejo.
 */
function envio_config(?array $settings = null): array
{
    $s = $settings ?? repo_settings();

    $kg = numero_decimal($s['envio_cotizar_kg'] ?? 50);

    return [
        'gratis_desde'      => max(0, (int) ($s['envio_gratis_desde'] ?? 0)),
        'zona_nombre'       => trim((string) ($s['envio_zona_nombre'] ?? '')) ?: 'CABA y alrededores',
        'zona_rangos'       => envio_rangos((string) ($s['envio_zona_cps'] ?? '')),
        'zona_costo'        => max(0, (int) ($s['envio_zona_costo'] ?? 0)),
        'cotizar_kg'        => $kg > 0 ? $kg : 50.0,
        'cotizar_categorias' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($s['envio_cotizar_categorias'] ?? 'racks-y-jaulas,bancos'))
        ))),
        'origen_cp'         => trim((string) ($s['envio_origen_cp'] ?? '')) ?: '2930',
        'origen_localidad'  => trim((string) ($s['envio_origen_localidad'] ?? '')) ?: 'San Pedro, Buenos Aires',
    ];
}

/**
 * "1000-1499, 1600-1899, 1900" -> [[1000,1499],[1600,1899],[1900,1900]].
 *
 * Se aceptan comas, punto y coma o saltos de línea, y rangos con guion.
 * Lo que no se entiende se descarta en vez de romper el checkout: el panel
 * muestra cómo quedó interpretado.
 *
 * @return list<array{0:int,1:int}>
 */
function envio_rangos(string $texto): array
{
    $rangos = [];

    foreach (preg_split('/[,;\n]+/', $texto) ?: [] as $pieza) {
        if (preg_match('/^\s*(\d{4})\s*(?:-\s*(\d{4}))?\s*$/', $pieza, $m)) {
            $desde = (int) $m[1];
            $hasta = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $desde;
            $rangos[] = [min($desde, $hasta), max($desde, $hasta)];
        }
    }

    return $rangos;
}

/**
 * Los cuatro dígitos de un código postal, venga como venga.
 *
 * El CPA nuevo es "C1425ABC": letra de provincia, cuatro dígitos y tres
 * letras de manzana. La gente escribe cualquiera de los dos; para la zona
 * alcanzan los cuatro números.
 */
function envio_cp_numero(string $cp): ?int
{
    return preg_match('/(\d{4})/', $cp, $m) ? (int) $m[1] : null;
}

function envio_cp_en_zona(string $cp, array $config): bool
{
    $n = envio_cp_numero($cp);

    if ($n === null) {
        return false;
    }

    foreach ($config['zona_rangos'] as [$desde, $hasta]) {
        if ($n >= $desde && $n <= $hasta) {
            return true;
        }
    }

    return false;
}

/**
 * Los datos de envío de un producto: medidas de la caja, bultos y si se
 * cotiza aparte.
 *
 * @return array{alto_cm:?float, ancho_cm:?float, largo_cm:?float, bultos:int, a_cotizar:bool}
 */
function envio_producto(array $producto): array
{
    $e = is_array($producto['envio'] ?? null) ? $producto['envio'] : [];

    $num = static fn ($v): ?float => is_numeric($v) && (float) $v > 0 ? (float) $v : null;

    return [
        'alto_cm'   => $num($e['alto_cm'] ?? null),
        'ancho_cm'  => $num($e['ancho_cm'] ?? null),
        'largo_cm'  => $num($e['largo_cm'] ?? null),
        'bultos'    => max(1, (int) ($e['bultos'] ?? 1)),
        'a_cotizar' => !empty($e['a_cotizar']),
    ];
}

/**
 * ¿Este producto se cotiza aparte?
 *
 * Sí si está marcado a mano, si su categoría está en la lista (racks,
 * jaulas, bancos) o si algún bulto pasa el peso que aceptan los
 * agregadores (50 kg).
 */
function envio_producto_a_cotizar(array $producto, ?array $config = null): bool
{
    $config ??= envio_config();
    $datos  = envio_producto($producto);

    if ($datos['a_cotizar']) {
        return true;
    }

    if (in_array((string) ($producto['categoria'] ?? ''), $config['cotizar_categorias'], true)) {
        return true;
    }

    $peso = (float) ($producto['peso_kg'] ?? 0);

    return $peso > 0 && ($peso / $datos['bultos']) > $config['cotizar_kg'];
}

/**
 * El envío de un pedido.
 *
 * @param array  $lineas     Las de checkout_lineas(): con producto_id y
 *                           precio_publicado.
 * @param string $cp         Código postal de entrega.
 *
 * @return array{modo:string, costo:int, texto:string, detalle:string}
 */
function envio_calcular(array $lineas, string $cp, ?array $settings = null): array
{
    $config = envio_config($settings);

    $ids = array_map(static fn (array $l): int => (int) $l['producto_id'], $lineas);
    $productos = [];
    foreach (repo_cart_items($ids) as $p) {
        $productos[(int) ($p['id'] ?? 0)] = $p;
    }

    $publicado = 0;
    $pesados   = [];

    foreach ($lineas as $l) {
        $publicado += (int) $l['precio_publicado'] * (int) $l['cantidad'];

        $p = $productos[(int) $l['producto_id']] ?? null;
        if ($p !== null && envio_producto_a_cotizar($p, $config)) {
            $pesados[] = (string) ($p['nombre'] ?? '');
        }
    }

    if ($pesados !== []) {
        return [
            'modo'    => 'a_cotizar',
            'costo'   => 0,
            'texto'   => 'A cotizar',
            'detalle' => 'Por el tamaño de ' . implode(', ', $pesados)
                . ' el envío se cotiza aparte. Registramos el pedido y te escribimos con el costo.',
        ];
    }

    if (envio_cp_numero($cp) === null) {
        return ['modo' => 'sin_cp', 'costo' => 0, 'texto' => 'Ingresá tu código postal', 'detalle' => ''];
    }

    if (envio_cp_en_zona($cp, $config)) {
        if ($config['gratis_desde'] > 0 && $publicado >= $config['gratis_desde']) {
            return [
                'modo'    => 'gratis',
                'costo'   => 0,
                'texto'   => 'Gratis',
                'detalle' => 'Envío sin cargo en ' . $config['zona_nombre'] . '.',
            ];
        }

        if ($config['zona_costo'] > 0) {
            return [
                'modo'    => 'zona',
                'costo'   => $config['zona_costo'],
                'texto'   => moneda($config['zona_costo']),
                'detalle' => 'Envío a ' . $config['zona_nombre'] . '.',
            ];
        }
    }

    return [
        'modo'    => 'a_cotizar',
        'costo'   => 0,
        'texto'   => 'A cotizar',
        'detalle' => 'Te escribimos con el costo de envío a tu código postal antes de despacharlo.',
    ];
}

/**
 * Lo que el resumen del carrito y el checkout necesitan para repetir la
 * cuenta en el navegador. Sólo datos, nada de lógica.
 */
function envio_config_js(?array $settings = null): array
{
    $c = envio_config($settings);

    return [
        'gratisDesde' => $c['gratis_desde'],
        'zonaNombre'  => $c['zona_nombre'],
        'zonaRangos'  => $c['zona_rangos'],
        'zonaCosto'   => $c['zona_costo'],
    ];
}

/** Una línea para la ficha de producto. */
function envio_texto_producto(array $producto, ?array $settings = null): string
{
    $c = envio_config($settings);

    if (envio_producto_a_cotizar($producto, $c)) {
        return 'A todo el país. Por su tamaño, el envío se cotiza aparte.';
    }

    $zona = $c['zona_rangos'] !== [];

    if ($zona && $c['gratis_desde'] > 0) {
        return 'A todo el país. Gratis en ' . $c['zona_nombre'] . ' desde ' . moneda($c['gratis_desde']) . '.';
    }

    if ($zona && $c['zona_costo'] > 0) {
        return 'A todo el país. ' . $c['zona_nombre'] . ': ' . moneda($c['zona_costo']) . '.';
    }

    return 'A todo el país. Se cotiza según tu código postal.';
}

/** Una línea general, para la regla de precio y los textos del sitio. */
function envio_texto_general(?array $settings = null): string
{
    $c = envio_config($settings);

    if ($c['zona_rangos'] !== [] && $c['gratis_desde'] > 0) {
        return 'Sin cargo en ' . $c['zona_nombre'] . ' desde ' . moneda($c['gratis_desde']) . '. Al resto del país, según código postal.';
    }

    if ($c['zona_rangos'] !== [] && $c['zona_costo'] > 0) {
        return $c['zona_nombre'] . ': ' . moneda($c['zona_costo']) . '. Al resto del país, según código postal.';
    }

    return 'A todo el país. El costo depende del peso y del código postal.';
}

/** Cómo se muestra el envío de un pedido ya hecho. */
function envio_texto_pedido(array $pedido): string
{
    $costo = (int) ($pedido['envio'] ?? 0);

    if ($costo > 0) {
        return moneda($costo);
    }

    return match ((string) ($pedido['entrega']['envio_modo'] ?? '')) {
        'gratis'    => 'Sin cargo',
        'a_cotizar' => 'A cotizar',
        // Los pedidos de antes del 28/10 no tienen modo: el envío se
        // coordinaba siempre aparte.
        default     => 'A coordinar',
    };
}
