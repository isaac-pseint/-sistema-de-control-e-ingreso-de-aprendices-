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
    }

    protected function requireRol(string $rol): void
    {
        $this->requireAuth();
        if (!isset($_SESSION["user_rol"]) || $_SESSION["user_rol"] !== $rol) {
            $this->fail("No tienes permisos para esta acción.", 403);
        }
    }
}
