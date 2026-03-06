<?php
/**
 * ADONIS LUTHIERIA — ROTEADOR PRINCIPAL
 * Redireciona acessos da raiz para os módulos corretos
 */

// Se vier de /admin ou /backend → vai pro painel
if (isset($_GET['admin']) || strpos($_SERVER['REQUEST_URI'], '/admin') !== false) {
    header('Location: /backend/admin/login.php');
    exit;
}

// Se vier de /api → retorna JSON de erro (API não disponível aqui)
if (strpos($_SERVER['REQUEST_URI'], '/api') !== false) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['erro' => 'Endpoint não encontrado']);
    exit;
}

// Qualquer outro acesso → formulário público
header('Location: /frontend/index.php');
exit;
