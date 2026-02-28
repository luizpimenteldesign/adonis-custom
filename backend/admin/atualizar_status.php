<?php
/**
 * API - ATUALIZAR STATUS DO PEDIDO
 * Versão: 3.1 - retorna wa.me para Adonis notificar cliente
 * Data: 28/02/2026
 */

require_once 'auth.php';
require_once '../config/Database.php';
require_once '../helpers/whatsapp.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$id     = isset($input['id'])              ? (int)$input['id']         : 0;
$status = isset($input['status'])          ? trim($input['status'])     : '';
$valor  = isset($input['valor_orcamento']) ? $input['valor_orcamento'] : null;
$prazo  = isset($input['prazo_orcamento']) ? $input['prazo_orcamento'] : null;
$motivo = isset($input['motivo'])          ? trim($input['motivo'])     : null;

$status_validos = [
    'Pre-OS', 'Em analise', 'Orcada', 'Aguardando aprovacao',
    'Aprovada',
    'Pagamento recebido', 'Instrumento recebido', 'Servico iniciado',
    'Em desenvolvimento', 'Servico finalizado', 'Pronto para retirada',
    'Aguardando pagamento retirada', 'Entregue',
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

    // ─── Gera link wa.me para o Adonis notificar o cliente ───────────────────
    $wa_link = null;
    // Só gera link para os status que o cliente precisa ser avisado
    $status_notifica = [
        'Em analise', 'Orcada', 'Pagamento recebido', 'Instrumento recebido',
        'Servico iniciado', 'Em desenvolvimento', 'Servico finalizado',
        'Pronto para retirada', 'Aguardando pagamento retirada', 'Entregue', 'Cancelada',
    ];

    if (in_array($status, $status_notifica)) {
        try {
            // Busca dados do pedido + telefone do cliente
            $stmt_p = $conn->prepare("
                SELECT p.id, p.public_token,
                       c.nome  AS cliente_nome,
                       c.telefone AS cliente_telefone,
                       i.tipo  AS instrumento_tipo,
                       i.marca AS instrumento_marca,
                       i.modelo AS instrumento_modelo
                FROM pre_os p
                LEFT JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN instrumentos i ON p.instrumento_id = i.id
                WHERE p.id = :id LIMIT 1
            ");
            $stmt_p->execute([':id' => $id]);
            $pedido = $stmt_p->fetch(PDO::FETCH_ASSOC);

            if ($pedido) {
                $extras = [];
                if ($status === 'Orcada') {
                    $extras['valor'] = $valor;
                    $extras['prazo'] = $prazo;
                }

                $msg_cliente = wa_msg_status_para_cliente($pedido, $status, $extras);

                // Se tiver telefone do cliente → link direto pra ele
                // Senão → link abre conversa em branco (Adonis digita o número)
                $tel = preg_replace('/\D/', '', $pedido['cliente_telefone'] ?? '');
                if (!empty($tel)) {
                    // Garante DDI 55
                    if (strlen($tel) <= 11) $tel = '55' . $tel;
                    $wa_link = wa_link_para_cliente($tel, $msg_cliente);
                } else {
                    // Fallback: abre WhatsApp Web sem número, só com o texto
                    $wa_link = 'https://wa.me/?text=' . rawurlencode($msg_cliente);
                }
            }
        } catch (Throwable $e) {
            error_log('[WhatsApp wa.me] ' . $e->getMessage());
        }
    }
    // ──────────────────────────────────────────────────────────────────────────

    $resposta = [
        'sucesso'      => true,
        'atualizado_em'=> date('d/m/Y H:i'),
    ];
    if ($wa_link) $resposta['wa_link'] = $wa_link;

    echo json_encode($resposta);

} catch (PDOException $e) {
    error_log('Erro ao atualizar status: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno: ' . $e->getMessage()]);
}
