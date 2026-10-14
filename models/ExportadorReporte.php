<?php
declare(strict_types=1);

/**
 * ExportadorReporte — Genera archivos XLSX y PDF en PHP puro (sin Composer)
 * a partir de los datos producidos por ReporteModel.
 *
 * XLSX: construye un libro OpenXML con ZipArchive nativo.
 * PDF:  construye un documento %PDF-1.4 con fuentes Type1 estándar.
 */
class ExportadorReporte
{
    // ========================================================================
    // ===========================  XLSX  =====================================
    // ========================================================================

    /**
     * Genera un libro .xlsx con 2 hojas: Resumen Aprendices y Detalle Inasistencias.
     *
     * @return string  Contenido binario del .xlsx listo para enviar al navegador.
     */
    public function generarXlsx(
        array $totales,
        array $resumen,
        array $inasistencias,
        array $filtros = []
    ): string {
        $archivos = [
            '[Content_Types].xml'        => $this->xlsxContentTypes(),
            '_rels/.rels'                => $this->xlsxRels(),
            'xl/workbook.xml'            => $this->xlsxWorkbook(),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(),
            'xl/styles.xml'              => $this->xlsxStyles(),
            'xl/worksheets/sheet1.xml'   => $this->xlsxSheet1($resumen, $filtros),
            'xl/worksheets/sheet2.xml'   => $this->xlsxSheet2($inasistencias),
        ];

        return $this->crearZipEnMemoria($archivos);
    }

    /**
     * Construye un contenedor ZIP estándar en memoria sin librerías externas ni acceso a disco.
     *
     * @param array<string,string> $archivos  Mapa de 'ruta/archivo' => 'contenido'
     * @return string  Contenido binario del archivo ZIP.
     */
    private function crearZipEnMemoria(array $archivos): string
    {
        $tieneGz = function_exists('gzdeflate');
        $localHeaders = '';
        $centralDir = '';
        $offset = 0;

        foreach ($archivos as $nombre => $contenido) {
            $uSize = strlen($contenido);
            $crc   = crc32($contenido);

            if ($tieneGz) {
                $cData  = gzdeflate($contenido, 6);
                $metodo = 8;
            } else {
                $cData  = $contenido;
                $metodo = 0;
            }
            $cSize = strlen($cData);

            // Encabezado local de archivo (PK\x03\x04)
            $lh = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                $metodo,
                0,
                0x21,
                $crc,
                $cSize,
                $uSize,
                strlen($nombre),
                0
            ) . $nombre . $cData;

            // Entrada del directorio central (PK\x01\x02)
            $centralDir .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                $metodo,
                0,
                0x21,
                $crc,
                $cSize,
                $uSize,
                strlen($nombre),
                0,
                0,
                0,
                0,
                0x20,
                $offset
            ) . $nombre;

            $localHeaders .= $lh;
            $offset += strlen($lh);
        }

        // Fin de directorio central (PK\x05\x06)
        $numArchivos = count($archivos);
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $numArchivos,
            $numArchivos,
            strlen($centralDir),
            $offset,
            0
        );

        return $localHeaders . $centralDir . $eocd;
    }

    // ── Helpers XLSX ────────────────────────────────────────────────────

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function xlsxRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Resumen Aprendices" sheetId="1" r:id="rId1"/>'
            . '<sheet name="Detalle Inasistencias" sheetId="2" r:id="rId2"/>'
            . '</sheets>'
            . '</workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            .   '<font><sz val="11"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2">'
            .   '<fill><patternFill patternType="none"/></fill>'
            .   '<fill><patternFill patternType="gray125"/></fill>'
            . '</fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function xlsxSheet1(array $resumen, array $filtros): string
    {
        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;
        $rango = 'Todas las fechas';
        if ($desde && $hasta) {
            $rango = "Desde: $desde — Hasta: $hasta";
        } elseif ($desde) {
            $rango = "Desde: $desde";
        } elseif ($hasta) {
            $rango = "Hasta: $hasta";
        }

        $rows = '';

        // Fila 1: Título
        $rows .= '<row r="1">'
            . $this->xlsxCellStr('A1', 'REPORTE DE ASISTENCIA POR APRENDIZ - SENA', 1)
            . $this->xlsxCellStr('E1', $rango, 1)
            . '</row>';

        // Fila 3: Encabezados
        $headers = [
            'A' => 'Identificación', 'B' => 'Aprendiz', 'C' => 'Ficha',
            'D' => 'Programa', 'E' => 'Asistencias', 'F' => 'A Tiempo',
            'G' => 'Retardos', 'H' => 'Tiempo Retardo (min)',
            'I' => 'Tiempo Retardo (h/m)', 'J' => 'Salidas Anticipadas',
            'K' => 'Total Inasistencias', 'L' => 'Días Inasistencia',
        ];
        $headerCells = '';
        foreach ($headers as $col => $label) {
            $headerCells .= $this->xlsxCellStr($col . '3', $label, 1);
        }
        $rows .= '<row r="3">' . $headerCells . '</row>';

        // Filas de datos
        $r = 4;
        foreach ($resumen as $ap) {
            $mins = (int) $ap['minutos_retardo'];
            $hm = $mins >= 60
                ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm'
                : $mins . ' min';

            $rows .= '<row r="' . $r . '">'
                . $this->xlsxCellStr('A' . $r, (string) $ap['identificacion'])
                . $this->xlsxCellStr('B' . $r, $ap['nombre'] . ' ' . $ap['apellido'])
                . $this->xlsxCellStr('C' . $r, (string) $ap['ficha_codigo'])
                . $this->xlsxCellStr('D' . $r, $ap['programa_nombre'])
                . $this->xlsxCellNum('E' . $r, (int) $ap['total_asistencias'])
                . $this->xlsxCellNum('F' . $r, (int) $ap['total_a_tiempo'])
                . $this->xlsxCellNum('G' . $r, (int) $ap['total_retardos'])
                . $this->xlsxCellNum('H' . $r, $mins)
                . $this->xlsxCellStr('I' . $r, $hm)
                . $this->xlsxCellNum('J' . $r, (int) $ap['total_salidas_anticipadas'])
                . $this->xlsxCellNum('K' . $r, (int) $ap['total_inasistencias'])
                . $this->xlsxCellNum('L' . $r, (int) $ap['dias_inasistencia'])
                . '</row>';
            $r++;
        }

        return $this->xlsxSheetWrap($rows);
    }

    private function xlsxSheet2(array $inasistencias): string
    {
        $rows = '';

        // Fila 1: Título
        $rows .= '<row r="1">'
            . $this->xlsxCellStr('A1', 'DETALLE DE DÍAS DE INASISTENCIA E INSTRUCTOR', 1)
            . '</row>';

        // Fila 3: Encabezados
        $headers = [
            'A' => 'Fecha', 'B' => 'Horario', 'C' => 'Identificación',
            'D' => 'Aprendiz', 'E' => 'Ficha', 'F' => 'Competencia',
            'G' => 'Instructor Encargado',
        ];
        $headerCells = '';
        foreach ($headers as $col => $label) {
            $headerCells .= $this->xlsxCellStr($col . '3', $label, 1);
        }
        $rows .= '<row r="3">' . $headerCells . '</row>';

        // Filas de datos
        $r = 4;
        foreach ($inasistencias as $ina) {
            $horario = substr((string) $ina['hora_inicio'], 0, 5) . ' - ' . substr((string) $ina['hora_fin'], 0, 5);
            $rows .= '<row r="' . $r . '">'
                . $this->xlsxCellStr('A' . $r, (string) $ina['fecha'])
                . $this->xlsxCellStr('B' . $r, $horario)
                . $this->xlsxCellStr('C' . $r, (string) $ina['aprendiz_identificacion'])
                . $this->xlsxCellStr('D' . $r, $ina['aprendiz_nombre'] . ' ' . $ina['aprendiz_apellido'])
                . $this->xlsxCellStr('E' . $r, (string) $ina['ficha_codigo'])
                . $this->xlsxCellStr('F' . $r, (string) $ina['competencia_nombre'])
                . $this->xlsxCellStr('G' . $r, $ina['instructor_nombre'] . ' ' . $ina['instructor_apellido'])
                . '</row>';
            $r++;
        }

        return $this->xlsxSheetWrap($rows);
    }

    private function xlsxSheetWrap(string $rows): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $rows . '</sheetData>'
            . '</worksheet>';
    }

    private function xlsxCellStr(string $ref, string $value, int $style = 0): string
    {
        $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $s = $style > 0 ? ' s="' . $style . '"' : '';
        return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
    }

    private function xlsxCellNum(string $ref, int $value, int $style = 0): string
    {
        $s = $style > 0 ? ' s="' . $style . '"' : '';
        return '<c r="' . $ref . '"' . $s . '><v>' . $value . '</v></c>';
    }

    // ========================================================================
    // ============================  PDF  =====================================
    // ========================================================================

    /** @var array<int,string> Objetos del PDF en construcción. */
    private array $pdfObjects = [];
    /** @var int Contador de objetos. */
    private int $pdfObjCount = 0;
    /** @var array<int,int> IDs de objetos de página. */
    private array $pdfPageIds = [];
    /** @var string Contenido del stream de la página actual. */
    private string $pdfStream = '';
    /** @var float Coordenada Y actual en la página. */
    private float $pdfY = 0.0;
    /** @var int ID del objeto Pages (se asigna tras construir). */
    private int $pdfPagesObjId = 0;
    /** @var string Fuente activa actual. */
    private string $pdfFont = 'F1';
    /** @var float Tamaño de fuente activo. */
    private float $pdfFontSize = 9.0;

    // Dimensiones A4 Landscape
    private const PAGE_W = 842.0;
    private const PAGE_H = 595.0;
    private const MARGIN_LEFT = 40.0;
    private const MARGIN_TOP = 555.0;
    private const MARGIN_BOTTOM = 50.0;
    private const LINE_HEIGHT = 14.0;

    /**
     * Genera un documento PDF en orientación horizontal con fuentes Type1 estándar.
     *
     * @return string  Contenido binario del PDF.
     */
    public function generarPdf(
        array $totales,
        array $resumen,
        array $inasistencias,
        array $filtros = []
    ): string {
        // Reiniciar estado
        $this->pdfObjects = [];
        $this->pdfObjCount = 0;
        $this->pdfPageIds = [];
        $this->pdfStream = '';
        $this->pdfY = self::MARGIN_TOP;
        $this->pdfFont = 'F1';
        $this->pdfFontSize = 9.0;

        // ── Objetos base (Catalog, Pages, Fonts) ────────────────────────
        // 1: Catalog (referencia a Pages que se rellenará al final)
        $this->pdfAddObject(''); // placeholder catalog (obj 1)
        // 2: Pages   (placeholder)
        $this->pdfPagesObjId = 2;
        $this->pdfAddObject(''); // placeholder pages (obj 2)
        // 3: Font Helvetica
        $this->pdfAddObject(
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>"
        );
        // 4: Font Helvetica-Bold
        $this->pdfAddObject(
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>"
        );

        // ── Iniciar primera página ──────────────────────────────────────
        $this->pdfNewPage();

        // ── Encabezado institucional ────────────────────────────────────
        $this->pdfSetFont('F2', 14);
        $this->pdfText(self::MARGIN_LEFT, $this->pdfY, 'SENA - Sistema de Control de Ingreso de Aprendices');
        $this->pdfY -= self::LINE_HEIGHT * 1.4;
        $this->pdfSetFont('F2', 11);
        $this->pdfText(self::MARGIN_LEFT, $this->pdfY, 'Reporte de Asistencias, Retardos e Inasistencias');
        $this->pdfY -= self::LINE_HEIGHT * 1.2;

        $this->pdfSetFont('F1', 9);
        $this->pdfText(self::MARGIN_LEFT, $this->pdfY, 'Fecha de generación: ' . date('Y-m-d H:i'));
        $this->pdfY -= self::LINE_HEIGHT;

        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;
        if ($desde || $hasta) {
            $rango = '';
            if ($desde) {
                $rango .= 'Desde: ' . $desde;
            }
            if ($hasta) {
                $rango .= ($rango !== '' ? '  |  ' : '') . 'Hasta: ' . $hasta;
            }
            $this->pdfText(self::MARGIN_LEFT, $this->pdfY, $rango);
            $this->pdfY -= self::LINE_HEIGHT;
        }

        $this->pdfY -= self::LINE_HEIGHT;

        // ── Resumen de Totales Generales ────────────────────────────────
        $this->pdfSetFont('F2', 10);
        $this->pdfText(self::MARGIN_LEFT, $this->pdfY, 'RESUMEN DE TOTALES GENERALES');
        $this->pdfY -= self::LINE_HEIGHT * 1.2;

        $this->pdfSetFont('F1', 9);
        $lineasTotales = [
            'Aprendices: ' . ($totales['total_aprendices'] ?? 0),
            'Asistencias: ' . ($totales['total_asistencias'] ?? 0),
            'Retardos: ' . ($totales['total_retardos'] ?? 0),
            'Minutos de Retardo acumulados: ' . ($totales['minutos_retardo'] ?? 0),
            'Salidas Anticipadas: ' . ($totales['total_salidas_anticipadas'] ?? 0),
            'Inasistencias: ' . ($totales['total_inasistencias'] ?? 0),
        ];
        foreach ($lineasTotales as $linea) {
            $this->pdfCheckPage();
            $this->pdfText(self::MARGIN_LEFT + 10, $this->pdfY, $linea);
            $this->pdfY -= self::LINE_HEIGHT;
        }

        $this->pdfY -= self::LINE_HEIGHT;

        // ── Sección 1: Resumen por Aprendiz ─────────────────────────────
        $this->pdfCheckPage();
        $this->pdfSetFont('F2', 11);
        $this->pdfText(self::MARGIN_LEFT, $this->pdfY, '1. Resumen por Aprendiz');
        $this->pdfY -= self::LINE_HEIGHT * 1.3;

        // Encabezados de tabla
        $col1 = [
            self::MARGIN_LEFT,        // Identificación
            self::MARGIN_LEFT + 90,   // Aprendiz
            self::MARGIN_LEFT + 230,  // Ficha
            self::MARGIN_LEFT + 290,  // Asist.
            self::MARGIN_LEFT + 340,  // Retardos
            self::MARGIN_LEFT + 400,  // Tiempo Retardo
            self::MARGIN_LEFT + 500,  // Sal. Ant.
            self::MARGIN_LEFT + 560,  // Días Inasist.
            self::MARGIN_LEFT + 650,  // Total Inasist.
        ];
        $hdr1 = ['Identificación', 'Aprendiz', 'Ficha', 'Asist.', 'Retardos', 'Tiempo Retardo', 'Sal. Ant.', 'Días Inasist.', 'Total Inasist.'];

        $this->pdfCheckPage();
        $this->pdfSetFont('F2', 8);
        foreach ($hdr1 as $i => $h) {
            $this->pdfText($col1[$i], $this->pdfY, $h);
        }
        $this->pdfY -= 2;
        // Línea separadora
        $this->pdfLine(self::MARGIN_LEFT, $this->pdfY, self::PAGE_W - self::MARGIN_LEFT, $this->pdfY);
        $this->pdfY -= self::LINE_HEIGHT;

        $this->pdfSetFont('F1', 7);
        if (empty($resumen)) {
            $this->pdfCheckPage();
            $this->pdfSetFont('F1', 9);
            $this->pdfText(self::MARGIN_LEFT, $this->pdfY, 'Sin registros para los filtros seleccionados.');
            $this->pdfY -= self::LINE_HEIGHT;
        } else {
            foreach ($resumen as $ap) {
                $this->pdfCheckPage();

                $mins = (int) $ap['minutos_retardo'];
                $tiempoRetardo = $mins >= 60
                    ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm'
                    : $mins . ' min';

                $fila = [
                    (string) $ap['identificacion'],
                    $this->recortarTexto($ap['nombre'] . ' ' . $ap['apellido'], 28),
                    (string) $ap['ficha_codigo'],
                    (string) $ap['total_asistencias'],
                    (string) $ap['total_retardos'],
                    $tiempoRetardo,
                    (string) $ap['total_salidas_anticipadas'],
                    (string) $ap['dias_inasistencia'],
                    (string) $ap['total_inasistencias'],
                ];
                foreach ($fila as $i => $val) {
                    $this->pdfText($col1[$i], $this->pdfY, $val);
                }
                $this->pdfY -= self::LINE_HEIGHT;
            }
        }

        $this->pdfY -= self::LINE_HEIGHT;

        // ── Sección 2: Detalle de Inasistencias ─────────────────────────
        $this->pdfCheckPage();
        $this->pdfSetFont('F2', 11);
        $this->pdfText(self::MARGIN_LEFT, $this->pdfY, '2. Días Específicos de Inasistencia con Instructor');
        $this->pdfY -= self::LINE_HEIGHT * 1.3;

        $col2 = [
            self::MARGIN_LEFT,        // Fecha
            self::MARGIN_LEFT + 75,   // Horario
            self::MARGIN_LEFT + 165,  // Identificación
            self::MARGIN_LEFT + 260,  // Aprendiz
            self::MARGIN_LEFT + 400,  // Ficha
            self::MARGIN_LEFT + 460,  // Competencia
            self::MARGIN_LEFT + 610,  // Instructor
        ];
        $hdr2 = ['Fecha', 'Horario', 'Identificación', 'Aprendiz', 'Ficha', 'Competencia', 'Instructor'];

        $this->pdfCheckPage();
        $this->pdfSetFont('F2', 8);
        foreach ($hdr2 as $i => $h) {
            $this->pdfText($col2[$i], $this->pdfY, $h);
        }
        $this->pdfY -= 2;
        $this->pdfLine(self::MARGIN_LEFT, $this->pdfY, self::PAGE_W - self::MARGIN_LEFT, $this->pdfY);
        $this->pdfY -= self::LINE_HEIGHT;

        $this->pdfSetFont('F1', 7);
        if (empty($inasistencias)) {
            $this->pdfCheckPage();
            $this->pdfSetFont('F1', 9);
            $this->pdfText(self::MARGIN_LEFT, $this->pdfY, 'Sin inasistencias registradas en el periodo seleccionado.');
            $this->pdfY -= self::LINE_HEIGHT;
        } else {
            foreach ($inasistencias as $ina) {
                $this->pdfCheckPage();

                $horario = substr((string) $ina['hora_inicio'], 0, 5) . ' - ' . substr((string) $ina['hora_fin'], 0, 5);
                $fila = [
                    (string) $ina['fecha'],
                    $horario,
                    (string) $ina['aprendiz_identificacion'],
                    $this->recortarTexto($ina['aprendiz_nombre'] . ' ' . $ina['aprendiz_apellido'], 28),
                    (string) $ina['ficha_codigo'],
                    $this->recortarTexto((string) $ina['competencia_nombre'], 30),
                    $this->recortarTexto($ina['instructor_nombre'] . ' ' . $ina['instructor_apellido'], 28),
                ];
                foreach ($fila as $i => $val) {
                    $this->pdfText($col2[$i], $this->pdfY, $val);
                }
                $this->pdfY -= self::LINE_HEIGHT;
            }
        }

        // ── Finalizar y ensamblar el PDF ────────────────────────────────
        return $this->pdfFinalize();
    }

    // ── Helpers PDF ─────────────────────────────────────────────────────

    /**
     * Recorta texto de forma segura considerando UTF-8 si mbstring está disponible.
     */
    private function recortarTexto(string $texto, int $max): string
    {
        return function_exists('mb_substr')
            ? mb_substr($texto, 0, $max, 'UTF-8')
            : substr($texto, 0, $max);
    }

    /**
     * Convierte UTF-8 a Windows-1252 y escapa paréntesis/backslash para
     * literales de cadena PDF.
     */
    private function pdfEscape(string $text): string
    {
        $conv = false;
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        }
        if ($conv === false) {
            $map = [
                'á' => "\xE1", 'é' => "\xE9", 'í' => "\xED", 'ó' => "\xF3", 'ú' => "\xFA",
                'Á' => "\xC1", 'É' => "\xC9", 'Í' => "\xCD", 'Ó' => "\xD3", 'Ú' => "\xDA",
                'ñ' => "\xF1", 'Ñ' => "\xD1", 'ü' => "\xFC", 'Ü' => "\xDC",
                '¿' => "\xBF", '¡' => "\xA1",
            ];
            $conv = strtr($text, $map);
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $conv);
    }

    private function pdfAddObject(string $body): int
    {
        $this->pdfObjCount++;
        $this->pdfObjects[$this->pdfObjCount] = $body;
        return $this->pdfObjCount;
    }

    private function pdfNewPage(): void
    {
        // Si ya hay un stream pendiente, ciérralo como página
        if (!empty($this->pdfPageIds)) {
            $this->pdfCommitPage();
        }
        $this->pdfStream = '';
        $this->pdfY = self::MARGIN_TOP;
    }

    private function pdfCommitPage(): void
    {
        // Stream object
        $streamBody = $this->pdfStream;
        $streamLen = strlen($streamBody);
        $streamObjId = $this->pdfAddObject(
            "<< /Length $streamLen >>\nstream\n$streamBody\nendstream"
        );
        // Page object
        $pageObjId = $this->pdfAddObject(
            "<< /Type /Page /Parent {$this->pdfPagesObjId} 0 R"
            . " /MediaBox [0 0 " . self::PAGE_W . " " . self::PAGE_H . "]"
            . " /Contents $streamObjId 0 R"
            . " /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>"
        );
        $this->pdfPageIds[] = $pageObjId;
    }

    private function pdfCheckPage(): void
    {
        if ($this->pdfY < self::MARGIN_BOTTOM) {
            $this->pdfCommitPage();
            $this->pdfStream = '';
            $this->pdfY = self::MARGIN_TOP;
        }
    }

    private function pdfSetFont(string $font, float $size): void
    {
        $this->pdfFont = $font;
        $this->pdfFontSize = $size;
    }

    private function pdfText(float $x, float $y, string $text): void
    {
        $escaped = $this->pdfEscape($text);
        $this->pdfStream .= "BT /{$this->pdfFont} {$this->pdfFontSize} Tf $x $y Td ($escaped) Tj ET\n";
    }

    private function pdfLine(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->pdfStream .= "0.6 w $x1 $y1 m $x2 $y2 l S\n";
    }

    private function pdfFinalize(): string
    {
        // Commit la última página en curso
        if ($this->pdfStream !== '') {
            $this->pdfCommitPage();
        }

        // Rellenar Pages (obj 2)
        $kids = implode(' ', array_map(fn(int $id): string => "$id 0 R", $this->pdfPageIds));
        $count = count($this->pdfPageIds);
        $this->pdfObjects[$this->pdfPagesObjId] =
            "<< /Type /Pages /Kids [$kids] /Count $count >>";

        // Rellenar Catalog (obj 1)
        $this->pdfObjects[1] =
            "<< /Type /Catalog /Pages {$this->pdfPagesObjId} 0 R >>";

        // Ensamblar el archivo
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        for ($i = 1; $i <= $this->pdfObjCount; $i++) {
            $offsets[$i] = strlen($out);
            $out .= "$i 0 obj\n" . $this->pdfObjects[$i] . "\nendobj\n";
        }

        // xref
        $xrefOffset = strlen($out);
        $out .= "xref\n";
        $out .= "0 " . ($this->pdfObjCount + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $this->pdfObjCount; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        // trailer
        $out .= "trailer\n";
        $out .= "<< /Size " . ($this->pdfObjCount + 1) . " /Root 1 0 R >>\n";
        $out .= "startxref\n";
        $out .= $xrefOffset . "\n";
        $out .= "%%EOF\n";

        return $out;
    }
}
