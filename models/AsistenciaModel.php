<?php

class AsistenciaModel
{


    // busccar si hay un registro de asistencia para el dia de hoy
    public function buscarPorCodigoFecha($codigo_llavero, $fecha)
    {


        $sql = "SELECT id from asistencia where codigo_llavero = ? AND fecha = ?";
        $params = [$codigo_llavero, $fecha];

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
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
}
