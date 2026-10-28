<?php
/**
 * ============================================================================
 * planilla.php — leer un Excel (.xlsx) o un CSV subido desde el panel.
 * ============================================================================
 *
 * Lo pidió el cliente el 22/10: la lista de precios cambia cada 15 días y
 * tienen más de 450 productos en una planilla. Cargarlos de a uno no es
 * una opción.
 *
 * POR QUÉ NO HAY LIBRERÍA
 *
 * El proyecto no tiene composer ni build (CLAUDE.md §3), y PhpSpreadsheet son
 * miles de archivos para leer la primera hoja. Un .xlsx es un ZIP con XML
 * adentro; acá se lee el ZIP a mano —con gzinflate, que está en cualquier
 * PHP— para no depender de la extensión zip, que no todos los hostings
 * traen prendida.
 *
 * Se lee SOLO la primera hoja, y la primera fila son los encabezados.
 */

declare(strict_types=1);

/**
 * Las filas de la planilla, cada una como [encabezado normalizado => valor].
 *
 * Los encabezados se normalizan a minúsculas sin acentos ni espacios
 * ("Precio de lista" -> "precio_de_lista") para que el que arma la
 * planilla no tenga que acertar mayúsculas.
 *
 * @return array{filas:list<array<string,string>>, encabezados:list<string>, error:?string}
 */
function planilla_leer(string $ruta, string $nombre_original): array
{
    $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));

    $crudas = match ($ext) {
        'xlsx'        => _planilla_xlsx($ruta),
        'csv', 'txt'  => _planilla_csv($ruta),
        default       => null,
    };

    if ($crudas === null) {
        return [
            'filas' => [], 'encabezados' => [],
            'error' => $ext === 'xls'
                ? 'Es un Excel viejo (.xls). Abrilo y guardalo como .xlsx o como CSV.'
                : 'No se pudo leer el archivo. Subí un .xlsx o un .csv.',
        ];
    }

    // Se descartan las filas vacías de arriba antes de buscar encabezados.
    while ($crudas !== [] && implode('', array_map('trim', $crudas[0])) === '') {
        array_shift($crudas);
    }

    if ($crudas === []) {
        return ['filas' => [], 'encabezados' => [], 'error' => 'La planilla está vacía.'];
    }

    $encabezados = array_map('planilla_clave', array_shift($crudas));
    $filas = [];

    foreach ($crudas as $cruda) {
        if (implode('', array_map('trim', $cruda)) === '') {
            continue;
        }

        $fila = [];
        foreach ($encabezados as $i => $clave) {
            if ($clave !== '') {
                $fila[$clave] = trim((string) ($cruda[$i] ?? ''));
            }
        }
        $filas[] = $fila;
    }

    return ['filas' => $filas, 'encabezados' => array_values(array_filter($encabezados)), 'error' => null];
}

/** "Precio de Lista " -> "precio_de_lista". */
function planilla_clave(string $texto): string
{
    $texto = mb_strtolower(trim($texto));
    $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

    return trim((string) preg_replace('/[^a-z0-9]+/', '_', $texto), '_');
}

/**
 * El primer valor que exista entre varios nombres de columna posibles.
 *
 * La planilla del cliente no tiene un formato fijo: "precio", "precio
 * lista", "lista" y "pvp" quieren decir lo mismo.
 */
function planilla_valor(array $fila, array $nombres): ?string
{
    foreach ($nombres as $nombre) {
        if (isset($fila[$nombre]) && $fila[$nombre] !== '') {
            return $fila[$nombre];
        }
    }

    return null;
}

/**
 * "$ 189.400", "189400", "189.400,50", "189400.5" -> 189400 (pesos enteros).
 *
 * Con punto y coma juntos manda la coma como decimal (formato argentino).
 * Un número solo con puntos en grupos de tres es de miles. Devuelve null si
 * no hay número.
 */
function planilla_pesos(?string $texto): ?int
{
    if ($texto === null) {
        return null;
    }

    $limpio = preg_replace('/[^\d.,-]/', '', $texto) ?? '';

    if ($limpio === '' || !preg_match('/\d/', $limpio)) {
        return null;
    }

    if (str_contains($limpio, ',')) {
        $limpio = str_replace(['.', ','], ['', '.'], $limpio);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $limpio)) {
        $limpio = str_replace('.', '', $limpio);
    }

    return (int) round((float) $limpio);
}

/* ==========================================================================
   CSV
   ========================================================================== */

function _planilla_csv(string $ruta): ?array
{
    $contenido = @file_get_contents($ruta);

    if ($contenido === false) {
        return null;
    }

    // Excel en castellano guarda en Windows-1252 y con BOM a veces.
    $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;
    if (!mb_check_encoding($contenido, 'UTF-8')) {
        $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
    }

    $lineas = preg_split('/\r\n|\r|\n/', $contenido) ?: [];

    // Excel en castellano separa con punto y coma; el resto, con coma.
    $primera   = $lineas[0] ?? '';
    $separador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

    $filas = [];
    $memoria = fopen('php://temp', 'r+');
    fwrite($memoria, $contenido);
    rewind($memoria);

    while (($fila = fgetcsv($memoria, 0, $separador, '"', '')) !== false) {
        $filas[] = array_map(static fn ($v): string => (string) $v, $fila);
    }

    fclose($memoria);

    return $filas;
}

/* ==========================================================================
   XLSX
   ========================================================================== */

function _planilla_xlsx(string $ruta): ?array
{
    $zip = _planilla_zip_leer($ruta);

    if ($zip === null) {
        return null;
    }

    // Qué archivo es la primera hoja: lo dice workbook.xml y sus relaciones.
    $hoja = 'xl/worksheets/sheet1.xml';

    if (isset($zip['xl/workbook.xml'], $zip['xl/_rels/workbook.xml.rels'])) {
        $libro = @simplexml_load_string($zip['xl/workbook.xml']);
        $rels  = @simplexml_load_string($zip['xl/_rels/workbook.xml.rels']);

        if ($libro && $rels && isset($libro->sheets->sheet[0])) {
            $rid = (string) $libro->sheets->sheet[0]->attributes('r', true)['id'];

            foreach ($rels->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $destino = ltrim((string) $rel['Target'], '/');
                    $hoja = str_starts_with($destino, 'xl/') ? $destino : 'xl/' . $destino;
                }
            }
        }
    }

    if (!isset($zip[$hoja])) {
        return null;
    }

    $compartidos = [];

    if (isset($zip['xl/sharedStrings.xml'])) {
        $sst = @simplexml_load_string($zip['xl/sharedStrings.xml']);

        foreach ($sst ? $sst->si : [] as $si) {
            // Un texto con formato mezclado viene partido en varios <r><t>.
            if (isset($si->t)) {
                $compartidos[] = (string) $si->t;
            } else {
                $texto = '';
                foreach ($si->r as $r) {
                    $texto .= (string) $r->t;
                }
                $compartidos[] = $texto;
            }
        }
    }

    $xml = @simplexml_load_string($zip[$hoja]);

    if (!$xml) {
        return null;
    }

    $filas = [];

    foreach ($xml->sheetData->row as $row) {
        $fila = [];

        foreach ($row->c as $c) {
            // "C12" -> columna 2 (cero-indexada). Las celdas vacías no
            // vienen, así que la posición sale de la referencia.
            $col = _planilla_columna(preg_replace('/\d+/', '', (string) $c['r']) ?? 'A');
            $tipo = (string) $c['t'];

            $valor = match ($tipo) {
                's'         => $compartidos[(int) $c->v] ?? '',
                'inlineStr' => (string) $c->is->t,
                'b'         => ((string) $c->v) === '1' ? 'VERDADERO' : 'FALSO',
                default     => (string) $c->v,
            };

            // Los números de Excel llegan como 189400 o 1.894E+5: se
            // dejan como texto plano sin notación científica.
            if ($tipo === '' && is_numeric($valor) && stripos($valor, 'e') !== false) {
                $valor = rtrim(rtrim(sprintf('%.6F', (float) $valor), '0'), '.');
            }

            $fila[$col] = $valor;
        }

        if ($fila !== []) {
            $max = max(array_keys($fila));
            $completa = [];
            for ($i = 0; $i <= $max; $i++) {
                $completa[] = $fila[$i] ?? '';
            }
            $filas[] = $completa;
        }
    }

    return $filas;
}

/** "A" -> 0, "Z" -> 25, "AA" -> 26. */
function _planilla_columna(string $letras): int
{
    $n = 0;
    foreach (str_split(strtoupper($letras)) as $l) {
        $n = $n * 26 + (ord($l) - 64);
    }

    return max(0, $n - 1);
}

/**
 * Los archivos de un ZIP, como [nombre => contenido], leyendo el directorio
 * central. Sólo entiende "stored" (0) y "deflate" (8), que es todo lo que
 * usa Excel.
 *
 * @return array<string,string>|null
 */
function _planilla_zip_leer(string $ruta): ?array
{
    $datos = @file_get_contents($ruta);

    if ($datos === false || strlen($datos) < 22) {
        return null;
    }

    // El "fin del directorio central" está en los últimos 64 KB.
    $fin = strrpos($datos, "PK\x05\x06", -22);

    if ($fin === false) {
        return null;
    }

    $eocd     = unpack('vdisco/vdiscocd/ventradas/vtotal/Vtamano/Vinicio', substr($datos, $fin + 4, 16));
    $posicion = (int) $eocd['inicio'];
    $archivos = [];

    for ($i = 0; $i < (int) $eocd['total']; $i++) {
        if (substr($datos, $posicion, 4) !== "PK\x01\x02") {
            return null;
        }

        $cd = unpack(
            'vhecho/vnecesita/vflags/vmetodo/vhora/vfecha/Vcrc/Vcomprimido/Vtamano/vlnombre/vlextra/vlcomentario/vdisco/vinternos/Vexternos/Vlocal',
            substr($datos, $posicion + 4, 42)
        );
        $nombre = substr($datos, $posicion + 46, (int) $cd['lnombre']);

        // La cabecera local tiene su propio largo de nombre y de extra.
        $local = unpack('vlnombre/vlextra', substr($datos, (int) $cd['local'] + 26, 4));
        $desde = (int) $cd['local'] + 30 + (int) $local['lnombre'] + (int) $local['lextra'];
        $bruto = substr($datos, $desde, (int) $cd['comprimido']);

        $contenido = match ((int) $cd['metodo']) {
            0       => $bruto,
            8       => @gzinflate($bruto),
            default => false,
        };

        if ($contenido !== false && (str_ends_with($nombre, '.xml') || str_ends_with($nombre, '.rels'))) {
            $archivos[$nombre] = (string) $contenido;
        }

        $posicion += 46 + (int) $cd['lnombre'] + (int) $cd['lextra'] + (int) $cd['lcomentario'];
    }

    return $archivos;
}
