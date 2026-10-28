<?php

// HorarioController — Importación de horarios desde Excel (API JSON).
// Único flujo: se sube el .xlsx con un botón, se valida TODO de una vez y,
// solo si todo está bien, se crean las sesiones en una transacción.
class HorarioController extends ControllerBase
{
    private SesionModel $sesionModel;
    private FichaModel $fichaModel;

    public function __construct()
    {
        $this->sesionModel = new SesionModel();
        $this->fichaModel = new FichaModel();
    }

    public function importar(): void
    {
        $this->requireRol('Administrador');

        // 1. Subida controlada: existe, no supera 2 MB, solo .xlsx, y es un
        //    archivo real (no un nombre falso forjado en la petición).
        if (empty($_FILES['horario']) || ($_FILES['horario']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->fail('Debe seleccionar un archivo .xlsx.');
        }

        $archivo = $_FILES['horario'];

        if ($archivo['size'] <= 0 || $archivo['size'] > LectorHorarioXlsx::TAMANO_MAXIMO) {
            $this->fail('El archivo supera el tamaño máximo permitido (2 MB).');
        }

        if (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            $this->fail('El archivo debe tener extensión .xlsx.');
        }

        $rutaTemporal = $archivo['tmp_name'];
        if (!is_uploaded_file($rutaTemporal) || !is_file($rutaTemporal)) {
            $this->fail('No se pudo recibir el archivo.');
        }

        // 2. Lectura del Excel (trabaja sobre la copia temporal y la limpia).
        $lector = new LectorHorarioXlsx();
        $okLectura = $lector->leer($rutaTemporal);
        @unlink($rutaTemporal);

        if (!$okLectura) {
            $this->fail($lector->errores ? implode(' ', $lector->errores) : 'No se pudo leer el archivo.');
        }

        if ($lector->inconsistencias) {
            $this->fail('El horario contiene bloques con más de un instructor para un mismo día. Revise el archivo.');
        }

        // 3. Ficha debe existir y estar activa.
        $ficha = $this->fichaModel->buscarPorCodigo((string)$lector->fichaCodigo);
        if (!$ficha) {
            $this->fail('La ficha del horario no existe en la base de datos.');
        }
        if (($ficha['estado'] ?? '') !== 'Activo') {
            $this->fail('La ficha del horario no está activa.');
        }

        // 4. Instructores: TODOS deben estar en la base de datos. Si falta
        //    uno, se bloquea la importación completa con un único mensaje.
        $instructores = $this->fichaModel->listarInstructores();
        $mapaInstructores = [];
        foreach ($instructores as $usuario) {
            $clave = LectorHorarioXlsx::normalizarNombre($usuario['nombre'] . ' ' . $usuario['apellido']);
            $mapaInstructores[$clave] = (int)$usuario['id'];
        }

        $clases = $lector->clases;
        foreach ($clases as $clase) {
            $clave = LectorHorarioXlsx::normalizarNombre($clase['instructor']);
            if (!isset($mapaInstructores[$clave])) {
                $this->fail('Todos los instructores deben estar en la base de datos.');
            }
        }

        // 5. Bloques horarios de la jornada de la ficha.
        $bloquesJornada = $this->sesionModel->bloquesDeJornada((int)$ficha['jornada_id']);
        $bloquePorOrden = [];
        foreach ($bloquesJornada as $bloque) {
            $bloquePorOrden[(int)$bloque['orden']] = $bloque;
        }
        if (!isset($bloquePorOrden[1], $bloquePorOrden[2])) {
            $this->fail('La jornada de la ficha no tiene los dos bloques horarios definidos.');
        }

        // 6. Validación completa ANTES de escribir nada. Se hace antes de abrir
        //    la transacción para que los SELECT vean sólo datos confirmados
        //    (InnoDB en REPEATABLE READ no ve filas insertadas en la misma
        //    transacción). Cualquier conflicto aborta TODO con un rollback.
        $fichaId = (int)$ficha['id'];
        $aCrear = [];
        $omitidas = [];
        $vistos = [];

        foreach ($clases as $clase) {
            $bloque = $bloquePorOrden[(int)$clase['bloque']];
            $horaInicio = substr((string)$bloque['hora_inicio'], 0, 5);
            $horaFin = substr((string)$bloque['hora_fin'], 0, 5);
            $instructorId = $mapaInstructores[LectorHorarioXlsx::normalizarNombre($clase['instructor'])];

            // 6a. Duplicado dentro del mismo archivo (misma ficha, fecha y hora).
            $clave = $clase['fecha'] . '|' . $horaInicio;
            if (isset($vistos[$clave])) {
                $omitidas[] = ['fecha' => $clase['fecha'], 'motivo' => 'Duplicado en el archivo'];
                continue;
            }
            $vistos[$clave] = true;

            // 6b. Ya existe una sesión de la ficha en esa fecha con la misma
            //     hora de inicio (cualquier instructor/estado) → re-import idempotente.
            if ($this->sesionModel->existeSesion($fichaId, $clase['fecha'], $horaInicio)) {
                $omitidas[] = ['fecha' => $clase['fecha'], 'motivo' => 'Ya existe'];
                continue;
            }

            // 6c. Conflicto real: otra sesión ACTIVA se solapa en el rango de
            //     horas con esta ficha (aunque sea otro instructor) o con este
            //     instructor (aunque sea en otra ficha).
            if ($this->sesionModel->existeSuperposicion($fichaId, $instructorId, $clase['fecha'], $horaInicio, $horaFin)) {
                $this->fail("La sesión del {$clase['fecha']} de {$horaInicio} a {$horaFin} se superpone con una sesión activa de la misma ficha o del mismo instructor. Revise el horario, no se importó nada.");
            }

            $aCrear[] = [
                'fecha' => $clase['fecha'],
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'instructor_id' => $instructorId,
                'bloque_id' => (int)$bloque['id']
            ];
        }

        // 7. Inserción ya validada, en una única transacción.
        $pdo = Database::conn();
        $creadas = 0;

        try {
            $pdo->beginTransaction();

            if (!$this->fichaModel->estaActiva($fichaId, true)) {
                $pdo->rollBack();
                $this->fail('La ficha del horario dejó de estar activa. No se importó nada.');
            }

            $instructoresAValidar = array_unique(array_column($aCrear, 'instructor_id'));
            sort($instructoresAValidar, SORT_NUMERIC);
            foreach ($instructoresAValidar as $instructorId) {
                if (!$this->fichaModel->esInstructorActivo((int)$instructorId, true)) {
                    $pdo->rollBack();
                    $this->fail('Un instructor del horario dejó de estar activo. No se importó nada.');
                }
            }

            foreach ($aCrear as $clase) {
                // 7a. Se vuelve a comprobar YA DENTRO de la transacción: aquí el
                //     FOR UPDATE de existeSuperposicion() sí bloquea, así que dos
                //     importaciones simultáneas no pueden insertar ambas. También
                //     cubre el duplicado exacto, que se solapa consigo mismo.
                if ($this->sesionModel->existeSuperposicion($fichaId, $clase['instructor_id'], $clase['fecha'], $clase['hora_inicio'], $clase['hora_fin'])) {
                    $pdo->rollBack();
                    $this->fail("La sesión del {$clase['fecha']} de {$clase['hora_inicio']} a {$clase['hora_fin']} se superpone con una sesión activa creada mientras se importaba. No se importó nada.");
                }

                $this->sesionModel->crear(
                    $fichaId,
                    null,
                    $clase['instructor_id'],
                    $clase['fecha'],
                    $clase['hora_inicio'],
                    $clase['hora_fin'],
                    $clase['bloque_id']
                );
                $creadas++;
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->fail('Error en la base de datos al importar el horario.');
        }

        $this->ok(
            ['creadas' => $creadas, 'omitidas' => $omitidas, 'festivos' => count($lector->festivos)],
            'Horario importado correctamente.'
        );
    }
}