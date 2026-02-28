<?php
/**
 * API PÚBLICA - APROVAÇÃO / REPROVAÇÃO PELO CLIENTE
 * Versão: 2.0 - salva todos os campos de pagamento no histórico
 * Data: 27/02/2026
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

if (empty($token))                            { echo json_encode(['sucesso'=>false,'erro'=>'Token não informado']);  exit; }
if (!in_array($status, ['Aprovada','Reprovada'])) { echo json_encode(['sucesso'=>false,'erro'=>'Status inválido']); exit; }
if ($status === 'Reprovada' && empty($motivo))    { echo json_encode(['sucesso'=>false,'erro'=>'Informe o motivo']); exit; }
if ($status === 'Aprovada'  && empty($pgto))      { echo json_encode(['sucesso'=>false,'erro'=>'Informe a forma de pagamento']); exit; }

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, status FROM pre_os WHERE public_token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido)                                                               { echo json_encode(['sucesso'=>false,'erro'=>'Pedido não encontrado']); exit; }
    if (!in_array($pedido['status'], ['Orcada','Aguardando aprovacao']))        { echo json_encode(['sucesso'=>false,'erro'=>'Este pedido não está aguardando aprovação']); exit; }

    $cols       = $conn->query("SHOW COLUMNS FROM pre_os")->fetchAll(PDO::FETCH_COLUMN);
    $has_motivo = in_array('motivo_reprovacao', $cols);

    $sets   = ['status = :status', 'atualizado_em = NOW()'];
    $params = [':status' => $status, ':id' => $pedido['id']];

    if ($status === 'Reprovada' && $has_motivo) {
        $sets[]            = 'motivo_reprovacao = :motivo';
        $params[':motivo'] = $motivo;
    }

    $conn->prepare('UPDATE pre_os SET ' . implode(', ',$sets) . ' WHERE id = :id')->execute($params);

    // Histórico com todos os campos de pagamento
    try {
        $hcols = $conn->query("SHOW COLUMNS FROM status_historico")->fetchAll(PDO::FETCH_COLUMN);

        $has_forma    = in_array('forma_pagamento',    $hcols);
        $has_parcelas = in_array('parcelas',           $hcols);
        $has_vfinal   = in_array('valor_final',        $hcols);
        $has_pparcela = in_array('por_parcela',        $hcols);
        $has_desc     = in_array('descricao_pagamento',$hcols);

        $h_fields = 'pre_os_id, status, motivo, criado_em';
        $h_vals   = ':pid, :st, :mot, NOW()';
        $h_params = [':pid'=>$pedido['id'], ':st'=>$status, ':mot'=>$motivo];

        if ($has_forma && $pgto) {
            $h_fields .= ', forma_pagamento';
            $h_vals   .= ', :forma';
            $h_params[':forma'] = $pgto['forma'] ?? null;
        }
        if ($has_parcelas && $pgto) {
            $h_fields .= ', parcelas';
            $h_vals   .= ', :parcelas';
            $h_params[':parcelas'] = isset($pgto['parcelas']) ? (int)$pgto['parcelas'] : null;
        }
        if ($has_vfinal && $pgto) {
            $h_fields .= ', valor_final';
            $h_vals   .= ', :vfinal';
            $h_params[':vfinal'] = isset($pgto['valor_final']) ? (float)$pgto['valor_final'] : null;
        }
        if ($has_pparcela && $pgto) {
            $h_fields .= ', por_parcela';
            $h_vals   .= ', :pparcela';
            $h_params[':pparcela'] = isset($pgto['por_parcela']) ? (float)$pgto['por_parcela'] : null;
        }
        if ($has_desc && $pgto) {
            $h_fields .= ', descricao_pagamento';
            $h_vals   .= ', :desc';
            $h_params[':desc'] = $pgto['descricao'] ?? null;
        }

        $conn->prepare("INSERT INTO status_historico ($h_fields) VALUES ($h_vals)")->execute($h_params);

    } catch (PDOException $e) { error_log('Histórico erro: '.$e->getMessage()); }

    echo json_encode(['sucesso' => true]);

} catch (PDOException $e) {
    error_log('aprovar_orcamento: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso'=>false,'erro'=>'Erro interno']);
}
