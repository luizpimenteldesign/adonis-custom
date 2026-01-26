<?php
require_once __DIR__ . '/../controllers/AuthController.php';

$controller = new AuthController();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $controller->login();
        break;

    case 'GET':
        $controller->validarToken();
        break;

    case 'DELETE':
        $controller->logout();
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        break;
}
?>
