<?php
/**
 * API - ATUALIZAR STATUS DO PEDIDO
 * Versão: 2.0
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

$input  = json_decode(file_get_contents('php://input'), true);
$id     = isset($input['id'])     ? (int)$input['id']      : 0;
$status = isset($input['status']) ? trim($input['status']) : '';
$valor  = isset($input['valor_orcamento']) ? $input['valor_orcamento'] : null;
$motivo = isset($input['motivo']) ? trim($input['motivo']) : null;

$status_validos = ['Pre-OS','Em analise','Orcada','Aguardando aprovacao','Aprovada','Reprovada','Cancelada'];

if ($id <= 0 || !in_array($status, $status_validos)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros inválidos']);
    exit;
}

// Validações específicas por status
if ($status === 'Orcada' && ($valor === null || !is_numeric($valor) || $valor < 0)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe o valor do orçamento']);
    exit;
}

if ($status === 'Reprovada' && empty($motivo)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe o motivo da reprovação']);
    exit;
}

try {
    $db   = new Database();
    $conn = $db->getConnection();

    // Monta UPDATE dinâmico
    $sets    = ['status = :status', 'atualizado_em = NOW()'];
    $params  = [':status' => $status, ':id' => $id];

    if ($status === 'Orcada' && $valor !== null) {
        $sets[]            = 'valor_orcamento = :valor';
        $params[':valor']  = (float)$valor;
    }

    if ($status === 'Reprovada' && $motivo) {
        $sets[]            = 'motivo_reprovacao = :motivo';
        $params[':motivo'] = $motivo;
    }

    $stmt = $conn->prepare('UPDATE pre_os SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado']);
        exit;
    }

    // Registrar no histórico de status
    $stmt_hist = $conn->prepare("
        INSERT INTO status_historico (pre_os_id, status, valor_orcamento, motivo, admin_id, criado_em)
        VALUES (:pre_os_id, :status, :valor, :motivo, :admin_id, NOW())
    ");
    $stmt_hist->execute([
        ':pre_os_id' => $id,
        ':status'    => $status,
        ':valor'     => ($status === 'Orcada' && $valor !== null) ? (float)$valor : null,
        ':motivo'    => ($status === 'Reprovada') ? $motivo : null,
        ':admin_id'  => $_SESSION['admin_id']
    ]);

    // Registrar na auditoria legada
    try {
        $stmt_audit = $conn->prepare("
            INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, valor_novo)
            VALUES (:uid, 'pre_os', :eid, 'edicao', :status)
        ");
        $stmt_audit->execute([':uid' => $_SESSION['admin_id'], ':eid' => $id, ':status' => $status]);
    } catch (PDOException $e) { /* auditoria opcional */ }

    echo json_encode([
        'sucesso'       => true,
        'atualizado_em' => date('d/m/Y H:i')
    ]);

} catch (PDOException $e) {
    error_log('Erro ao atualizar status: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno: ' . $e->getMessage()]);
}
