<?php

class AsistenciaModel
{


    // busccar si hay un registro de asistencia para el dia de hoy
    public function buscarPorCodigoFecha($codigo_llavero, $fecha)
    {


        $sql = "SELECT id, estado, hora_salida from asistencia where codigo_llavero = ? AND fecha = ?";
        $params = [$codigo_llavero, $fecha];

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }


    public function registrarSalida(int $id_asistencia, string $hora_salida, int $minutos_anticipacion): bool
    {
        $sql = "UPDATE asistencia
                SET hora_salida = ?, estado = 'Completado', minutos_anticipacion = ?
                WHERE id = ?";

        $stmt = Database::conn()->prepare($sql);

        return $stmt->execute([
            $hora_salida,
            $minutos_anticipacion,
            $id_asistencia
        ]);
    }


    public function registrarAsistencia($fecha, $hora_entrada, $usuario_id, $codigo_llavero, $minutos_retardo, $minutos_anticipacion)
    {
        $sql = "INSERT INTO asistencia(fecha,hora_entrada, usuario_id, estado, codigo_llavero, minutos_retardo, minutos_anticipacion) VALUES(
        ?,
        ?,
        ?,
        'Activo',
        ?,
        ?,
        ?)
        ";

        $stmt = Database::conn()->prepare($sql);

        return $stmt->execute([
            $fecha,
            $hora_entrada,
            $usuario_id,
            $codigo_llavero,
            $minutos_retardo,
            $minutos_anticipacion
        ]);
    }


    public function listar($id): array
    {
        $sql = "SELECT fecha,
                       DATE_FORMAT(hora_entrada, '%r') AS hora_entrada,
                       DATE_FORMAT(hora_salida, '%r') AS hora_salida,
                       estado, minutos_retardo, minutos_anticipacion, codigo_llavero
                FROM asistencia WHERE Usuario_id = ? ORDER BY fecha DESC, hora_entrada DESC";
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }
}
