<?php

// ExcusaEvidenciaStorage — Guardado y validación de archivos de evidencia.
class ExcusaEvidenciaStorage
{
    public const TAMANO_MAXIMO = 2 * 1024 * 1024;

    private const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MIME_PERMITIDOS = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    public static function directorioBase(): string
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'excusas';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * @return array{ok: true, extension: string}|array{ok: false, error: string}
     */
    public static function validarSubida(?array $archivo): array
    {
        if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'extension' => ''];
        }

        if (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'No se pudo recibir el archivo de evidencia.'];
        }

        if ($archivo['size'] <= 0 || $archivo['size'] > self::TAMANO_MAXIMO) {
            return ['ok' => false, 'error' => 'La evidencia supera el tamaño máximo permitido (2 MB).'];
        }

        $ext = strtolower(pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES, true)) {
            return ['ok' => false, 'error' => 'Formato no permitido. Use PDF, JPG o PNG.'];
        }

        $tmp = $archivo['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp) || !is_file($tmp)) {
            return ['ok' => false, 'error' => 'Archivo de evidencia no válido.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!in_array($mime, self::MIME_PERMITIDOS, true)) {
            return ['ok' => false, 'error' => 'El tipo de archivo de evidencia no está permitido.'];
        }

        return ['ok' => true, 'extension' => $ext];
    }

    public static function guardar(int $excusaId, array $archivo, string $extension): ?string
    {
        if ($extension === '') {
            return null;
        }

        $nombre = sprintf('excusa_%d_%s.%s', $excusaId, bin2hex(random_bytes(8)), $extension);
        $destino = self::directorioBase() . DIRECTORY_SEPARATOR . $nombre;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            return null;
        }

        return $nombre;
    }

    public static function rutaSegura(string $nombreArchivo): ?string
    {
        $nombre = basename($nombreArchivo);
        if ($nombre === '' || preg_match('/[^a-zA-Z0-9._-]/', $nombre)) {
            return null;
        }

        $ruta = self::directorioBase() . DIRECTORY_SEPARATOR . $nombre;
        return is_file($ruta) ? $ruta : null;
    }

    public static function mimeParaDescarga(string $nombreArchivo): string
    {
        $ext = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            default => 'image/jpeg',
        };
    }
}
