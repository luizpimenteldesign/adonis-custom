<?php
/**
 * API - ATUALIZAR STATUS DO PEDIDO
 * Versão: 1.0
 * Data: 27/02/2026
 */

require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id     = isset($input['id'])     ? (int)$input['id']          : 0;
$status = isset($input['status']) ? trim($input['status'])     : '';

$status_validos = ['Pre-OS', 'Em analise', 'Orcada', 'Aguardando aprovacao', 'Aprovada', 'Reprovada', 'Cancelada'];

if ($id <= 0 || !in_array($status, $status_validos)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros inválidos']);
    exit;
}

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        UPDATE pre_os 
        SET status = :status, atualizado_em = NOW() 
        WHERE id = :id
    ");
    $stmt->execute([':status' => $status, ':id' => $id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado']);
        exit;
    }

    // Registrar na auditoria
    $stmt_audit = $conn->prepare("
        INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, valor_novo)
        VALUES (:uid, 'pre_os', :eid, 'edicao', :status)
    ");
    $stmt_audit->execute([
        ':uid'    => $_SESSION['admin_id'],
        ':eid'    => $id,
        ':status' => $status
    ]);

    echo json_encode([
        'sucesso'      => true,
        'atualizado_em' => date('d/m/Y H:i')
    ]);

} catch (PDOException $e) {
    error_log('Erro ao atualizar status: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno']);
}
