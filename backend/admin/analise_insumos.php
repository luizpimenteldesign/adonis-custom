<?php
/**
 * API: Análise de insumos de uma Pré-OS (VERSÃO 3.1 - FILTRO POR SERVIÇOS)
 *
 * GET  ?pre_os_id=X       → retorna categorias FILTRADAS pelos serviços e insumos já selecionados
 * GET  ?categoria=X       → retorna insumos de uma categoria específica
 * POST                    → salva a lista confirmada em pre_os_insumos
 */
require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

// ── GET: BUSCAR INSUMOS DE UMA CATEGORIA ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['categoria'])) {
    $categoria = trim($_GET['categoria']);
    $busca = trim($_GET['q'] ?? '');
    
    $sql = "SELECT id, nome, unidade, valor_unitario, quantidade_estoque FROM insumos WHERE ativo=1";
    
    if ($categoria !== 'Todos') {
        $sql .= " AND categoria = :cat";
    }
    
    if ($busca) {
        $sql .= " AND nome LIKE :busca";
    }
    
    $sql .= " ORDER BY nome LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    
    if ($categoria !== 'Todos') {
        $stmt->bindValue(':cat', $categoria);
    }
    if ($busca) {
        $stmt->bindValue(':busca', '%' . $busca . '%');
    }
    
    $stmt->execute();
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['sucesso' => true, 'insumos' => $insumos]);
    exit;
}

// ── GET: DADOS INICIAIS DO PEDIDO ────────────────────────────────
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

    // ✅ FILTRO: Busca categorias APENAS dos insumos vinculados aos serviços selecionados
    $categorias = ['Todos']; // Sempre inclui "Todos" primeiro
    try {
        // Busca IDs dos serviços desta pré-OS
        $servico_ids = array_column($servicos, 'id');
        
        if (!empty($servico_ids)) {
            $placeholders = implode(',', array_fill(0, count($servico_ids), '?'));
            
            // SQL: Busca categorias dos insumos vinculados aos serviços
            $sql_cat = "
                SELECT DISTINCT i.categoria
                FROM insumos i
                INNER JOIN insumos_servicos isv ON i.id = isv.insumo_id
                WHERE isv.servico_id IN ($placeholders)
                  AND i.ativo = 1
                  AND i.categoria IS NOT NULL
                  AND i.categoria != ''
                ORDER BY i.categoria
            ";
            
            $stmt_cat = $conn->prepare($sql_cat);
            $stmt_cat->execute($servico_ids);
            $rows = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);
            
            // Se encontrou categorias, adiciona. Senão, mantém só "Todos"
            if (!empty($rows)) {
                $categorias = array_merge($categorias, $rows);
            }
        }
    } catch (Exception $e) {
        // Se der erro, mantém apenas "Todos"
        error_log('[ANALISE_INSUMOS] Erro ao buscar categorias: ' . $e->getMessage());
    }

    // Insumos já selecionados (se houver)
    $stmt_ins = $conn->prepare("
        SELECT poi.insumo_id, poi.quantidade, poi.cliente_fornece,
               ins.nome, ins.unidade, ins.valor_unitario, ins.quantidade_estoque
        FROM pre_os_insumos poi
        JOIN insumos ins ON poi.insumo_id = ins.id
        WHERE poi.pre_os_id = :id
    ");
    $stmt_ins->execute([':id' => $pre_os_id]);
    $insumos_selecionados = $stmt_ins->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'sucesso'              => true,
        'pedido'               => $pedido,
        'servicos'             => $servicos,
        'categorias'           => $categorias,
        'insumos_selecionados' => $insumos_selecionados,
    ]);
    exit;
}

// ── POST: SALVAR INSUMOS SELECIONADOS ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $pre_os_id = isset($body['pre_os_id']) ? (int)$body['pre_os_id'] : 0;
    $insumos   = $body['insumos'] ?? [];

    if (!$pre_os_id) { echo json_encode(['erro' => 'ID inválido']); exit; }

    try {
        $conn->beginTransaction();

        // Remove registros anteriores
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
        try {
            $conn->prepare("
                INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, valor_novo)
                VALUES (:uid, 'pre_os', :eid, 'edicao', 'Em analise')
            ")->execute([':uid' => $_SESSION['usuario_id'] ?? null, ':eid' => $pre_os_id]);
        } catch (Exception $e) {}

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
