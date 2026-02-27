<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../utils/response.php';

class AuthController {
    private $usuarioModel;

    public function __construct() {
        $this->usuarioModel = new Usuario();
    }

    public function login() {
        $dados = json_decode(file_get_contents("php://input"), true);

        if (!isset($dados['email']) || !isset($dados['senha'])) {
            errorResponse('Email e senha são obrigatórios', 400);
        }

        $resultado = $this->usuarioModel->login($dados['email'], $dados['senha']);

        if ($resultado['success']) {
            successResponse('Login realizado com sucesso', $resultado['usuario']);
        } else {
            errorResponse('Credenciais inválidas', 401);
        }
    }

    public function validarToken() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? null;

        if (!$token) {
            unauthorizedResponse('Token não fornecido');
        }

        // Remover "Bearer " se existir
        $token = str_replace('Bearer ', '', $token);

        $usuario = $this->usuarioModel->validarToken($token);

        if ($usuario) {
            successResponse('Token válido', $usuario);
        } else {
            unauthorizedResponse('Token inválido ou expirado');
        }
    }

    public function logout() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? null;

        if ($token) {
            $token = str_replace('Bearer ', '', $token);
            // Implementar revogação de token se necessário
        }

        successResponse('Logout realizado com sucesso');
    }
}
?>
