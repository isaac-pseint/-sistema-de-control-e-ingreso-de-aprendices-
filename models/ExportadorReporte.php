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
            // 4 fuentes: 0=normal, 1=negrita, 2=negrita verde oscuro 12pt (título), 3=negrita blanca (encabezado), 4=negrita roja (alerta)
            . '<fonts count="5">'
            .   '<font><sz val="11"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="12"/><color rgb="FF1C7308"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="11"/><color rgb="FFC62828"/><name val="Calibri"/></font>'
            . '</fonts>'
            // 5 rellenos: 0=none, 1=gray125 (requerido), 2=verde SENA (encabezado), 3=verde muy suave (zebra), 4=sin relleno extra
            . '<fills count="4">'
            .   '<fill><patternFill patternType="none"/></fill>'
            .   '<fill><patternFill patternType="gray125"/></fill>'
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FF28960C"/></patternFill></fill>'
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F9F0"/></patternFill></fill>'
            . '</fills>'
            // 2 bordes: 0=ninguno, 1=bordes finos gris
            . '<borders count="2">'
            .   '<border/>'
            .   '<border>'
            .     '<left style="thin"><color rgb="FFD0D7DE"/></left>'
            .     '<right style="thin"><color rgb="FFD0D7DE"/></right>'
            .     '<top style="thin"><color rgb="FFD0D7DE"/></top>'
            .     '<bottom style="thin"><color rgb="FFD0D7DE"/></bottom>'
            .   '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            // 6 estilos: s0=base, s1=título, s2=encabezado tabla, s3=dato normal, s4=dato zebra, s5=alerta
            . '<cellXfs count="6">'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .   '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .   '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .   '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>'
            .   '<xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
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

        // Fila 1: Título (s=1 verde oscuro negrita)
        $rows .= '<row r="1">'
            . $this->xlsxCellStr('A1', 'REPORTE DE ASISTENCIA POR APRENDIZ - SENA', 1)
            . $this->xlsxCellStr('E1', $rango, 1)
            . '</row>';

        // Fila 3: Encabezados (s=2 fondo verde SENA, fuente blanca)
        $headers = [
            'A' => 'Identificación', 'B' => 'Aprendiz', 'C' => 'Ficha',
            'D' => 'Programa', 'E' => 'Asistencias', 'F' => 'A Tiempo',
            'G' => 'Retardos', 'H' => 'Tiempo Retardo (min)',
            'I' => 'Tiempo Retardo (h/m)', 'J' => 'Salidas Anticipadas',
            'K' => 'Total Inasistencias', 'L' => 'Días Inasistencia',
        ];
        $headerCells = '';
        foreach ($headers as $col => $label) {
            $headerCells .= $this->xlsxCellStr($col . '3', $label, 2);
        }
        $rows .= '<row r="3">' . $headerCells . '</row>';

        // Filas de datos con zebra striping y alertas semánticas
        $r = 4;
        foreach ($resumen as $ap) {
            $mins = (int) $ap['minutos_retardo'];
            $hm = $mins >= 60
                ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm'
                : $mins . ' min';

            // Zebra: filas pares s=4, impares s=3
            $base = ($r % 2 === 0) ? 4 : 3;
            $retardos = (int) $ap['total_retardos'];
            $inasist  = (int) $ap['total_inasistencias'];
            // Alerta retardos (G, H, I)
            $sRet = ($retardos > 0 || $mins > 0) ? 5 : $base;
            // Alerta inasistencias (K, L)
            $sIna = ($inasist > 0) ? 5 : $base;

            $rows .= '<row r="' . $r . '">'
                . $this->xlsxCellStr('A' . $r, (string) $ap['identificacion'], $base)
                . $this->xlsxCellStr('B' . $r, $ap['nombre'] . ' ' . $ap['apellido'], $base)
                . $this->xlsxCellStr('C' . $r, (string) $ap['ficha_codigo'], $base)
                . $this->xlsxCellStr('D' . $r, $ap['programa_nombre'], $base)
                . $this->xlsxCellNum('E' . $r, (int) $ap['total_asistencias'], $base)
                . $this->xlsxCellNum('F' . $r, (int) $ap['total_a_tiempo'], $base)
                . $this->xlsxCellNum('G' . $r, $retardos, $sRet)
                . $this->xlsxCellNum('H' . $r, $mins, $sRet)
                . $this->xlsxCellStr('I' . $r, $hm, $sRet)
                . $this->xlsxCellNum('J' . $r, (int) $ap['total_salidas_anticipadas'], $base)
                . $this->xlsxCellNum('K' . $r, $inasist, $sIna)
                . $this->xlsxCellNum('L' . $r, (int) $ap['dias_inasistencia'], $sIna)
                . '</row>';
            $r++;
        }

        return $this->xlsxSheetWrap($rows);
    }

    private function xlsxSheet2(array $inasistencias): string
    {
        $rows = '';

        // Fila 1: Título (s=1 verde oscuro negrita)
        $rows .= '<row r="1">'
            . $this->xlsxCellStr('A1', 'DETALLE DE DÍAS DE INASISTENCIA E INSTRUCTOR', 1)
            . '</row>';

        // Fila 3: Encabezados (s=2 fondo verde SENA, fuente blanca)
        $headers = [
            'A' => 'Fecha', 'B' => 'Horario', 'C' => 'Identificación',
            'D' => 'Aprendiz', 'E' => 'Ficha', 'F' => 'Competencia',
            'G' => 'Instructor Encargado',
        ];
        $headerCells = '';
        foreach ($headers as $col => $label) {
            $headerCells .= $this->xlsxCellStr($col . '3', $label, 2);
        }
        $rows .= '<row r="3">' . $headerCells . '</row>';

        // Filas de datos con zebra striping; columna A (Fecha) siempre en alerta
        $r = 4;
        foreach ($inasistencias as $ina) {
            $horario = substr((string) $ina['hora_inicio'], 0, 5) . ' - ' . substr((string) $ina['hora_fin'], 0, 5);
            $base = ($r % 2 === 0) ? 4 : 3;
            $rows .= '<row r="' . $r . '">'
                . $this->xlsxCellStr('A' . $r, (string) $ina['fecha'], 5)
                . $this->xlsxCellStr('B' . $r, $horario, $base)
                . $this->xlsxCellStr('C' . $r, (string) $ina['aprendiz_identificacion'], $base)
                . $this->xlsxCellStr('D' . $r, $ina['aprendiz_nombre'] . ' ' . $ina['aprendiz_apellido'], $base)
                . $this->xlsxCellStr('E' . $r, (string) $ina['ficha_codigo'], $base)
                . $this->xlsxCellStr('F' . $r, (string) $ina['competencia_nombre'], $base)
                . $this->xlsxCellStr('G' . $r, $ina['instructor_nombre'] . ' ' . $ina['instructor_apellido'], $base)
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
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
    }

    private function xlsxCellNum(string $ref, int $value, int $style = 0): string
    {
        return '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
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

        // Ancho útil del contenido
        $contentW = self::PAGE_W - 2 * self::MARGIN_LEFT; // 762

        // ── 1) Banner Superior Institucional ────────────────────────────
        $bannerH = 42.0;
        $bannerY = $this->pdfY - 26;
        $this->pdfRect(self::MARGIN_LEFT, $bannerY, $contentW, $bannerH, 0.16, 0.59, 0.05);

        // Título principal en blanco dentro del banner
        $this->pdfSetFont('F2', 14);
        $this->pdfText(self::MARGIN_LEFT + 14, $bannerY + 24, 'SENA - Sistema de Control de Ingreso de Aprendices', 1.0, 1.0, 1.0);

        // Subtítulo en blanco suave
        $this->pdfSetFont('F1', 10);
        $this->pdfText(self::MARGIN_LEFT + 14, $bannerY + 8, 'Reporte de Asistencias, Retardos e Inasistencias', 0.92, 0.98, 0.90);

        $this->pdfY = $bannerY - 16;

        // Fecha de generación y rango en gris oscuro
        $this->pdfSetFont('F1', 9);
        $this->pdfText(self::MARGIN_LEFT, $this->pdfY, 'Fecha de generación: ' . date('Y-m-d H:i'), 0.35, 0.40, 0.45);
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
            $this->pdfText(self::MARGIN_LEFT, $this->pdfY, $rango, 0.35, 0.40, 0.45);
            $this->pdfY -= self::LINE_HEIGHT;
        }

        $this->pdfY -= self::LINE_HEIGHT * 0.5;

        // ── 2) Caja de Resumen de Totales Generales (Grilla 2×3) ────────
        $boxH = 48.0;
        $boxY = $this->pdfY - $boxH;
        // Fondo verde muy suave
        $this->pdfRect(self::MARGIN_LEFT, $boxY, $contentW, $boxH, 0.93, 0.97, 0.92);
        // Barra lateral izquierda verde SENA como acento
        $this->pdfRect(self::MARGIN_LEFT, $boxY, 4, $boxH, 0.16, 0.59, 0.05);

        // Título del resumen
        $this->pdfSetFont('F2', 9);
        $this->pdfText(self::MARGIN_LEFT + 12, $boxY + $boxH - 12, 'RESUMEN DE TOTALES GENERALES', 0.11, 0.45, 0.03);

        // 6 totales distribuidos en 3 columnas × 2 filas
        $this->pdfSetFont('F1', 8);
        $colTot = [
            self::MARGIN_LEFT + 12,
            self::MARGIN_LEFT + 260,
            self::MARGIN_LEFT + 520,
        ];
        $totLabels = [
            'Aprendices: ' . ($totales['total_aprendices'] ?? 0),
            'Retardos: ' . ($totales['total_retardos'] ?? 0),
            'Salidas Anticipadas: ' . ($totales['total_salidas_anticipadas'] ?? 0),
            'Asistencias: ' . ($totales['total_asistencias'] ?? 0),
            'Min. Retardo acumulados: ' . ($totales['minutos_retardo'] ?? 0),
            'Inasistencias: ' . ($totales['total_inasistencias'] ?? 0),
        ];
        // Fila 1 (índices 0,1,2)
        $row1Y = $boxY + $boxH - 26;
        for ($t = 0; $t < 3; $t++) {
            $this->pdfText($colTot[$t], $row1Y, $totLabels[$t], 0.15, 0.18, 0.20);
        }
        // Fila 2 (índices 3,4,5)
        $row2Y = $row1Y - 14;
        for ($t = 3; $t < 6; $t++) {
            $this->pdfText($colTot[$t - 3], $row2Y, $totLabels[$t], 0.15, 0.18, 0.20);
        }

        $this->pdfY = $boxY - 18;

        // ── 3) Sección 1: Resumen por Aprendiz ─────────────────────────
        $this->pdfCheckPage();
        $this->pdfSetFont('F2', 11);
        $this->pdfText(self::MARGIN_LEFT, $this->pdfY, '1. Resumen por Aprendiz', 0.14, 0.52, 0.04);
        $this->pdfY -= self::LINE_HEIGHT * 1.5;

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

        // Franja de fondo verde SENA para encabezado
        $this->pdfCheckPage();
        $this->pdfRect(self::MARGIN_LEFT, $this->pdfY - 4, $contentW, 16, 0.16, 0.59, 0.05);
        $this->pdfSetFont('F2', 8);
        foreach ($hdr1 as $i => $h) {
            $this->pdfText($col1[$i], $this->pdfY, $h, 1.0, 1.0, 1.0);
        }
        $this->pdfY -= self::LINE_HEIGHT + 4;

        $this->pdfSetFont('F1', 7);
        if (empty($resumen)) {
            $this->pdfCheckPage();
            $this->pdfSetFont('F1', 9);
            $this->pdfText(self::MARGIN_LEFT, $this->pdfY, 'Sin registros para los filtros seleccionados.');
            $this->pdfY -= self::LINE_HEIGHT;
        } else {
            foreach ($resumen as $idx => $ap) {
                $this->pdfCheckPage();

                // Zebra striping: filas pares (idx impar 0-indexed) con fondo suave
                if ($idx % 2 === 1) {
                    $this->pdfRect(self::MARGIN_LEFT, $this->pdfY - 3.5, $contentW, 14, 0.95, 0.98, 0.95);
                }

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

                $retardos = (int) $ap['total_retardos'];
                $minRet   = (int) $ap['minutos_retardo'];
                $inasist  = (int) $ap['total_inasistencias'];

                foreach ($fila as $i => $val) {
                    // Semántica: retardos/tiempo retardo en rojo-naranja si > 0
                    if (($i === 4 || $i === 5) && ($retardos > 0 || $minRet > 0)) {
                        $this->pdfText($col1[$i], $this->pdfY, $val, 0.78, 0.16, 0.16);
                    // Semántica: días inasistencia y total inasistencias en rojo si > 0
                    } elseif (($i === 7 || $i === 8) && $inasist > 0) {
                        $this->pdfText($col1[$i], $this->pdfY, $val, 0.78, 0.16, 0.16);
                    } else {
                        $this->pdfText($col1[$i], $this->pdfY, $val);
                    }
                }
                $this->pdfY -= self::LINE_HEIGHT;
            }
        }

        $this->pdfY -= self::LINE_HEIGHT;

        // ── 4) Sección 2: Detalle de Inasistencias ──────────────────────
        $this->pdfCheckPage();
        $this->pdfSetFont('F2', 11);
        $this->pdfText(self::MARGIN_LEFT, $this->pdfY, '2. Días Específicos de Inasistencia con Instructor', 0.14, 0.52, 0.04);
        $this->pdfY -= self::LINE_HEIGHT * 1.5;

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

        // Franja de fondo verde SENA para encabezado
        $this->pdfCheckPage();
        $this->pdfRect(self::MARGIN_LEFT, $this->pdfY - 4, $contentW, 16, 0.16, 0.59, 0.05);
        $this->pdfSetFont('F2', 8);
        foreach ($hdr2 as $i => $h) {
            $this->pdfText($col2[$i], $this->pdfY, $h, 1.0, 1.0, 1.0);
        }
        $this->pdfY -= self::LINE_HEIGHT + 4;

        $this->pdfSetFont('F1', 7);
        if (empty($inasistencias)) {
            $this->pdfCheckPage();
            $this->pdfSetFont('F1', 9);
            $this->pdfText(self::MARGIN_LEFT, $this->pdfY, 'Sin inasistencias registradas en el periodo seleccionado.');
            $this->pdfY -= self::LINE_HEIGHT;
        } else {
            foreach ($inasistencias as $idx => $ina) {
                $this->pdfCheckPage();

                // Zebra striping
                if ($idx % 2 === 1) {
                    $this->pdfRect(self::MARGIN_LEFT, $this->pdfY - 3.5, $contentW, 14, 0.95, 0.98, 0.95);
                }

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
                    // Semántica: columna Fecha en rojo oscuro
                    if ($i === 0) {
                        $this->pdfText($col2[$i], $this->pdfY, $val, 0.75, 0.15, 0.15);
                    } else {
                        $this->pdfText($col2[$i], $this->pdfY, $val);
                    }
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

    /**
     * Dibuja un rectángulo relleno con color RGB y restablece el color de relleno a negro.
     */
    private function pdfRect(float $x, float $y, float $w, float $h, float $r, float $g, float $b): void
    {
        $this->pdfStream .= sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f 0 0 0 rg\n", $r, $g, $b, $x, $y, $w, $h);
    }

    /**
     * Emite texto con color RGB opcional (por defecto gris oscuro) y restablece a negro.
     */
    private function pdfText(float $x, float $y, string $text, float $r = 0.15, float $g = 0.18, float $b = 0.20): void
    {
        $escaped = $this->pdfEscape($text);
        $this->pdfStream .= sprintf(
            "BT %.3f %.3f %.3f rg /%s %.1f Tf %.2f %.2f Td (%s) Tj 0 0 0 rg ET\n",
            $r, $g, $b, $this->pdfFont, $this->pdfFontSize, $x, $y, $escaped
        );
    }

    /**
     * Dibuja una línea con color RGB y grosor opcionales y restablece el color de trazo a negro.
     */
    private function pdfLine(float $x1, float $y1, float $x2, float $y2, float $r = 0.16, float $g = 0.59, float $b = 0.05, float $width = 0.8): void
    {
        $this->pdfStream .= sprintf("%.2f w %.3f %.3f %.3f RG %.2f %.2f m %.2f %.2f l S 0 0 0 RG\n", $width, $r, $g, $b, $x1, $y1, $x2, $y2);
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
