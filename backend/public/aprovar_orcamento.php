<?php
/**
 * API PÚBLICA - APROVAÇÃO / REPROVAÇÃO PELO CLIENTE
 * Versão: 2.1 - integração WhatsApp (CallMeBot para o Adonis)
 * Data: 28/02/2026
 */

require_once '../config/Database.php';
require_once '../helpers/whatsapp.php';

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

if (empty($token))                                { echo json_encode(['sucesso'=>false,'erro'=>'Token não informado']);  exit; }
if (!in_array($status, ['Aprovada','Reprovada'])) { echo json_encode(['sucesso'=>false,'erro'=>'Status inválido']);      exit; }
if ($status === 'Reprovada' && empty($motivo))    { echo json_encode(['sucesso'=>false,'erro'=>'Informe o motivo']);     exit; }
if ($status === 'Aprovada'  && empty($pgto))      { echo json_encode(['sucesso'=>false,'erro'=>'Informe a forma de pagamento']); exit; }

try {
    $db   = new Database();
    $conn = $db->getConnection();

    // Busca pedido com dados completos para a notificação
    $stmt = $conn->prepare("
        SELECT p.id, p.status, p.public_token,
               c.nome as cliente_nome,
               i.tipo as instrumento_tipo,
               i.marca as instrumento_marca,
               i.modelo as instrumento_modelo
        FROM pre_os p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN instrumentos i ON p.instrumento_id = i.id
        WHERE p.public_token = :token LIMIT 1
    ");
    $stmt->execute([':token' => $token]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) { echo json_encode(['sucesso'=>false,'erro'=>'Pedido não encontrado']); exit; }
    if (!in_array($pedido['status'], ['Orcada','Aguardando aprovacao'])) {
        echo json_encode(['sucesso'=>false,'erro'=>'Este pedido não está aguardando aprovação']);
        exit;
    }

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

        $has_forma    = in_array('forma_pagamento',     $hcols);
        $has_parcelas = in_array('parcelas',            $hcols);
        $has_vfinal   = in_array('valor_final',         $hcols);
        $has_pparcela = in_array('por_parcela',         $hcols);
        $has_desc     = in_array('descricao_pagamento', $hcols);

        $h_fields = 'pre_os_id, status, motivo, criado_em';
        $h_vals   = ':pid, :st, :mot, NOW()';
        $h_params = [':pid'=>$pedido['id'], ':st'=>$status, ':mot'=>$motivo];

        if ($has_forma    && $pgto) { $h_fields .= ', forma_pagamento';     $h_vals .= ', :forma';     $h_params[':forma']    = $pgto['forma']       ?? null; }
        if ($has_parcelas && $pgto) { $h_fields .= ', parcelas';            $h_vals .= ', :parcelas';  $h_params[':parcelas'] = isset($pgto['parcelas']) ? (int)$pgto['parcelas'] : null; }
        if ($has_vfinal   && $pgto) { $h_fields .= ', valor_final';         $h_vals .= ', :vfinal';    $h_params[':vfinal']   = isset($pgto['valor_final'])  ? (float)$pgto['valor_final']  : null; }
        if ($has_pparcela && $pgto) { $h_fields .= ', por_parcela';         $h_vals .= ', :pparcela';  $h_params[':pparcela'] = isset($pgto['por_parcela'])  ? (float)$pgto['por_parcela']  : null; }
        if ($has_desc     && $pgto) { $h_fields .= ', descricao_pagamento'; $h_vals .= ', :desc';      $h_params[':desc']     = $pgto['descricao']   ?? null; }

        $conn->prepare("INSERT INTO status_historico ($h_fields) VALUES ($h_vals)")->execute($h_params);

    } catch (PDOException $e) { error_log('Histórico erro: '.$e->getMessage()); }

    // ─── NOTIFICAÇÃO WHATSAPP PARA O ADONIS ───────────────────────────────
    try {
        if ($status === 'Aprovada') {
            $msg = wa_msg_aprovacao($pedido, $pgto ?? []);
        } else {
            $msg = wa_msg_reprovacao($pedido, $motivo ?? '');
        }
        wa_notificar_adonis($msg);
    } catch (Throwable $e) {
        error_log('[WhatsApp] Erro ao notificar: ' . $e->getMessage());
        // Não interrompe o fluxo — a aprovação já foi salva
    }
    // ──────────────────────────────────────────────────────────────────────

    echo json_encode(['sucesso' => true]);

} catch (PDOException $e) {
    error_log('aprovar_orcamento: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso'=>false,'erro'=>'Erro interno']);
}
