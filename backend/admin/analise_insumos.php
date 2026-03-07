<?php
/**
 * API: Análise de insumos de uma Pré-OS
 *
 * GET  ?pre_os_id=X   → retorna insumos sugeridos pelos serviços
 *                        (pré-marca cliente_fornece=1 quando estoque <= 0)
 *                        se já houver registros em pre_os_insumos, retorna eles
 * POST               → salva a lista confirmada em pre_os_insumos
 *                        e muda status para 'Em analise'
 */
require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pre_os_id = isset($_GET['pre_os_id']) ? (int)$_GET['pre_os_id'] : 0;
    if (!$pre_os_id) { echo json_encode(['erro' => 'ID inválido']); exit; }

    // Dados do pedido
    $stmt = $conn->prepare("
        SELECT p.id, p.status, p.observacoes,
               c.nome  AS cliente_nome,
               i.tipo  AS instrumento_tipo,
               i.marca AS instrumento_marca,
               i.modelo AS instrumento_modelo
        FROM pre_os p
        JOIN clientes    c ON p.cliente_id    = c.id
        JOIN instrumentos i ON p.instrumento_id = i.id
        WHERE p.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $pre_os_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) { echo json_encode(['erro' => 'Pedido não encontrado']); exit; }

    // Serviços do pedido
    $stmt_s = $conn->prepare("
        SELECT s.id, s.nome
        FROM pre_os_servicos ps
        JOIN servicos s ON ps.servico_id = s.id
        WHERE ps.pre_os_id = :id
    ");
    $stmt_s->execute([':id' => $pre_os_id]);
    $servicos = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

    // Verifica se já existem insumos confirmados
    $stmt_ex = $conn->prepare("SELECT COUNT(*) FROM pre_os_insumos WHERE pre_os_id = :id");
    $stmt_ex->execute([':id' => $pre_os_id]);
    $ja_confirmado = (int)$stmt_ex->fetchColumn() > 0;

    if ($ja_confirmado) {
        // Retorna o que já foi salvo
        $stmt_i = $conn->prepare("
            SELECT poi.id, poi.insumo_id, poi.quantidade, poi.valor_unitario, poi.cliente_fornece,
                   ins.nome, ins.unidade, ins.quantidade_estoque,
                   (
                       SELECT GROUP_CONCAT(s2.nome SEPARATOR ', ')
                       FROM insumos_servicos is2
                       JOIN servicos s2 ON is2.servicoid = s2.id
                       WHERE is2.insumoid = ins.id
                       AND is2.servicoid IN (
                           SELECT servico_id FROM pre_os_servicos WHERE pre_os_id = :pre_os_id2
                       )
                   ) AS servicos_origem
            FROM pre_os_insumos poi
            JOIN insumos ins ON poi.insumo_id = ins.id
            WHERE poi.pre_os_id = :pre_os_id
        ");
        $stmt_i->execute([':pre_os_id' => $pre_os_id, ':pre_os_id2' => $pre_os_id]);
        $insumos = $stmt_i->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Monta lista sugerida a partir dos serviços + insumos_servicos
        if (empty($servicos)) {
            $insumos = [];
        } else {
            $ids_servicos = implode(',', array_map(fn($s) => (int)$s['id'], $servicos));
            $stmt_i = $conn->prepare("
                SELECT ins.id AS insumo_id,
                       ins.nome, ins.unidade,
                       ins.valor_unitario,
                       ins.quantidade_estoque,
                       GROUP_CONCAT(s.nome SEPARATOR ', ') AS servicos_origem
                FROM insumos_servicos is_rel
                JOIN insumos  ins ON is_rel.insumoid  = ins.id
                JOIN servicos s   ON is_rel.servicoid = s.id
                WHERE is_rel.servicoid IN ($ids_servicos)
                  AND ins.ativo = 1
                GROUP BY ins.id
            ");
            $stmt_i->execute();
            $rows = $stmt_i->fetchAll(PDO::FETCH_ASSOC);

            $insumos = [];
            foreach ($rows as $r) {
                $sem_estoque = (float)$r['quantidade_estoque'] <= 0;
                $insumos[] = [
                    'insumo_id'          => (int)$r['insumo_id'],
                    'nome'               => $r['nome'],
                    'unidade'            => $r['unidade'],
                    'valor_unitario'     => (float)$r['valor_unitario'],
                    'quantidade_estoque' => (float)$r['quantidade_estoque'],
                    'quantidade'         => 1,
                    'cliente_fornece'    => $sem_estoque ? 1 : 0,
                    'servicos_origem'    => $r['servicos_origem'],
                ];
            }
        }
    }

    echo json_encode([
        'sucesso'        => true,
        'ja_confirmado'  => $ja_confirmado,
        'pedido'         => $pedido,
        'servicos'       => $servicos,
        'insumos'        => $insumos,
    ]);
    exit;
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $pre_os_id = isset($body['pre_os_id']) ? (int)$body['pre_os_id'] : 0;
    $insumos   = $body['insumos'] ?? [];

    if (!$pre_os_id) { echo json_encode(['erro' => 'ID inválido']); exit; }

    try {
        $conn->beginTransaction();

        // Remove registros anteriores (re-análise)
        $conn->prepare("DELETE FROM pre_os_insumos WHERE pre_os_id = :id")->execute([':id' => $pre_os_id]);

        // Insere insumos confirmados
        $stmt_ins = $conn->prepare("
            INSERT INTO pre_os_insumos (pre_os_id, insumo_id, quantidade, valor_unitario, cliente_fornece)
            VALUES (:pre_os_id, :insumo_id, :qtd, :valor, :cf)
        ");

        $total_insumos = 0;
        foreach ($insumos as $ins) {
            $cf    = (int)($ins['cliente_fornece'] ?? 0);
            $qtd   = (float)($ins['quantidade']    ?? 1);
            $valor = (float)($ins['valor_unitario'] ?? 0);
            $stmt_ins->execute([
                ':pre_os_id' => $pre_os_id,
                ':insumo_id' => (int)$ins['insumo_id'],
                ':qtd'       => $qtd,
                ':valor'     => $valor,
                ':cf'        => $cf,
            ]);
            if (!$cf) $total_insumos += $qtd * $valor;
        }

        // Atualiza status para Em analise
        $stmt_st = $conn->prepare("UPDATE pre_os SET status='Em analise', atualizado_em=NOW() WHERE id=:id");
        $stmt_st->execute([':id' => $pre_os_id]);

        // Auditoria
        $conn->prepare("
            INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, valor_novo)
            VALUES (:uid, 'pre_os', :eid, 'edicao', 'Em analise')
        ")->execute([':uid' => $_SESSION['usuario_id'] ?? null, ':eid' => $pre_os_id]);

        $conn->commit();

        // Busca valor base dos serviços
        $stmt_sv = $conn->prepare("
            SELECT COALESCE(SUM(s.valor_base),0) AS total
            FROM pre_os_servicos ps
            JOIN servicos s ON ps.servico_id = s.id
            WHERE ps.pre_os_id = :id
        ");
        $stmt_sv->execute([':id' => $pre_os_id]);
        $total_servicos = (float)$stmt_sv->fetchColumn();

        echo json_encode([
            'sucesso'         => true,
            'total_servicos'  => $total_servicos,
            'total_insumos'   => round($total_insumos, 2),
            'total_orcamento' => round($total_servicos + $total_insumos, 2),
        ]);
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['erro' => 'Método não suportado']);
