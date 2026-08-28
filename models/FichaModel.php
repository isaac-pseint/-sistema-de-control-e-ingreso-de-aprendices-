<?php


class FichaModel
{

    // busccar la ficha por id
    public function buscarPorId($ficha_id)
    {


        $sql = "SELECT id, hora_entrada, hora_salida FROM ficha WHERE id = ?";
        $params = [$ficha_id];

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
