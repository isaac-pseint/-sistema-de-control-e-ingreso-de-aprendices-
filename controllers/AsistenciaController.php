<?php

class AsistenciaController extends ControllerBase
{


    private UserModel $userModel;
    private AsistenciaModel $asistenciaModel;

    private FichaModel $ficha_model;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->asistenciaModel = new AsistenciaModel();
        $this->ficha_model = new FichaModel();
    }

    public function registrarEntrada()
    {

        $codigo_llavero = trim($_POST['codigo_llavero'] ?? '');

        if (!$codigo_llavero) {
            $this->fail("El codigo del llavero no puede estar vacio.");
            return;
        }


        // confirmar que el llavero existe y que el usuario este activo
        $usuario = $this->userModel->buscarPorCodigoLlavero($codigo_llavero);


        if (!$usuario) {
            $this->fail("No se encontro un usuario con ese codigo.");
            return;
        }

        if ($usuario['estado'] == 'Inactivo') {
            $this->fail("El Usuario esta inactivo.");
            return;
        }

        // buscar si ya tiene un registro para el dia de hoy
        $asistencia = $this->asistenciaModel->buscarPorCodigoFecha($codigo_llavero, date('Y-m-d'));

        if ($asistencia) {
            $this->fail("El usuario ya registro su entrada el dia de hoy.");
            return;
        }

        // registrar la asistencia

        // buscar la hora de entrada de la ficha del usuario
        $ficha = $this->ficha_model->buscarPorId($usuario['Ficha_id']);


        if (!$ficha) {
            $this->fail("El usuario no tiene ficha.");
            return;
        }


        // comparar la hora de ingreso con la hora de entrada de la ficha
        $minutos_anticipacion = 0;
        $minutos_retardo = 0;

        $hora_entrada = date("H:i");
        $hora_ficha = date("H:i", strtotime($ficha['hora_entrada']));

        $diferencia_segundos = strtotime($hora_entrada) - strtotime($hora_ficha);
        $diferencia_minutos = $diferencia_segundos / 60;

        // Llegó después de la hora de entrada → retardo (minutos).
        if ($diferencia_minutos > 0) {
            $minutos_retardo = $diferencia_minutos;
        }

        // La anticipación es de la salida (salida temprana), no aplica
        // al registrar la entrada. Se calcula recién al registrar la salida.
        $minutos_anticipacion = 0;

        // Registrar entrada solo dentro de la ventana de la ficha:
        // rechazar si ya pasó la hora de salida.
        $hora_salida = date("H:i", strtotime($ficha['hora_salida']));

        if ($hora_entrada > $hora_salida) {
            $this->fail("Ya pasó la hora de salida de la ficha. No se puede registrar la entrada.");
            return;
        }

        // insertar en la bd

        try {
            $creado = $this->asistenciaModel->registrarAsistencia(date('Y-m-d'), $hora_entrada, (int)$usuario['id'], $codigo_llavero, (int)$minutos_retardo, (int)$minutos_anticipacion);

            if ($creado) {
                $this->ok([], "Entrada registrada correctamente.");
            } else {
                $this->fail("No se pudo registrar la asistencia.");
            }
        } catch (PDOException $e) {
            $this->fail("No se pudo insertar la asistencia, verifique los datos ingresados");
        }
    }

    public function registrarSalida()
    {
        $codigo_llavero = trim($_POST['codigo_llavero'] ?? '');

        if (!$codigo_llavero) {
            $this->fail("El codigo del llavero no puede estar vacio.");
            return;
        }

        // confirmar que el llavero existe y que el usuario este activo
        $usuario = $this->userModel->buscarPorCodigoLlavero($codigo_llavero);

        if (!$usuario) {
            $this->fail("No se encontro un usuario con ese codigo.");
            return;
        }

        if ($usuario['estado'] == 'Inactivo') {
            $this->fail("El Usuario esta inactivo.");
            return;
        }

        // buscar el registro de asistencia del dia de hoy
        $asistencia = $this->asistenciaModel->buscarPorCodigoFecha($codigo_llavero, date('Y-m-d'));

        if (!$asistencia) {
            $this->fail("No hay entrada registrada hoy para registrar la salida.");
            return;
        }

        if ($asistencia['estado'] == 'Completado') {
            $this->fail("La salida ya fue registrada hoy.");
            return;
        }

        // buscar la ficha del usuario para la ventana de referencia
        $ficha = $this->ficha_model->buscarPorId($usuario['Ficha_id']);

        if (!$ficha) {
            $this->fail("El usuario no tiene ficha.");
            return;
        }

        $hora_salida = date("H:i");
        $hora_entrada_ficha = date("H:i", strtotime($ficha['hora_entrada']));

        // validacion de coherencia: no registrar salida antes de la hora de entrada de la ficha
        if ($hora_salida < $hora_entrada_ficha) {
            $this->fail("No se puede registrar la salida antes de la hora de entrada.");
            return;
        }

        // minutos de anticipacion por salida temprana (antes de la hora de salida de la ficha)
        $minutos_anticipacion = 0;
        $hora_salida_ficha = date("H:i", strtotime($ficha['hora_salida']));

        $diferencia_segundos = strtotime($hora_salida_ficha) - strtotime($hora_salida);
        $diferencia_minutos = $diferencia_segundos / 60;

        if ($diferencia_minutos > 0) {
            $minutos_anticipacion = $diferencia_minutos;
        }

        // actualizar en la bd
        try {
            $actualizado = $this->asistenciaModel->registrarSalida((int)$asistencia['id'], $hora_salida, (int)$minutos_anticipacion);

            if ($actualizado) {
                $this->ok([], "Salida registrada correctamente.");
            } else {
                $this->fail("No se pudo registrar la salida.");
            }
        } catch (PDOException $e) {
            $this->fail("No se pudo registrar la salida, verifique los datos ingresados");
        }
    }

    public function listar()
    {
        try {

            $this->requireRol('Aprendiz');
            $id = $_SESSION['user_id'] ?? null;
            $asistencias = $this->asistenciaModel->listar($id);

            $this->ok(['asistencias' => $asistencias]);
        } catch (PDOException $e) {
            $this->fail("Error al listar las asistencias.");
        }
    }
}
