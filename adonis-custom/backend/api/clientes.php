<?php
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../utils/response.php';

$clienteModel = new Cliente();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['telefone'])) {
            $cliente = $clienteModel->buscarPorTelefone($_GET['telefone']);
            if ($cliente) {
                successResponse('Cliente encontrado', $cliente);
            } else {
                notFoundResponse('Cliente não encontrado');
            }
        } else {
            errorResponse('Parâmetro telefone é obrigatório', 400);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        break;
}
?>
