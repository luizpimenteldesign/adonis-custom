<?php
/**
 * API - HISTÓRICO DE STATUS DO PEDIDO
 * Versão: 1.0
 * Data: 27/02/2026
 */

require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido']);
    exit;
}

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT 
            h.status,
            h.valor_orcamento,
            h.motivo,
            h.criado_em,
            a.nome as admin_nome
        FROM status_historico h
        LEFT JOIN admins a ON h.admin_id = a.id
        WHERE h.pre_os_id = :id
        ORDER BY h.criado_em ASC
    ");
    $stmt->execute([':id' => $id]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['sucesso' => true, 'historico' => $historico]);

} catch (PDOException $e) {
    error_log('Erro ao buscar histórico: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno']);
}
