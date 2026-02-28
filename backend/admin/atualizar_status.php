<?php
/**
 * API - ATUALIZAR STATUS DO PEDIDO
 * Versão: 3.0 - inclui sub-status de serviço
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
$id     = isset($input['id'])              ? (int)$input['id']            : 0;
$status = isset($input['status'])          ? trim($input['status'])        : '';
$valor  = isset($input['valor_orcamento']) ? $input['valor_orcamento']    : null;
$prazo  = isset($input['prazo_orcamento']) ? $input['prazo_orcamento']    : null;
$motivo = isset($input['motivo'])          ? trim($input['motivo'])        : null;

$status_validos = [
    'Pre-OS', 'Em analise', 'Orcada', 'Aguardando aprovacao',
    'Aprovada',
    // Sub-status de serviço (após aprovação)
    'Pagamento recebido',
    'Instrumento recebido',
    'Servico iniciado',
    'Em desenvolvimento',
    'Servico finalizado',
    'Pronto para retirada',
    'Aguardando pagamento retirada',
    'Entregue',
    // Negativos
    'Reprovada', 'Cancelada',
];

if ($id <= 0 || !in_array($status, $status_validos)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros inválidos']);
    exit;
}

if ($status === 'Orcada') {
    if ($valor === null || !is_numeric($valor) || (float)$valor <= 0)
        { echo json_encode(['sucesso'=>false,'erro'=>'Informe o valor do orçamento']); exit; }
    if ($prazo === null || !is_numeric($prazo) || (int)$prazo <= 0)
        { echo json_encode(['sucesso'=>false,'erro'=>'Informe o prazo em dias úteis']); exit; }
}

if ($status === 'Reprovada' && empty($motivo))
    { echo json_encode(['sucesso'=>false,'erro'=>'Informe o motivo da reprovação']); exit; }

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $pre_os_cols      = $conn->query("SHOW COLUMNS FROM pre_os")->fetchAll(PDO::FETCH_COLUMN);
    $has_prazo_preos  = in_array('prazo_orcamento',   $pre_os_cols);
    $has_motivo_preos = in_array('motivo_reprovacao', $pre_os_cols);

    $sets   = ['status = :status', 'atualizado_em = NOW()'];
    $params = [':status' => $status, ':id' => $id];

    if ($status === 'Orcada') {
        $sets[]           = 'valor_orcamento = :valor';
        $params[':valor'] = (float)$valor;
        if ($has_prazo_preos) {
            $sets[]           = 'prazo_orcamento = :prazo';
            $params[':prazo'] = (int)$prazo;
        }
    }

    if ($status === 'Reprovada' && $has_motivo_preos) {
        $sets[]            = 'motivo_reprovacao = :motivo';
        $params[':motivo'] = $motivo;
    }

    $stmt = $conn->prepare('UPDATE pre_os SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($params);

    if ($stmt->rowCount() === 0)
        { echo json_encode(['sucesso'=>false,'erro'=>'Pedido não encontrado']); exit; }

    // Histórico
    try {
        $hist_cols      = $conn->query("SHOW COLUMNS FROM status_historico")->fetchAll(PDO::FETCH_COLUMN);
        $has_prazo_hist = in_array('prazo_orcamento', $hist_cols);

        if ($has_prazo_hist) {
            $conn->prepare("
                INSERT INTO status_historico
                    (pre_os_id, status, valor_orcamento, prazo_orcamento, motivo, admin_id, criado_em)
                VALUES (:pid, :st, :valor, :prazo, :motivo, :admin_id, NOW())
            ")->execute([
                ':pid'      => $id,
                ':st'       => $status,
                ':valor'    => ($status === 'Orcada')    ? (float)$valor : null,
                ':prazo'    => ($status === 'Orcada')    ? (int)$prazo   : null,
                ':motivo'   => ($status === 'Reprovada') ? $motivo       : null,
                ':admin_id' => $_SESSION['admin_id'],
            ]);
        } else {
            $conn->prepare("
                INSERT INTO status_historico
                    (pre_os_id, status, valor_orcamento, motivo, admin_id, criado_em)
                VALUES (:pid, :st, :valor, :motivo, :admin_id, NOW())
            ")->execute([
                ':pid'      => $id,
                ':st'       => $status,
                ':valor'    => ($status === 'Orcada')    ? (float)$valor : null,
                ':motivo'   => ($status === 'Reprovada') ? $motivo       : null,
                ':admin_id' => $_SESSION['admin_id'],
            ]);
        }
    } catch (PDOException $e) {
        error_log('Historico erro: ' . $e->getMessage());
    }

    try {
        $conn->prepare("INSERT INTO auditoria (usuario_id,entidade,entidade_id,acao,valor_novo) VALUES (:u,'pre_os',:e,'edicao',:s)")
             ->execute([':u'=>$_SESSION['admin_id'],':e'=>$id,':s'=>$status]);
    } catch (PDOException $e) {}

    echo json_encode(['sucesso' => true, 'atualizado_em' => date('d/m/Y H:i')]);

} catch (PDOException $e) {
    error_log('Erro ao atualizar status: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno: ' . $e->getMessage()]);
}
