<?php
require_once 'auth.php';
require_once '../config/Database.php';

// Apenas admins podem acessar
if (($_SESSION['admin_tipo'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$user_id = (int)($_GET['id'] ?? 0);

if (!$user_id) {
    echo json_encode(['erro' => 'ID inválido']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->prepare('
        SELECT 
            tipo_acao,
            ip,
            user_agent,
            criado_em,
            DATE_FORMAT(criado_em, "%d/%m/%Y às %H:%i") as criado_em_formatado
        FROM logs_acesso 
        WHERE usuario_id = ? 
        ORDER BY criado_em DESC 
        LIMIT 50
    ');
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($logs);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
