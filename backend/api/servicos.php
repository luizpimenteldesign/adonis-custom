<?php
require_once __DIR__ . '/../controllers/ServicoController.php';

$controller = new ServicoController();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $controller->buscarPorId($_GET['id']);
        } else {
            $controller->listar();
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        break;
}
?>
