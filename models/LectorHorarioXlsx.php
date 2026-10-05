<?php

// LectorHorarioXlsx — Extrae el horario semanal del archivo tipo "HORARIO_*.xlsx"
// que produce el SENA.  Lee ficha, programa, semana/día/bloque e instructores.
// Estructura esperada (verificada sobre el archivo real):
//   - Fila 2: E2 = código de la ficha, H2 = nombre del programa.
//   - Cada bloque semanal arranca con una fila encabezado donde D="LUNES"
//     (y la fila siguiente trae los números de día del mes en D,F,H,J,L,N).
//   - Siguen 6 filas de datos: las 3 primeras = Bloque 1, las 3 últimas = Bloque 2.
//   - Los días son D=Lunes, F=Martes, H=Miércoles, J=Jueves, L=Viernes, N=Sábado;
//     la celda del día contiene el instructor ("Nombre Apellido\nROLES…") o "FESTIVO".
//   - Año: el horario se ancla al año actual y el día de inicio se lee de la
//     primera semana del propio archivo (último lunes de junio, día 24..30),
//     de modo que cada archivo produce sus fechas reales. Las fechas se
//     derivan por desfase de semanas/días. La fecha serial de los encabezados
//     (p. ej. 46023 = 2026-01-01) solo sirve de referencia.
class LectorHorarioXlsx
{
    public const TAMANO_MAXIMO = 2097152; // 2 MB

    // Columnas por día (Lunes..Sábado) y quiénes dibujan el día del mes.
    public const COLUMNAS_DIAS = ['D', 'F', 'H', 'J', 'L', 'N'];
    public const NOMBRE_DIAS = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO'];

    public int $fichaCodigo = 0;
    public string $programa = '';
    public int $anio = 0;
    public int $semanas = 0;
    public int $celdasConInstructor = 0;

    /** @var array<int,array{fecha:string,bloque:int,instructor:string}> */
    public array $clases = [];
    /** @var array<int,array{fecha:string,bloque:int,detalle:string}> */
    public array $festivos = [];
    /** @var array<int,string> */
    public array $inconsistencias = [];
    /** @var array<int,string> */
    public array $errores = [];

    // Iguala nombres con y sin tildes/mayúsculas (Ana García ~ ANA GARCIA).
    public static function normalizarNombre(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        $tildes = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i',
            'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n'
        ];
        return mb_strtolower(strtr($nombre, $tildes));
    }

    public function leer(string $ruta): bool
    {
        if (!is_file($ruta)) {
            $this->errores[] = 'El archivo no se pudo recibir.';
            return false;
        }

        $tamano = filesize($ruta);
        if ($tamano <= 0 || $tamano > self::TAMANO_MAXIMO) {
            $this->errores[] = 'El archivo supera el tamaño máximo permitido (2 MB).';
            return false;
        }

        if (!class_exists('ZipArchive')) {
            $this->errores[] = 'La extensión ZipArchive no está disponible en el servidor.';
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            $this->errores[] = 'El archivo no es un .xlsx válido.';
            return false;
        }

        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($shared === false || $sheet === false) {
            $this->errores[] = 'El .xlsx no contiene una hoja de cálculo válida.';
            return false;
        }

        $strings = $this->leerStrings($shared);
        $filas = $this->leerCeldas($sheet, $strings);

        if ($filas === null) {
            return false;
        }

        $this->extraer($filas);

        if ($this->anio <= 0) {
            $this->errores[] = 'No se pudo determinar el año del horario.';
            return false;
        }

        if ($this->semanas <= 0) {
            $this->errores[] = 'No se encontró ningún bloque semanal en el archivo.';
            return false;
        }

        if ($this->fichaCodigo <= 0) {
            $this->errores[] = 'No se encontró el código de la ficha en el archivo (celda E2).';
            return false;
        }

        return true;
    }

    private function leerStrings(string $xml): array
    {
        $out = [];
        try {
            $x = new SimpleXMLElement($xml);
        } catch (Exception $e) {
            return $out;
        }
        foreach ($x->si as $si) {
            $partes = [];
            foreach ($si as $nodo) {
                $partes[] = (string)$nodo;
            }
            $out[] = implode('', $partes);
        }
        return $out;
    }

    private function leerCeldas(string $xml, array $strings): ?array
    {
        try {
            $x = new SimpleXMLElement($xml);
        } catch (Exception $e) {
            $this->errores[] = 'La hoja de cálculo contiene XML inválido.';
            return null;
        }

        $filas = [];
        foreach ($x->sheetData->row as $row) {
            $r = (int)$row['r'];
            $mapa = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $col = preg_replace('/[0-9]+$/', '', $ref);
                $tipo = (string)$c['t'];
                $valor = (string)$c->v;
                $mapa[$col] = $tipo === 's' ? ($strings[(int)$valor] ?? '') : $valor;
            }
            $filas[$r] = $mapa;
        }
        return $filas;
    }

    private function valorFila(array $fila, string $col): string
    {
        return isset($fila[$col]) ? trim((string)$fila[$col]) : '';
    }

    private function extraer(array $filas): void
    {
        // Encabezado general: E2 = ficha, H2 = programa.
        if (isset($filas[2])) {
            $cod = $this->valorFila($filas[2], 'E');
            if (is_numeric($cod)) {
                $this->fichaCodigo = (int)round((float)$cod);
            }
            $this->programa = $this->valorFila($filas[2], 'H');
        }

        // Año del horario: ancla al año actual (petición: "año actual y sus
        // fechas"). El serial de fecha de los encabezados queda como referencia.
        $this->anio = (int)date('Y');

        // Recorre las filas en orden y detecta los bloques semanales.
        $lineas = array_keys($filas);
        sort($lineas, SORT_NUMERIC);
        $n = count($lineas);
        $semana = 0;
        $baseInicio = null;

        for ($i = 0; $i < $n; $i++) {
            $r = $lineas[$i];
            $fila = $filas[$r];

            // Solo un encabezado de semana: D == "LUNES" y la fila siguiente
            // trae el día del mes en las columnas de días (1..31).
            if ($this->valorFila($fila, 'D') !== 'LUNES') {
                continue;
            }
            $rDias = $i + 1 < $n ? $lineas[$i + 1] : null;
            if ($rDias === null || !$this->esFilaDeDias($filas[$rDias])) {
                continue;
            }
            $filaDias = $filas[$rDias];

            // Semana 0 arranca el último lunes de junio. Ese día puede ser 29 o
            // 30 según el calendario, así que se lee del archivo en lugar de
            // fijarlo: la plantilla usa "LUNES" como encabezado de la columna D.
            if ($semana === 0) {
                $primerDia = (int)$this->valorFila($filaDias, 'D');
                if ($primerDia < 24 || $primerDia > 30) {
                    $this->errores[] = 'La primera semana del horario no inicia en la última semana de junio.';
                    return;
                }
                // Cronología en UTC: evita desfases por cambio de hora del
                // uso horario del servidor al sumar semanas enteras.
                $baseInicio = (new DateTimeImmutable($this->anio . '-06-01 00:00:00', new DateTimeZone('UTC')))
                    ->modify('+' . ($primerDia - 1) . ' days');
            }

            // 6 filas de datos: 3 Bloque 1 + 3 Bloque 2.
            $recolector = [];
            $festivoDetectado = [];
            for ($k = 0; $k < 6; $k++) {
                $rData = $i + 2 + $k < $n ? $lineas[$i + 2 + $k] : null;
                if ($rData === null) {
                    break;
                }
                $filaData = $filas[$rData];
                $bloque = $k < 3 ? 1 : 2;
                foreach (self::COLUMNAS_DIAS as $indice => $col) {
                    $v = $this->valorFila($filaData, $col);
                    if ($v === '') {
                        continue;
                    }
                    if ($v === 'FESTIVO') {
                        $festivoDetectado[$indice][$bloque] = true;
                        continue;
                    }
                    $instructor = trim(explode("\n", $v, 2)[0]);
                    if ($instructor === '') {
                        continue;
                    }
                    $recolector[$indice][$bloque][LectorHorarioXlsx::normalizarNombre($instructor)] = $instructor;
                    $this->celdasConInstructor++;
                }
            }

            // Materializa clases y festivos de la semana.
            foreach (self::COLUMNAS_DIAS as $indice => $col) {
                $desfase = $semana * 7 + $indice;
                $fecha = $baseInicio->modify('+' . $desfase . ' days')->format('Y-m-d');
                for ($bloque = 1; $bloque <= 2; $bloque++) {
                    if (!empty($festivoDetectado[$indice][$bloque])) {
                        $this->festivos[] = ['fecha' => $fecha, 'bloque' => $bloque, 'detalle' => 'FESTIVO'];
                        continue;
                    }
                    $candidatos = $recolector[$indice][$bloque] ?? [];
                    if (count($candidatos) === 0) {
                        continue;
                    }
                    if (count($candidatos) > 1) {
                        $this->inconsistencias[] = 'Semana ' . ($semana + 1) . ', ' . self::NOMBRE_DIAS[$indice] . ': bloque ' . $bloque . ' con varios instructores.';
                        continue;
                    }
                    $instructor = reset($candidatos);
                    $this->clases[] = [
                        'fecha' => $fecha,
                        'bloque' => $bloque,
                        'instructor' => $instructor
                    ];
                }
            }

            $semana++;
            $this->semanas = $semana;
            $i++; // salta la fila de días ya consumida
        }

        if ($semana === 0) {
            $this->errores[] = 'No se encontró ningún bloque semanal en el archivo.';
        }
    }

    private function esFilaDeDias(array $fila): bool
    {
        $v = $this->valorFila($fila, 'D');
        return is_numeric($v) && (int)$v >= 1 && (int)$v <= 31;
    }

    // Serial de fecha que marca el año en los encabezados (referencia; el año
    // efectivo es el actual). Se conserva para compatibilidad con archivos previos.
    private function buscarSerial(array $filas): ?int
    {
        foreach ($filas as $fila) {
            foreach ($fila as $valor) {
                if (is_numeric($valor) && (int)$valor === 46023) {
                    return 46023;
                }
            }
        }
        return null;
    }
}