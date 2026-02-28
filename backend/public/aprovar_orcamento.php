<?php
/**
 * API PÚBLICA - APROVAÇÃO / REPROVAÇÃO PELO CLIENTE
 * Versão: 1.0
 * Data: 27/02/2026
 * Acesso: público (autenticado por token do pedido)
 */

require_once '../config/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso'=>false,'erro'=>'Método não permitido']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$token  = isset($input['token'])  ? trim($input['token'])  : '';
$status = isset($input['status']) ? trim($input['status']) : '';
$motivo = isset($input['motivo']) ? trim($input['motivo']) : null;
$pgto   = isset($input['pagamento']) && is_array($input['pagamento']) ? $input['pagamento'] : null;

if (empty($token)) {
    echo json_encode(['sucesso'=>false,'erro'=>'Token não informado']); exit;
}
if (!in_array($status, ['Aprovada','Reprovada'])) {
    echo json_encode(['sucesso'=>false,'erro'=>'Status inválido']); exit;
}
if ($status === 'Reprovada' && empty($motivo)) {
    echo json_encode(['sucesso'=>false,'erro'=>'Informe o motivo']); exit;
}

try {
    $db   = new Database();
    $conn = $db->getConnection();

    // Buscar pedido pelo token e validar que está no status correto
    $stmt = $conn->prepare("SELECT id, status FROM pre_os WHERE public_token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['sucesso'=>false,'erro'=>'Pedido não encontrado']); exit;
    }
    if (!in_array($pedido['status'], ['Orcada','Aguardando aprovacao'])) {
        echo json_encode(['sucesso'=>false,'erro'=>'Este pedido não está aguardando aprovação']); exit;
    }

    // Detectar colunas opcionais
    $cols = $conn->query("SHOW COLUMNS FROM pre_os")->fetchAll(PDO::FETCH_COLUMN);
    $has_motivo = in_array('motivo_reprovacao', $cols);
    $has_pgto   = in_array('forma_pagamento',   $cols);

    // Montar UPDATE
    $sets   = ['status = :status', 'atualizado_em = NOW()'];
    $params = [':status' => $status, ':id' => $pedido['id']];

    if ($status === 'Reprovada' && $has_motivo) {
        $sets[]            = 'motivo_reprovacao = :motivo';
        $params[':motivo'] = $motivo;
    }
    if ($status === 'Aprovada' && $has_pgto && $pgto) {
        $sets[]           = 'forma_pagamento = :pgto';
        $params[':pgto']  = $pgto['descricao'] ?? $pgto['forma'];
    }

    $conn->prepare('UPDATE pre_os SET ' . implode(', ',$sets) . ' WHERE id = :id')
         ->execute($params);

    // Histórico
    try {
        $hcols    = $conn->query("SHOW COLUMNS FROM status_historico")->fetchAll(PDO::FETCH_COLUMN);
        $has_pgto_hist = in_array('forma_pagamento', $hcols);

        if ($has_pgto_hist) {
            $conn->prepare("INSERT INTO status_historico (pre_os_id,status,motivo,forma_pagamento,criado_em) VALUES (:pid,:st,:mot,:pgto,NOW())")
                 ->execute([':pid'=>$pedido['id'],':st'=>$status,':mot'=>$motivo,':pgto'=>($pgto ? ($pgto['descricao']??$pgto['forma']) : null)]);
        } else {
            $conn->prepare("INSERT INTO status_historico (pre_os_id,status,motivo,criado_em) VALUES (:pid,:st,:mot,NOW())")
                 ->execute([':pid'=>$pedido['id'],':st'=>$status,':mot'=>$motivo]);
        }
    } catch (PDOException $e) { error_log('Histórico erro: '.$e->getMessage()); }

    echo json_encode(['sucesso' => true]);

} catch (PDOException $e) {
    error_log('aprovar_orcamento: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso'=>false,'erro'=>'Erro interno']);
}
