<?php

class ControllerBase
{
    // Respuesta de éxito: siempre 200
    protected function ok(array $data = [], string $mensaje = ""): void
    {
        $response = ["ok" => true];
        if ($data)    $response["data"]    = $data;
        if ($mensaje) $response["mensaje"] = $mensaje;
        $this->json($response, 200);
    }

    protected function fail(string $error, int $status = 422): void
    {
        $this->json(["ok" => false, "error" => $error], $status);
    }

    protected function validarId(mixed $valor, string $campo): int
    {
        if (!is_string($valor) && !is_int($valor)) {
            $this->fail("El identificador de {$campo} no es válido.");
        }

        $valor = trim((string)$valor);
        if ($valor === '' || !ctype_digit($valor)) {
            $this->fail("El identificador de {$campo} no es válido.");
        }

        $normalizado = ltrim($valor, '0');
        $normalizado = $normalizado === '' ? '0' : $normalizado;
        $id = filter_var($normalizado, FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0 || $id > 2147483647) {
            $this->fail("El identificador de {$campo} no es válido.");
        }

        return $id;
    }

    protected function validarIdOpcional(mixed $valor, string $campo): ?int
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return null;
        }

        return $this->validarId($valor, $campo);
    }

    protected function esConflictoDeUnicidad(PDOException $e): bool
    {
        return $e->getCode() === '23000' && (int)($e->errorInfo[1] ?? 0) === 1062;
    }

    // Envía el JSON y termina el script
    private function json(array $data, int $status): void
    {
        http_response_code($status);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }


    protected function requireAuth(): void
    {
        if (!isset($_SESSION["user_id"])) {
            $this->fail("Debes iniciar sesión.", 401);
        }

        $userModel = new UserModel();
        if (!$userModel->sesionVigente(
            (int)$_SESSION["user_id"],
            (string)($_SESSION["user_rol"] ?? '')
        )) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            $this->fail("Tu cuenta ya no está activa o sus permisos cambiaron. Inicia sesión nuevamente.", 401);
        }
    }

    protected function requireRol(string $rol): void
    {
        $this->requireAuth();
        if (!isset($_SESSION["user_rol"]) || $_SESSION["user_rol"] !== $rol) {
            $this->fail("No tienes permisos para esta acción.", 403);
        }
    }
}
